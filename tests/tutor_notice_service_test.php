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
use block_dixeo_tutor\task\post_notice;

/**
 * Tutor notices: greeting shown locally, quiz and completion texts.
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

    public function test_greeting_is_stored_with_the_text_shown(): void {
        set_config('api_key', 'phpunit-api-key', 'local_dixeo');
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student', ['firstname' => 'Alice']);

        $notices = $this->service->take_notices((int) $user->id, (int) $course->id);

        $tasks = \core\task\manager::get_adhoc_tasks(post_notice::class);
        $this->assertCount(1, $tasks);
        $data = reset($tasks)->get_custom_data();
        $this->assertSame('first_visit', $data->event->type);
        $this->assertSame($notices[0]['content'], $data->text);
    }

    public function test_no_greeting_within_a_day_then_return_greeting(): void {
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student', ['firstname' => 'Bob']);

        $this->assertCount(1, $this->service->take_notices((int) $user->id, (int) $course->id));
        $this->assertSame([], $this->service->take_notices((int) $user->id, (int) $course->id));

        $this->set_last_greeting((int) $user->id, (int) $course->id, time() - DAYSECS - 60);
        tutor_notice_service::record_learner_message((int) $user->id, (int) $course->id);
        $notices = $this->service->take_notices((int) $user->id, (int) $course->id);
        $this->assertCount(1, $notices);
        $this->assertContains($notices[0]['content'], $this->variants('returnvisit', 4, ['name' => 'Bob']));
    }

    public function test_a_silent_learner_is_not_greeted_again(): void {
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->set_last_greeting((int) $user->id, (int) $course->id, time() - 3 * DAYSECS);

        $this->assertSame([], $this->service->take_notices((int) $user->id, (int) $course->id));
    }

    public function test_queued_events_are_not_shown_locally(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student', ['firstname' => 'Cleo']);
        $this->set_last_greeting((int) $user->id, (int) $course->id, time());
        $this->queue((int) $user->id, (int) $course->id, [['type' => 'guide_started', 'time' => 2]]);

        $this->assertSame([], $this->service->take_notices((int) $user->id, (int) $course->id));
        $this->assertSame(1, $DB->count_records(tutor_proactive_context_service::TABLE));
    }

    public function test_course_completed_text(): void {
        $course = $this->getDataGenerator()->create_course(['shortname' => 'BIO101']);
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student', ['firstname' => 'Cleo']);

        $text = $this->service->text(['type' => 'course_completed', 'time' => 1], (int) $user->id, (int) $course->id);

        $this->assertContains($text, $this->variants('coursecompleted', 3, ['name' => 'Cleo', 'course' => 'BIO101']));
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
        $event = ['type' => 'quiz_graded', 'time' => 1, 'quizname' => 'Q1', 'grade' => $grade, 'maxgrade' => $maxgrade];

        $text = $this->service->text($event, (int) $user->id, (int) $course->id);

        $this->assertContains($text, $this->variants($key, 3, [
            'name' => 'Dan', 'quiz' => 'Q1', 'grade' => format_float($grade, 2, true, true), 'maxgrade' => '10',
        ]));
    }

    public function test_greeting_html_is_escaped_and_text_is_plain(): void {
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student', ['firstname' => 'Ann & Bo']);

        $notices = $this->service->take_notices((int) $user->id, (int) $course->id);
        $text = $this->service->text(
            ['type' => 'quiz_graded', 'time' => 1, 'quizname' => 'A < B', 'grade' => 1, 'maxgrade' => 10],
            (int) $user->id,
            (int) $course->id
        );

        $this->assertStringContainsString('Ann &amp; Bo', $notices[0]['contenthtml']);
        $this->assertStringContainsString('Ann & Bo', $notices[0]['content']);
        $this->assertStringContainsString('A < B', $text);
        $this->assertStringContainsString('Ann & Bo', $text);
    }

    public function test_unknown_types_have_no_text(): void {
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $this->assertNull($this->service->text(['type' => 'return_visit_warm', 'time' => 1], (int) $user->id, (int) $course->id));
    }
}
