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
 * Inline citation chips for tutor answers.
 *
 * The API marks each citation with a {{cite:N}} placeholder, N indexing the message
 * sources from 1. Chips are built here, after format_text, from the sources local_dixeo
 * resolved for the current user, so their markup never goes through the filters.
 *
 * @package    block_dixeo_tutor
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @author     Pierre FACQ <pierre.facq@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_dixeo_tutor\service;

use html_writer;

/**
 * Replaces citation placeholders with activity chips, or strips them.
 */
class tutor_citation_renderer {
    /** @var string A run of consecutive placeholders, with the whitespace before each, then any closing punctuation. */
    private const RUN_PATTERN = '/(?:\s*\{\{cite:\d+\}\})+([.,;:!?…)»]*)/u';

    /** @var string One placeholder, capturing its 1-based source index. */
    private const PLACEHOLDER_PATTERN = '/\{\{cite:(\d+)\}\}/';

    /**
     * Remove every citation placeholder from a text.
     *
     * @param string $text Message content.
     * @return string
     */
    public static function strip(string $text): string {
        return preg_replace(self::RUN_PATTERN, '$1', $text);
    }

    /**
     * Replace citation placeholders with chips linking to the cited activities.
     *
     * Placeholders whose source did not resolve are removed; a run of placeholders
     * shows each distinct chip once. The last chip keeps the punctuation that follows
     * it on the same line.
     *
     * @param string $html Formatted message HTML.
     * @param array $sources Resolved sources in API order, null where unresolved.
     * @return string
     */
    public static function render(string $html, array $sources): string {
        $folders = self::cms_with_several_files($sources);

        return preg_replace_callback(self::RUN_PATTERN, function (array $run) use ($sources, $folders): string {
            preg_match_all(self::PLACEHOLDER_PATTERN, $run[0], $matches);
            $chips = [];
            foreach ($matches[1] as $index) {
                $source = $sources[(int) $index - 1] ?? null;
                if (is_array($source)) {
                    $chips[] = self::chip($source, isset($folders[$source['cmid']]));
                }
            }

            $punctuation = $run[1];
            if ($chips === []) {
                return $punctuation;
            }

            $chips = array_values(array_unique($chips));
            $last = array_pop($chips) . $punctuation;
            if ($punctuation !== '') {
                $last = html_writer::span($last, 'text-nowrap');
            }

            return ' ' . implode(' ', [...$chips, $last]);
        }, $html);
    }

    /**
     * Course modules cited with more than one distinct file, keyed by cmid.
     *
     * @param array $sources Resolved sources.
     * @return array
     */
    private static function cms_with_several_files(array $sources): array {
        $files = [];
        foreach ($sources as $source) {
            if (is_array($source) && $source['file'] !== null) {
                $files[$source['cmid']][$source['file']] = true;
            }
        }

        return array_filter($files, fn(array $cmfiles): bool => count($cmfiles) > 1);
    }

    /**
     * Chip for one cited source: activity icon, name, then file (folders only) and pages.
     *
     * @param array $source Resolved source.
     * @param bool $showfile Whether to name the file.
     * @return string
     */
    private static function chip(array $source, bool $showfile): string {
        $details = [];
        if ($showfile && $source['file'] !== null) {
            $details[] = $source['file'];
        }
        if ($source['pages'] !== []) {
            $details[] = get_string('sources_pages', 'block_dixeo_tutor', implode(', ', $source['pages']));
        }
        $detail = implode(', ', $details);

        $content = html_writer::empty_tag('img', ['src' => $source['iconurl'], 'alt' => '', 'class' => 'dixeo-tutor-cite__icon'])
            . html_writer::span(s($source['name']), 'dixeo-tutor-cite__name')
            . ($detail !== '' ? html_writer::span(s($detail), 'dixeo-tutor-cite__detail') : '')
            . html_writer::span(' ' . s(get_string('opensinnewwindowbracketed')), 'sr-only');

        return html_writer::link($source['url'], $content, [
            'class' => 'dixeo-tutor-cite',
            'target' => '_blank',
            'rel' => 'noopener',
            'title' => implode(', ', array_filter([$source['name'], $detail])),
        ]);
    }
}
