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
 * Block class definition for the Dixeo Student Tutor.
 *
 * @package    block_dixeo_tutor
 * @copyright  2025 Edunao SAS (contact@edunao.com)
 * @author     Pierre FACQ <pierre.facq@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Dixeo Student Tutor block class.
 */
class block_dixeo_tutor extends block_base {
    /**
     * Returns the list of module types where the tutor should not be displayed.
     * Reads from plugin settings, falling back to default values.
     *
     * @return array List of module names to exclude.
     */
    public static function get_excluded_modules(): array {
        $setting = get_config('block_dixeo_tutor', 'excludedmodules');
        if ($setting === false || $setting === '') {
            return ['quiz', 'simplequiz2'];
        }
        return array_map('trim', explode(',', $setting));
    }

    /**
     * Resolve activity module type on a module context page when $PAGE->cm is not set yet.
     *
     * @param \moodle_page $page
     * @return string|null Module frankenstyle name (e.g. quiz) or null if not resolvable.
     */
    public static function resolve_modname_for_module_page(\moodle_page $page): ?string {
        if ((int) $page->context->contextlevel !== CONTEXT_MODULE) {
            return null;
        }
        $cmid = (int) $page->context->instanceid;
        if ($cmid < 1) {
            return null;
        }
        if (!empty($page->cm) && (int) $page->cm->id === $cmid) {
            return $page->cm->modname;
        }
        if (empty($page->course->id) || (int) $page->course->id === SITEID) {
            return null;
        }
        try {
            $modinfo = get_fast_modinfo($page->course);
            $cm = $modinfo->get_cm($cmid);
            return $cm->modname;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Whether the given page is a module activity whose type is listed in excludedmodules.
     *
     * @param \moodle_page $page Page to evaluate (typically the global $PAGE).
     * @return bool True if the tutor should be hidden on this page.
     */
    public static function is_current_page_excluded(\moodle_page $page): bool {
        $modname = self::resolve_modname_for_module_page($page);
        if ($modname === null || $modname === '') {
            return false;
        }
        return in_array($modname, self::get_excluded_modules(), true);
    }

    /**
     * Whether the tutor UI is available on this page for the given user (same rules as {@see get_content()}).
     * Used to decide if queued proactive context may be flushed during the current request.
     * When false, messages stay queued until the tutor AMD module loads on a qualifying page.
     *
     * @param \moodle_page $page Page being rendered (typically global $PAGE).
     * @param int $userid User who will receive the proactive message.
     * @return bool
     */
    public static function is_tutor_available_on_page(\moodle_page $page, int $userid): bool {
        $courseid = (int) ($page->course->id ?? 0);
        if ($courseid <= SITEID || $userid <= 0) {
            return false;
        }

        try {
            $context = \context_course::instance($courseid);
        } catch (\Exception $e) {
            return false;
        }

        if (!has_capability('block/dixeo_tutor:talktotutor', $context, $userid)) {
            return false;
        }

        if (self::is_current_page_excluded($page)) {
            return false;
        }

        if ($page->user_is_editing()) {
            return false;
        }

        $page->blocks->load_blocks();
        return $page->blocks->is_block_present('dixeo_tutor');
    }

    /**
     * Initialize the block with its title and basic settings.
     *
     * @return void
     */
    public function init(): void {
        $this->title = get_string('pluginname', 'block_dixeo_tutor');
    }

    /**
     * Generate the content for this block.
     * Renders the tutor interface if the user has appropriate permissions
     * and is not on an excluded page type. Returns cached content if already generated.
     *
     * @return stdClass The block content object with text and footer properties
     */
    public function get_content(): \stdClass {
        global $USER, $OUTPUT;

        // Return cached content if already generated.
        if ($this->content !== null) {
            return $this->content;
        }

        // Initialize empty content structure.
        $this->content = (object)[];
        $this->content->text = '';
        $this->content->footer = '';
        $courseid = $this->page->course->id;

        // Check if user has permission to use the tutor.
        if (!has_capability('block/dixeo_tutor:talktotutor', \context_course::instance($courseid))) {
            $this->content->text = $OUTPUT->notification(get_string('notenrolled', 'block_dixeo_tutor'), 'info');
            return $this->content;
        }

        // Hide tutor on excluded module pages.
        if (self::is_current_page_excluded($this->page)) {
            return $this->content;
        }

        // Do not display the block if the user is in editing mode.
        if ($this->page->user_is_editing()) {
            $this->content->text = get_string('editingmode', 'block_dixeo_tutor');
            return $this->content;
        }

        // Note: Conversations are created automatically when user sends first message.
        // No need to pre-create anything with Response API architecture.

        // Render the tutor interface and initialize JavaScript.
        $this->content->text = $OUTPUT->render_from_template('block_dixeo_tutor/tutor', []);
        $displaymode = get_config('block_dixeo_tutor', 'displaymode');
        if ($displaymode === false) {
            $displaymode = 'popup';
        }
        // Theme Dixeo always presents the tutor in drawer mode.
        if ($this->page->theme->name === 'dixeo') {
            $displaymode = 'drawer';
        }
        $opentooltip = get_string('tooltip_open_tutor', 'block_dixeo_tutor');
        $hidetooltip = get_string('tooltip_hide_tutor', 'block_dixeo_tutor');
        $this->page->requires->js_call_amd('block_dixeo_tutor/tutor', 'init', [
            $courseid,
            $USER->id,
            $displaymode,
            $opentooltip,
            $hidetooltip,
        ]);
        return $this->content;
    }

    /**
     * Define where this block can be displayed.
     * Specifies that this block is only available in course contexts,
     * as it requires course content to function properly.
     *
     * @return array Array of applicable formats
     */
    public function applicable_formats(): array {
        return ['course' => true];
    }

    /**
     * Determine if multiple instances of this block are allowed.
     * Only one tutor instance per course is needed and recommended
     * to avoid confusion and resource duplication.
     *
     * @return bool False to prevent multiple instances
     */
    public function instance_allow_multiple(): bool {
        return false;
    }

    /**
     * Indicate that this block has a configuration page.
     *
     * @return bool True to enable the settings link in the admin panel.
     */
    public function has_config(): bool {
        return true;
    }

    /**
     * Determine if the block header should be hidden.
     * The tutor interface provides its own visual identity,
     * so the standard block header is not needed.
     *
     * @return bool True to hide the block header
     */
    public function hide_header(): bool {
        return !$this->page->user_is_editing();
    }

    /**
     * Handle the creation of a new block instance.
     * This method is called when the block is added to a course.
     * It configures the block settings (display on all pages, weight, etc.).
     *
     * @return bool True on success
     */
    public function instance_create(): bool {
        global $DB, $USER;

        // Update the block.
        $bi = $DB->get_record('block_instances', ['id' => $this->instance->id]);
        $bi->showinsubcontexts = 1;
        $bi->pagetypepattern = '*';
        $bi->defaultweight = 5;
        $DB->update_record('block_instances', $bi);

        $courseid = \local_dixeo\service\file_sync_policy::resolve_courseid_from_block_parent(
            (int) $this->instance->parentcontextid
        );
        if ($courseid !== null) {
            \local_dixeo\external\service_factory::get_file_sync_service()->opt_in_on_block_added(
                $courseid,
                (int) $USER->id
            );
        }

        return true;
    }
}
