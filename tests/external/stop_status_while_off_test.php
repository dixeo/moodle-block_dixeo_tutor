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
 * Tutor job cancel and status while the tutor cannot be used.
 *
 * @package    block_dixeo_tutor
 * @category   test
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_dixeo_tutor\external;

use block_dixeo_tutor\job_ownership;
use local_dixeo\dto\job_status;
use local_dixeo\external\service_factory;
use local_dixeo\service\job_service;
use local_dixeo\service\tutor_service;

/**
 * The job owner cancels and reads status while Dixeo or the tutor is off, or without the terms.
 *
 * @package    block_dixeo_tutor
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_dixeo_tutor\external\cancel_generation_job
 * @covers     \block_dixeo_tutor\external\get_job_status
 * @covers     \block_dixeo_tutor\external\send_message
 */
final class stop_status_while_off_test extends \advanced_testcase {
    /** @var string Job id issued to the owner. */
    private const JOBID = '0b0f8a3c-1d2e-4f5a-9b6c-7d8e9f0a1b2c';

    /** @var \stdClass Course. */
    private \stdClass $course;

    /** @var \stdClass Learner who owns the job. */
    private \stdClass $owner;

    /** @var \stdClass Another learner of the course. */
    private \stdClass $other;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->course = $this->getDataGenerator()->create_course();
        $this->owner = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->other = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        job_ownership::register((int) $this->owner->id, (int) $this->course->id, self::JOBID);
    }

    protected function tearDown(): void {
        service_factory::reset();
        parent::tearDown();
    }

    /**
     * Ways the tutor can be unusable for the learner.
     *
     * @return array
     */
    public static function unusable_provider(): array {
        return [
            'dixeo off for the course' => ['local_dixeo', 'enabled', '0', true],
            'tutor off for the course' => ['block_dixeo_tutor', 'tutor_enabled', '0', true],
            'terms not accepted' => [null, null, null, false],
        ];
    }

    /**
     * The owner cancels and reads status; another learner is refused.
     *
     * @dataProvider unusable_provider
     * @param string|null $component Setting component, or null to change no setting.
     * @param string|null $name Setting name.
     * @param string|null $value Course value.
     * @param bool $accepted Whether the learners accepted the current terms.
     */
    public function test_owner_cancels_and_reads_status(?string $component, ?string $name, ?string $value, bool $accepted): void {
        $courseid = (int) $this->course->id;
        if ($component !== null) {
            $this->set_course_value($component, $name, $value);
        }
        if ($accepted) {
            $generator = $this->getDataGenerator()->get_plugin_generator('local_dixeo');
            $generator->create_terms_acceptance(['userid' => $this->owner->id]);
            $generator->create_terms_acceptance(['userid' => $this->other->id]);
        }
        $jobservice = $this->createMock(job_service::class);
        $jobservice->expects($this->once())->method('cancel_job')
            ->with(self::JOBID, $courseid, (int) $this->owner->id)
            ->willReturn(['status' => 'cancelled']);
        $jobservice->expects($this->once())->method('get_job_status')
            ->with(self::JOBID, $courseid, (int) $this->owner->id)
            ->willReturn(new job_status(
                jobid: self::JOBID,
                type: 'tutor_message',
                status: job_status::STATUS_PROCESSING,
                progress: 50,
                createdat: time()
            ));
        service_factory::set_test_job_service($jobservice);

        $this->setUser($this->other);
        $calls = [
            fn() => cancel_generation_job::execute($courseid, self::JOBID),
            fn() => get_job_status::execute($courseid, self::JOBID),
        ];
        foreach ($calls as $call) {
            try {
                $call();
                $this->fail('A job of another learner must not be reached.');
            } catch (\moodle_exception $e) {
                $this->assertSame('error_job_access', $e->errorcode);
            }
        }

        $this->setUser($this->owner);
        $this->assertTrue(cancel_generation_job::execute($courseid, self::JOBID)['success']);
        $this->assertSame(job_status::STATUS_PROCESSING, get_job_status::execute($courseid, self::JOBID)['status']);
    }

    /**
     * Sending a message still stops while Dixeo is off.
     */
    public function test_send_message_refuses_while_dixeo_is_off(): void {
        $this->set_course_value('local_dixeo', 'enabled', '0');
        $service = $this->createMock(tutor_service::class);
        $service->expects($this->never())->method($this->anything());
        service_factory::set_test_tutor_service($service);
        $this->setUser($this->owner);

        try {
            send_message::execute((int) $this->course->id, 'Hello');
            $this->fail('A message must not be sent while Dixeo is off.');
        } catch (\moodle_exception $e) {
            $this->assertSame('error:dixeo_disabled', $e->errorcode);
            $this->assertSame('local_dixeo', $e->module);
        }
    }

    /**
     * Store a course-level setting as the admin.
     *
     * @param string $component Setting component.
     * @param string $name Setting name.
     * @param string $value Stored value.
     */
    private function set_course_value(string $component, string $name, string $value): void {
        $this->setAdminUser();
        service_factory::get_settings_config()->set(
            $component,
            $name,
            \context_course::instance((int) $this->course->id),
            $value
        );
    }
}
