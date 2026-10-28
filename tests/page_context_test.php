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
 * Tests for {@see \block_dixeo_tutor\page_context}.
 *
 * @package    block_dixeo_tutor
 * @category   test
 * @copyright  2026 Edunao SAS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_dixeo_tutor;

/**
 * Tests for the page URL sent to the tutor.
 *
 * @covers \block_dixeo_tutor\page_context
 */
final class page_context_test extends \advanced_testcase {
    /**
     * Page URLs as the client sends them, and what reaches the API.
     *
     * @return array
     */
    public static function pageurl_provider(): array {
        return [
            'activity keeps its id' => ['/mod/page/view.php?id=57', '/mod/page/view.php?id=57'],
            'section keeps its id' => ['/course/section.php?id=12#top', '/course/section.php?id=12'],
            'other parameters are dropped' => ['/mod/forum/discuss.php?d=3&id=8&parent=9', '/mod/forum/discuss.php?id=8'],
            'no id' => ['/mod/forum/discuss.php?d=3', '/mod/forum/discuss.php'],
            'non-numeric id' => ['/mod/page/view.php?id=abc', '/mod/page/view.php'],
            'array id' => ['/mod/page/view.php?id[]=5', '/mod/page/view.php'],
            'empty' => ['', null],
            'other site' => ['https://evil.example/mod/page/view.php?id=57', null],
        ];
    }

    /**
     * Only site URLs pass, with at most their numeric id; anything else falls back to the course.
     *
     * @param string $pageurl Client-supplied URL, relative to wwwroot unless absolute.
     * @param string|null $expected Expected path relative to wwwroot, null for the course fallback.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('pageurl_provider')]
    public function test_sanitize_pageurl(string $pageurl, ?string $expected): void {
        global $CFG;

        $courseid = 42;
        if ($pageurl !== '' && $pageurl[0] === '/') {
            $pageurl = $CFG->wwwroot . $pageurl;
        }
        $expected = $expected === null
            ? (new \moodle_url('/course/view.php', ['id' => $courseid]))->out(false)
            : $CFG->wwwroot . $expected;

        $this->assertSame($expected, page_context::sanitize_pageurl($pageurl, $courseid));
    }
}
