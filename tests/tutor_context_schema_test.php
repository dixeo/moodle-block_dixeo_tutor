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
 * Tests for {@see \block_dixeo_tutor\service\tutor_context_schema}.
 *
 * @package    block_dixeo_tutor
 * @category   test
 * @copyright  2026 Edunao SAS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_dixeo_tutor;

use block_dixeo_tutor\service\tutor_context_schema;

/**
 * Tests for tutor context schema helpers.
 *
 * @covers \block_dixeo_tutor\service\tutor_context_schema
 */
final class tutor_context_schema_test extends \advanced_testcase {
    public function test_proactive_context_shape(): void {
        $events = [
            ['type' => 'first_visit', 'time' => 1234567890, 'name' => 'Alice'],
        ];
        $context = tutor_context_schema::proactive_context($events, 5, 10, 1234567890);
        $this->assertSame(tutor_context_schema::SCHEMA_PROACTIVE, $context['schema']);
        $this->assertSame(1, $context['version']);
        $this->assertSame($events, $context['events']);
        $this->assertSame(5, $context['userid']);
        $this->assertSame(10, $context['courseid']);
        $this->assertSame(1234567890, $context['time']);
    }

    public function test_page_context_shape(): void {
        $context = tutor_context_schema::page_context('https://example.test/page');
        $this->assertSame(tutor_context_schema::SCHEMA_PAGE, $context['schema']);
        $this->assertSame('https://example.test/page', $context['url']);
    }
}
