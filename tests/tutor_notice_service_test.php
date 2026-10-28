<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Tests for {@see \block_dixeo_tutor\service\tutor_notice_service}.
 *
 * @package    block_dixeo_tutor
 * @category   test
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @author     Pierre FACQ <pierre.facq@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_dixeo_tutor;

use block_dixeo_tutor\service\tutor_notice_service;
use block_dixeo_tutor\service\tutor_proactive_context_service;

/**
 * Local notices: greeting, course completed, quiz graded.
 *
 * @covers \block_dixeo_tutor\service\tutor_notice_service
 */
final class tutor_notice_service_test extends \advanced_testcase {
    /** @var tutor_notice_service */
    private tutor_notice_service $service;

    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        $this->service = new tutor_notice_service();
    }

    /**
     * Plain text of every variant of one notice key.
     *
     * @param string $key Notice key, e.g. firstvisit or quizgraded_high.
     * @param int $count
     * @param array $a Placeholder values.
     * @return string[]
     */
    private function variants(string $key, int $count, array $a): array {
        $strings = [];
        for ($i = 1; $i <= $count; $i++) {
            $strings[] = get_string('notice_' . $key . '_' . $i, 'block_dixeo_tutor', (object) $a);
        }
        return $strings;
    }

    /**
     * Store queued events for a user and course.
     *
     * @param int $userid
     * @param int $courseid
     * @param array $events
     */
    private function queue(int $userid, int $courseid, array $events): void {
        global $DB;
        $DB->insert_record(tutor_proactive_context_service::TABLE, (object) [
            'userid' => $userid,
            'courseid' => $courseid,
            'message' => json_encode($events),
            'timemodified' => time(),
        ]);
    }

    /**
     * Mark the learner as greeted at the given time.
     *
     * @param int $userid
     * @param int $courseid
     * @param int $timestamp
     */
    private function set_last_greeting(int $userid, int $courseid, int $timestamp): void {
        set_user_preference(tutor_notice_service::PREF_LAST_GREETING_PREFIX . $courseid, $timestamp, $userid);
    }

    public function test_first_visit_greets_by_first_name(): void {
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student', ['firstname' => 'Alice']);

        $notices = $this->service->take_notices((int) $user->id, (int) $course->id);

        $this->assertCount(1, $notices);
        $this->assertContains($notices[0]['content'], $this->variants('firstvisit', 3, ['name' => 'Alice']));
        $this->assertStringStartsWith('<p>', $notices[0]['contenthtml']);
    }

    public function test_no_greeting_within_a_day_then_return_greeting(): void {
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student', ['firstname' => 'Bob']);

        $this->assertCount(1, $this->service->take_notices((int) $user->id, (int) $course->id));
        $this->assertSame([], $this->service->take_notices((int) $user->id, (int) $course->id));

        $this->set_last_greeting((int) $user->id, (int) $course->id, time() - DAYSECS - 60);
        $notices = $this->service->take_notices((int) $user->id, (int) $course->id);
        $this->assertCount(1, $notices);
        $this->assertContains($notices[0]['content'], $this->variants('returnvisit', 4, ['name' => 'Bob']));
    }

    public function test_queued_notices_follow_the_greeting_in_order(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course(['shortname' => 'BIO101']);
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student', ['firstname' => 'Cleo']);
        $this->queue((int) $user->id, (int) $course->id, [
            ['type' => 'quiz_graded', 'time' => 1, 'quizname' => 'Cells', 'grade' => 9, 'maxgrade' => 10],
            ['type' => 'guide_started', 'time' => 2],
            ['type' => 'course_completed', 'time' => 3],
        ]);

        $notices = $this->service->take_notices((int) $user->id, (int) $course->id);

        $this->assertCount(3, $notices);
        $this->assertContains($notices[0]['content'], $this->variants('firstvisit', 3, ['name' => 'Cleo']));
        $this->assertContains($notices[1]['content'], $this->variants('quizgraded_high', 3, [
            'name' => 'Cleo', 'quiz' => 'Cells', 'grade' => '9', 'maxgrade' => '10',
        ]));
        $this->assertContains($notices[2]['content'], $this->variants('coursecompleted', 3, [
            'name' => 'Cleo', 'course' => 'BIO101',
        ]));

        // The Guide me turn stays queued for the API; notices are shown once.
        $this->assertSame([], $this->service->take_notices((int) $user->id, (int) $course->id));
        $queue = (new tutor_proactive_context_service())->decode_queue((string) $DB->get_field(
            tutor_proactive_context_service::TABLE,
            'message',
            ['userid' => $user->id, 'courseid' => $course->id]
        ));
        $this->assertSame('guide_started', $queue[0]['type']);
    }

    /**
     * Quiz score bands.
     *
     * @return array
     */
    public static function quiz_band_provider(): array {
        return [
            'high' => [8, 10, 'quizgraded_high'],
            'mid upper' => [7.9, 10, 'quizgraded_mid'],
            'mid' => [5, 10, 'quizgraded_mid'],
            'low' => [4.5, 10, 'quizgraded_low'],
        ];
    }

    /**
     * The quiz notice variant follows the score band.
     *
     * @param float $grade
     * @param float $maxgrade
     * @param string $key
     * @dataProvider quiz_band_provider
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('quiz_band_provider')]
    public function test_quiz_notice_variant_follows_the_score_band(float $grade, float $maxgrade, string $key): void {
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student', ['firstname' => 'Dan']);
        $this->set_last_greeting((int) $user->id, (int) $course->id, time());
        $this->queue((int) $user->id, (int) $course->id, [
            ['type' => 'quiz_graded', 'time' => 1, 'quizname' => 'Q1', 'grade' => $grade, 'maxgrade' => $maxgrade],
        ]);

        $notices = $this->service->take_notices((int) $user->id, (int) $course->id);

        $this->assertCount(1, $notices);
        $this->assertContains($notices[0]['content'], $this->variants($key, 3, [
            'name' => 'Dan', 'quiz' => 'Q1', 'grade' => format_float($grade, 2, true, true), 'maxgrade' => '10',
        ]));
    }

    public function test_placeholders_are_escaped(): void {
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student', ['firstname' => 'Ann & Bo']);
        $this->set_last_greeting((int) $user->id, (int) $course->id, time());
        $this->queue((int) $user->id, (int) $course->id, [
            ['type' => 'quiz_graded', 'time' => 1, 'quizname' => 'A < B', 'grade' => 1, 'maxgrade' => 10],
        ]);

        $notices = $this->service->take_notices((int) $user->id, (int) $course->id);

        $this->assertStringContainsString('Ann &amp; Bo', $notices[0]['contenthtml']);
        $this->assertStringContainsString('A &lt; B', $notices[0]['contenthtml']);
        $this->assertStringContainsString('Ann & Bo', $notices[0]['content']);
    }

    public function test_unknown_types_are_dropped(): void {
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->set_last_greeting((int) $user->id, (int) $course->id, time());
        $this->queue((int) $user->id, (int) $course->id, [['type' => 'return_visit_warm', 'time' => 1]]);

        $this->assertSame([], $this->service->take_notices((int) $user->id, (int) $course->id));
    }
}
