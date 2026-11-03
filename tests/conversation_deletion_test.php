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
 * "Delete my conversation" leaves nothing queued that could recreate it.
 *
 * @package    block_dixeo_tutor
 * @category   test
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_dixeo_tutor;

use block_dixeo_tutor\external\delete_conversation;
use block_dixeo_tutor\service\tutor_erasure_marker;
use block_dixeo_tutor\service\tutor_proactive_context_service;
use block_dixeo_tutor\task\post_notice;
use local_dixeo\external\service_factory;
use local_dixeo\service\consent_service;
use local_dixeo\service\tutor_service;

/**
 * Queued Guide me prompts and notices do not survive a conversation deletion.
 *
 * @covers \block_dixeo_tutor\external\delete_conversation
 * @covers \block_dixeo_tutor\service\tutor_erasure_marker
 * @covers \block_dixeo_tutor\service\tutor_proactive_context_service::flush
 * @covers \block_dixeo_tutor\task\post_notice
 */
final class conversation_deletion_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        set_config('api_key', 'phpunit-api-key', 'local_dixeo');
    }

    protected function tearDown(): void {
        service_factory::reset();
        parent::tearDown();
    }

    /**
     * Course with a student who accepted the Terms of Use, set as the current user.
     *
     * @return array{0: \stdClass, 1: \stdClass}
     */
    private function course_and_student(): array {
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        (new consent_service())->accept((int) $student->id);
        $this->setUser($student);
        return [$course, $student];
    }

    public function test_deletion_drops_queued_guide_prompts_and_notices(): void {
        global $DB;

        [$course, $student] = $this->course_and_student();
        $userid = (int) $student->id;
        $courseid = (int) $course->id;
        $proactive = new tutor_proactive_context_service();
        $proactive->queue_guide_started($userid, $courseid, 'Help me with fractions');
        post_notice::queue($userid, $courseid, ['type' => 'course_completed', 'time' => time()]);

        $service = $this->createMock(tutor_service::class);
        $service->expects($this->once())->method('delete_conversations')->with($courseid, $userid)->willReturn(1);
        $service->expects($this->never())->method('submit');
        $service->expects($this->never())->method('add_message');
        service_factory::set_test_tutor_service($service);

        delete_conversation::execute($courseid);

        $this->assertFalse($DB->record_exists(tutor_proactive_context_service::TABLE, [
            'userid' => $userid,
            'courseid' => $courseid,
        ]));
        $this->assertGreaterThan(0, tutor_erasure_marker::erased_at($userid, $courseid));

        $this->assertNull($proactive->flush($userid, $courseid));
        foreach (\core\task\manager::get_adhoc_tasks(post_notice::class) as $task) {
            $task->execute();
        }
    }

    public function test_flush_drops_a_prompt_queued_before_the_deletion(): void {
        global $DB;

        [$course, $student] = $this->course_and_student();
        $userid = (int) $student->id;
        $courseid = (int) $course->id;
        $proactive = new tutor_proactive_context_service();
        // A prompt that reached the queue while the deletion was running.
        tutor_erasure_marker::mark($userid, $courseid);
        $proactive->queue_guide_started($userid, $courseid, 'Help me with fractions');

        $service = $this->createMock(tutor_service::class);
        $service->expects($this->never())->method('submit');
        service_factory::set_test_tutor_service($service);

        $this->assertNull($proactive->flush($userid, $courseid));
        $this->assertSame('', (string) $DB->get_field(tutor_proactive_context_service::TABLE, 'message', [
            'userid' => $userid,
            'courseid' => $courseid,
        ]));
    }

    public function test_notice_queued_after_the_deletion_is_still_sent(): void {
        [$course, $student] = $this->course_and_student();
        $userid = (int) $student->id;
        $courseid = (int) $course->id;
        set_user_preference(tutor_erasure_marker::PREF_ERASED_AT_PREFIX . $courseid, time() - 60, $userid);
        post_notice::queue($userid, $courseid, ['type' => 'course_completed', 'time' => time()]);

        $service = $this->createMock(tutor_service::class);
        $service->expects($this->once())->method('add_message');
        service_factory::set_test_tutor_service($service);

        $tasks = \core\task\manager::get_adhoc_tasks(post_notice::class);
        $this->assertCount(1, $tasks);
        reset($tasks)->execute();
    }
}
