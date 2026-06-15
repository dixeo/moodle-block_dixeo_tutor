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
 * Tests for {@see \block_dixeo_tutor\service\tutor_mode_service}.
 *
 * @package    block_dixeo_tutor
 * @category   test
 * @copyright  2026 Edunao SAS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_dixeo_tutor;

use block_dixeo_tutor\service\tutor_mode_service;
use local_dixeo\dto\tutor_message;

/**
 * Tests for tutor mode service.
 *
 * @covers \block_dixeo_tutor\service\tutor_mode_service
 */
final class tutor_mode_service_test extends \advanced_testcase {
    public function test_get_mode_defaults_to_normal(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();

        $service = new tutor_mode_service();
        $this->assertSame(tutor_message::MODE_NORMAL, $service->get_mode((int) $user->id, (int) $course->id));
    }

    public function test_set_and_get_mode(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();

        $service = new tutor_mode_service();
        $stored = $service->set_mode((int) $user->id, (int) $course->id, tutor_message::MODE_GUIDE);

        $this->assertSame(tutor_message::MODE_GUIDE, $stored);
        $this->assertSame(tutor_message::MODE_GUIDE, $service->get_mode((int) $user->id, (int) $course->id));
    }
}
