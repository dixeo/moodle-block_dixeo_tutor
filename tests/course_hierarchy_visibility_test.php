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
 * Tests for course hierarchy visibility filtering (DIXEO-TUTOR-SEC-008).
 *
 * @package    block_dixeo_tutor
 * @category   test
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_dixeo_tutor;

use advanced_testcase;
use core_courseformat\formatactions;

/**
 * The practice quiz hierarchy must only expose content the calling user may access.
 *
 * @covers \block_dixeo_tutor::build_practice_quiz_hierarchy
 */
final class course_hierarchy_visibility_test extends advanced_testcase {
    /** @var \stdClass Course holding the visible, hidden and restricted activities. */
    private \stdClass $course;

    /** @var array<string, int> Activity name => cmid. */
    private array $cmids = [];

    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        $this->resetAfterTest();

        // Block classes are not autoloaded.
        require_once($CFG->dirroot . '/blocks/moodleblock.class.php');
        require_once($CFG->dirroot . '/blocks/dixeo_tutor/block_dixeo_tutor.php');

        $CFG->enableavailability = 1;
        $this->setup_course_content();
    }

    /**
     * Build a course with a visible, a hidden and a date-restricted activity in section 1,
     * plus an activity sitting in a hidden section 2.
     */
    private function setup_course_content(): void {
        $generator = $this->getDataGenerator();
        $this->course = $generator->create_course();

        $futuredate = json_encode([
            'op' => '&',
            'c' => [['type' => 'date', 'd' => '>=', 't' => time() + DAYSECS]],
            'showc' => [true],
        ]);

        $modules = [
            'Visible page' => ['section' => 1],
            'Hidden page' => ['section' => 1, 'visible' => 0],
            'Restricted page' => ['section' => 1, 'availability' => $futuredate],
            'Page in hidden section' => ['section' => 2],
        ];
        foreach ($modules as $name => $options) {
            $module = $generator->create_module('page', ['course' => $this->course->id, 'name' => $name] + $options);
            $this->cmids[$name] = (int) $module->cmid;
        }

        $sectiontwo = get_fast_modinfo($this->course->id)->get_section_info(2);
        formatactions::section($this->course->id)->set_visibility($sectiontwo, false);
        rebuild_course_cache($this->course->id, true);
    }

    /**
     * Build the hierarchy as the given user.
     *
     * @param \stdClass $user
     * @return array{course: array, sections: array}
     */
    private function build_as(\stdClass $user): array {
        $this->setUser($user);
        return \block_dixeo_tutor::build_practice_quiz_hierarchy((int) $this->course->id, new \moodle_page());
    }

    /**
     * Section numbers exposed by the hierarchy.
     *
     * @param array $hierarchy
     * @return int[]
     */
    private function section_numbers(array $hierarchy): array {
        return array_map(static fn($section) => (int) $section['num'], $hierarchy['sections']);
    }

    /**
     * Activity names exposed by the hierarchy, sorted for comparison.
     *
     * @param array $hierarchy
     * @return string[]
     */
    private function activity_names(array $hierarchy): array {
        $names = [];
        foreach ($hierarchy['sections'] as $section) {
            foreach ($section['activities'] as $activity) {
                $names[] = (string) $activity['name'];
            }
        }
        sort($names);
        return $names;
    }

    /**
     * Course module ids exposed by the hierarchy.
     *
     * @param array $hierarchy
     * @return int[]
     */
    private function activity_cmids(array $hierarchy): array {
        $cmids = [];
        foreach ($hierarchy['sections'] as $section) {
            foreach ($section['activities'] as $activity) {
                $cmids[] = (int) $activity['cmid'];
            }
        }
        sort($cmids);
        return $cmids;
    }

    public function test_student_only_sees_accessible_activities(): void {
        $student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');

        $hierarchy = $this->build_as($student);

        $this->assertSame([1], $this->section_numbers($hierarchy));
        $this->assertSame(['Visible page'], $this->activity_names($hierarchy));
        $this->assertSame([$this->cmids['Visible page']], $this->activity_cmids($hierarchy));
    }

    public function test_teacher_sees_hidden_and_restricted_activities(): void {
        $teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');

        $hierarchy = $this->build_as($teacher);

        $this->assertSame([1, 2], $this->section_numbers($hierarchy));
        $this->assertSame(
            ['Hidden page', 'Page in hidden section', 'Restricted page', 'Visible page'],
            $this->activity_names($hierarchy)
        );
    }
}
