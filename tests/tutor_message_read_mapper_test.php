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
 * Tests for {@see \block_dixeo_tutor\service\tutor_message_read_mapper}.
 *
 * @package    block_dixeo_tutor
 * @category   test
 * @copyright  2026 Edunao SAS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_dixeo_tutor;

use block_dixeo_tutor\service\tutor_context_schema;
use block_dixeo_tutor\service\tutor_message_read_mapper;

/**
 * Tests for tutor message read mapper.
 *
 * @covers \block_dixeo_tutor\service\tutor_message_read_mapper
 */
final class tutor_message_read_mapper_test extends \advanced_testcase {
    public function test_normalize_messages_adds_schema_to_legacy_body_context(): void {
        $messages = tutor_message_read_mapper::normalize_messages([
            [
                'role' => 'system',
                'context' => ['body' => 'Legacy proactive line'],
            ],
        ]);

        $this->assertSame(tutor_context_schema::SCHEMA_PROACTIVE, $messages[0]['context']['schema']);
        $this->assertSame('Legacy proactive line', $messages[0]['context']['body']);
    }

    public function test_normalize_messages_adds_schema_to_legacy_url_context(): void {
        $messages = tutor_message_read_mapper::normalize_messages([
            [
                'role' => 'user',
                'context' => ['url' => 'https://example.test/page'],
            ],
        ]);

        $this->assertSame(tutor_context_schema::SCHEMA_PAGE, $messages[0]['context']['schema']);
        $this->assertSame('https://example.test/page', $messages[0]['context']['url']);
    }

    public function test_schema_from_message_reads_context_schema(): void {
        $schema = tutor_message_read_mapper::schema_from_message([
            'role' => 'system',
            'context' => ['schema' => tutor_context_schema::SCHEMA_PRACTICE_QUIZ_REVIEW],
        ]);
        $this->assertSame(tutor_context_schema::SCHEMA_PRACTICE_QUIZ_REVIEW, $schema);
    }
}
