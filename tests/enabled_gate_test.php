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
 * Tutor block visibility when Dixeo is off.
 *
 * @package    block_dixeo_tutor
 * @category   test
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_dixeo_tutor;

/**
 * The tutor block is empty when Dixeo is off for its course.
 *
 * @package    block_dixeo_tutor
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_dixeo_tutor::get_content
 * @covers     \block_dixeo_tutor::is_tutor_available_on_page
 */
final class enabled_gate_test extends \advanced_testcase {
    /**
     * Course off empties the tutor. Dixeo off does too, even when the course stores On.
     */
    public function test_tutor_is_empty_when_dixeo_is_off(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $block = $this->load_block($course);
        $config = \local_dixeo\external\service_factory::get_settings_config();
        $coursecontext = \context_course::instance($course->id);

        $this->setUser($student);
        $shown = $block->get_content();
        $this->assertNotSame('', $shown->text);
        $this->assertTrue(\block_dixeo_tutor::is_tutor_available_on_page($block->page, (int) $student->id));

        $this->setAdminUser();
        $config->set('local_dixeo', 'enabled', $coursecontext, '0');
        $this->setUser($student);
        $block->content = null;
        $hidden = $block->get_content();
        $this->assertSame('', $hidden->text);
        $this->assertSame('', $hidden->footer);
        $this->assertFalse(\block_dixeo_tutor::is_tutor_available_on_page($block->page, (int) $student->id));

        $this->setAdminUser();
        $this->assertSame(get_string('disabled_block_course', 'local_dixeo'), $this->editing_text($block));

        $config->set('local_dixeo', 'enabled', \context_system::instance(), '0');
        $config->set('local_dixeo', 'enabled', $coursecontext, '1');
        $this->setUser($student);
        $block->content = null;
        $forcedoff = $block->get_content();
        $this->assertSame('', $forcedoff->text);
        $this->assertFalse(\block_dixeo_tutor::is_tutor_available_on_page($block->page, (int) $student->id));
        $this->setAdminUser();
        $this->assertSame(get_string('disabled_block_site', 'local_dixeo'), $this->editing_text($block));
    }

    /**
     * A course can turn the tutor off while Dixeo stays on.
     * Editing mode explains that. Outside editing mode the block stays empty.
     */
    public function test_tutor_disabled_for_the_course_hides_the_block(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $block = $this->load_block($course);
        $context = \context_course::instance($course->id);
        $config = \local_dixeo\external\service_factory::get_settings_config();
        $config->set('block_dixeo_tutor', 'tutor_enabled', $context, '0');

        $this->setUser($student);
        $block->content = null;
        $hidden = $block->get_content();
        $this->assertSame('', $hidden->text);
        $this->assertFalse(\block_dixeo_tutor::is_tutor_available_on_page($block->page, (int) $student->id));

        $this->setAdminUser();
        $this->assertSame(get_string('disabled_tutor_course', 'block_dixeo_tutor'), $this->editing_text($block));

        $config->set('block_dixeo_tutor', 'tutor_enabled', $context, '1');
        $this->setAdminUser();
        $block->content = null;
        $teacher = $block->get_content();
        $this->assertStringContainsString('data-initial="feature:tutor"', $teacher->text);
        $this->assertStringNotContainsString('data-preferencesonly="1"', $teacher->text);

        $this->setUser($student);
        $block->content = null;
        $studentview = $block->get_content();
        $this->assertStringContainsString('data-preferencesonly="1"', $studentview->text);
        $this->assertStringContainsString('data-initial="preferences"', $studentview->text);
        $this->assertStringNotContainsString('local_dixeo_get_settings', $studentview->text);
        $this->assertTrue(\block_dixeo_tutor::is_tutor_available_on_page($block->page, (int) $student->id));
    }

    /**
     * Block text while the current user is editing the page.
     *
     * @param \block_base $block
     * @return string
     */
    private function editing_text(\block_base $block): string {
        global $USER;
        $USER->editing = 1;
        $block->content = null;
        $text = (string) $block->get_content()->text;
        $USER->editing = 0;
        $block->content = null;
        return $text;
    }

    /**
     * Load the tutor block on a course page.
     *
     * @param \stdClass $course
     * @return \block_dixeo_tutor
     */
    private function load_block(\stdClass $course): \block_dixeo_tutor {
        global $PAGE;

        $context = \context_course::instance($course->id);
        $this->getDataGenerator()->create_block('dixeo_tutor', [
            'parentcontextid' => $context->id,
            'showinsubcontexts' => 1,
            'pagetypepattern' => '*',
        ]);

        $page = $this->course_page($course, $context);
        $page->blocks->load_blocks();
        $PAGE = $page;
        $this->use_page_output($page);
        return $this->block_on_page($page);
    }

    /**
     * @param \stdClass $course
     * @param \context_course $context
     * @return \moodle_page
     */
    private function course_page(\stdClass $course, \context_course $context): \moodle_page {
        $page = new \moodle_page();
        $page->set_context($context);
        $page->set_course($course);
        $page->set_pagelayout('course');
        $page->set_pagetype('course-view-topics');
        $page->set_url(new \moodle_url('/course/view.php', ['id' => $course->id]));
        return $page;
    }

    /**
     * Point global output at the page renderer.
     *
     * @param \moodle_page $page
     * @return void
     */
    private function use_page_output(\moodle_page $page): void {
        global $OUTPUT, $PAGE;
        $PAGE = $page;
        $page->initialise_theme_and_output();
        $OUTPUT = $page->get_renderer('core');
    }

    /**
     * @param \moodle_page $page
     * @return \block_dixeo_tutor
     */
    private function block_on_page(\moodle_page $page): \block_dixeo_tutor {
        foreach ($page->blocks->get_regions() as $region) {
            foreach ($page->blocks->get_blocks_for_region($region) as $block) {
                if ($block instanceof \block_dixeo_tutor) {
                    return $block;
                }
            }
        }
        $this->fail('Tutor block was not loaded on the course page');
    }
}
