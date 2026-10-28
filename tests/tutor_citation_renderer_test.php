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
 * Tests for the inline citation chips of tutor answers.
 *
 * @package    block_dixeo_tutor
 * @category   test
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @author     Pierre FACQ <pierre.facq@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_dixeo_tutor;

use block_dixeo_tutor\service\tutor_citation_renderer;

/**
 * Tests for tutor_citation_renderer.
 *
 * @covers \block_dixeo_tutor\service\tutor_citation_renderer
 */
final class tutor_citation_renderer_test extends \advanced_testcase {
    public function test_placeholder_becomes_a_chip_with_icon_name_and_pages(): void {
        $html = tutor_citation_renderer::render('<p>Chloroplasts {{cite:1}}.</p>', [
            $this->source(4, 'Cours & bio', 'cours.pdf', [1, 3]),
        ]);

        $this->assertMatchesRegularExpression(
            '#^<p>Chloroplasts <span class="text-nowrap"><a [^>]*class="dixeo-tutor-cite"[^>]*>.*</a>\.</span></p>$#',
            $html
        );
        $this->assertStringContainsString('href="https://example.test/mod/resource/view.php?id=4"', $html);
        $this->assertStringContainsString('target="_blank"', $html);
        $this->assertStringContainsString('rel="noopener"', $html);
        $this->assertStringContainsString('<img src="https://example.test/icon4.svg" alt=""', $html);
        $this->assertStringContainsString('>Cours &amp; bio</span>', $html);
        $this->assertStringContainsString('>p. 1, 3</span>', $html);
        $this->assertStringNotContainsString('cours.pdf', $html);
    }

    public function test_folder_chips_name_their_file(): void {
        $html = tutor_citation_renderer::render('A {{cite:1}} B {{cite:2}}', [
            $this->source(7, 'Folder', 'a.pdf', [3]),
            $this->source(7, 'Folder', 'b.pdf', []),
        ]);

        $this->assertStringContainsString('>a.pdf, p. 3</span>', $html);
        $this->assertStringContainsString('>b.pdf</span>', $html);
    }

    public function test_consecutive_placeholders_of_the_same_chip_collapse(): void {
        $sources = [
            $this->source(4, 'Cours', 'cours.pdf', [2]),
            $this->source(4, 'Cours', 'cours.pdf', [2]),
            $this->source(5, 'Quiz', null, []),
        ];

        $html = tutor_citation_renderer::render('Text {{cite:1}}{{cite:2}} {{cite:3}}.', $sources);

        $this->assertSame(2, substr_count($html, '<a '));
        $this->assertStringEndsWith('</a>.</span>', $html);
        $this->assertSame(1, substr_count($html, 'text-nowrap'));
    }

    public function test_chip_without_following_punctuation_is_not_wrapped(): void {
        $html = tutor_citation_renderer::render('A {{cite:1}} then B', [
            $this->source(4, 'Cours', null, []),
        ]);

        $this->assertStringNotContainsString('text-nowrap', $html);
        $this->assertStringEndsWith('</a> then B', $html);
    }

    public function test_unresolved_placeholders_are_removed(): void {
        $html = tutor_citation_renderer::render('Text {{cite:1}} {{cite:9}}.', [null]);

        $this->assertSame('Text.', $html);
    }

    public function test_placeholders_outside_the_sources_are_removed(): void {
        $html = tutor_citation_renderer::render('A {{cite:0}}. B {{cite:3}}. C {{cite:1}}.', [
            $this->source(4, 'Cours', null, []),
        ]);

        $this->assertStringStartsWith('A. B. C <span class="text-nowrap"><a ', $html);
        $this->assertSame(1, substr_count($html, '<a '));
    }

    public function test_strip_removes_every_placeholder(): void {
        $this->assertSame('In chloroplasts. Then sugars.', tutor_citation_renderer::strip(
            'In chloroplasts {{cite:1}}{{cite:2}}. Then sugars {{cite:3}}.'
        ));
    }

    /**
     * Resolved source as local_dixeo returns it.
     *
     * @param int $cmid
     * @param string $name
     * @param string|null $file
     * @param int[] $pages
     * @return array
     */
    private function source(int $cmid, string $name, ?string $file, array $pages): array {
        return [
            'cmid' => $cmid,
            'name' => $name,
            'url' => "https://example.test/mod/resource/view.php?id={$cmid}",
            'iconurl' => "https://example.test/icon{$cmid}.svg",
            'file' => $file,
            'pages' => $pages,
        ];
    }
}
