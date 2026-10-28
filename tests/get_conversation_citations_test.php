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
 * Tests for the inline citations returned with tutor messages.
 *
 * @package    block_dixeo_tutor
 * @category   test
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @author     Pierre FACQ <pierre.facq@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_dixeo_tutor;

use block_dixeo_tutor\external\get_conversation;
use core_external\external_api;
use local_dixeo\external\service_factory;
use local_dixeo\service\tutor_service;

/**
 * Citation placeholders reach the UI as chips, never as raw text.
 *
 * @covers \block_dixeo_tutor\external\get_conversation
 * @covers \block_dixeo_tutor\service\tutor_message_format_service
 */
final class get_conversation_citations_test extends \advanced_testcase {
    protected function tearDown(): void {
        service_factory::reset();
        parent::tearDown();
    }

    public function test_placeholders_become_chips_and_leave_the_raw_content(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'student'));

        $source = [
            'cmid' => 4,
            'name' => 'Cours PDF',
            'url' => 'https://example.test/mod/resource/view.php?id=4',
            'iconurl' => 'https://example.test/icon.svg',
            'file' => 'cours.pdf',
            'pages' => [3],
        ];
        $tutorservice = $this->createMock(tutor_service::class);
        $tutorservice->method('get_conversation')->willReturn([
            ['id' => 'm1', 'role' => 'user', 'content' => 'Where {{cite:1}}?', 'time' => 1],
            ['id' => 'm2', 'role' => 'assistant', 'content' => 'In chloroplasts {{cite:1}}.', 'time' => 2, 'sources' => [$source]],
        ]);
        service_factory::set_test_tutor_service($tutorservice);

        $result = external_api::clean_returnvalue(
            get_conversation::execute_returns(),
            get_conversation::execute((int) $course->id)
        );

        $this->assertSame('Where {{cite:1}}?', $result['messages'][0]['content']);
        $this->assertSame('In chloroplasts.', $result['messages'][1]['content']);
        $this->assertStringContainsString('class="dixeo-tutor-cite"', $result['messages'][1]['contenthtml']);
        $this->assertStringNotContainsString('{{cite:', $result['messages'][1]['contenthtml']);
    }
}
