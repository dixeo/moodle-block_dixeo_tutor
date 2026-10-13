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
    public static function get_excluded_modules(?\context $context = null): array {
        if ($context === null) {
            $context = \context_system::instance();
        }
        $setting = \local_dixeo\external\service_factory::get_settings_config()
            ->get('block_dixeo_tutor', 'excludedmodules', $context)
            ->get_value();
        if ($setting === '') {
            return ['quiz', 'simplequiz2'];
        }
        return array_values(array_filter(array_map('trim', explode(',', $setting)), static function(string $part): bool {
            return $part !== '';
        }));
    }

    /**
     * Whether an activity module type is omitted from the practice-quiz topic dropdown.
     * Uses the block excludedmodules setting plus hardcoded types and certificate plugins.
     *
     * @param string $modname Activity frankenstyle name (e.g. quiz, h5pactivity).
     * @return bool
     */
    public static function is_quiz_topic_excluded(string $modname, ?\context $context = null): bool {
        if ($modname === '') {
            return false;
        }
        if (str_contains($modname, 'certificate')) {
            return true;
        }
        // Hardcoded exclusions.
        if (in_array($modname, ['h5pactivity'], true)) {
            return true;
        }
        return in_array($modname, self::get_excluded_modules($context), true);
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
        $context = null;
        $courseid = (int) ($page->course->id ?? 0);
        if ($courseid > SITEID) {
            $context = \context_course::instance($courseid);
        }
        return in_array($modname, self::get_excluded_modules($context), true);
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

        $config = \local_dixeo\external\service_factory::get_settings_config();
        if (!$config->enabled_for($context)) {
            return false;
        }

        if ($config->get('block_dixeo_tutor', 'tutor_enabled', $context)->get_value() !== '1') {
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
     * Whether mod_simplequiz2 is installed and compatible (practice quiz UI dependency).
     *
     * @return bool
     */
    public static function is_simplequiz2_available(): bool {
        return \block_dixeo_tutor\service\tutor_mode_policy::is_quiz_runtime_available();
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

        // Dixeo off leaves the block empty, except in editing mode, where it explains why.
        if (!$this->dixeo_is_enabled()) {
            if ($this->page->user_is_editing()) {
                $this->content->text = $this->disabled_block_text();
            }
            return $this->content;
        }

        // A course can turn the tutor off on its own. Editing mode says so; otherwise the block stays empty.
        if ($this->dixeo_page_context()->contextlevel === CONTEXT_COURSE && !$this->tutor_is_enabled()) {
            if ($this->page->user_is_editing()) {
                $this->content->text = get_string('disabled_tutor_course', 'block_dixeo_tutor');
            }
            return $this->content;
        }

        $courseid = $this->page->course->id;

        // Users without permission to use the tutor get no block at all.
        if (!has_capability('block/dixeo_tutor:talktotutor', \context_course::instance($courseid))) {
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
        $currentcmid = 0;
        if ($this->page->context->contextlevel == CONTEXT_MODULE) {
            $currentcmid = (int) $this->page->context->instanceid;
        }

        $simplequiz2available = self::is_simplequiz2_available();
        $quizmodeavailable = \block_dixeo_tutor\service\tutor_mode_policy::is_mode_available(
            \local_dixeo\dto\tutor_message::MODE_QUIZ,
            $simplequiz2available,
            $courseid
        );
        $guidemodeavailable = \block_dixeo_tutor\service\tutor_mode_policy::is_mode_available(
            \local_dixeo\dto\tutor_message::MODE_GUIDE,
            $simplequiz2available,
            $courseid
        );
        $teachmodeavailable = \block_dixeo_tutor\service\tutor_mode_policy::is_mode_available(
            \local_dixeo\dto\tutor_message::MODE_TEACH,
            $simplequiz2available,
            $courseid
        );

        $modeservice = new \block_dixeo_tutor\service\tutor_mode_service();
        $selectedmode = $modeservice->get_mode((int) $USER->id, $courseid);
        $modeselector = \block_dixeo_tutor\service\tutor_mode_helper::export_mode_selector(
            $OUTPUT,
            $selectedmode,
            $simplequiz2available,
            $courseid
        );

        $langoptions = \local_dixeo\service\generation_language_helper::build_options((int) $USER->id);

        $coursecontext = \context_course::instance($courseid);
        $templatecontext = [
            'courseid' => $courseid,
            'contextid' => $coursecontext->id,
            'canupdate' => has_capability('moodle/course:update', $coursecontext),
            'canmanagesync' => has_capability('local/dixeo:syncfiles', $coursecontext) ? '1' : '0',
            'coursename' => format_string($this->page->course->fullname, true, ['context' => $coursecontext]),
            'currentcmid' => $currentcmid,
            'generationlanguagesjson' => json_encode($langoptions['languages']),
            'defaultgenerationlanguage' => $langoptions['defaultlanguage'],
            'quizmodeavailable' => $quizmodeavailable,
            'teachmodeavailable' => $teachmodeavailable,
        ];
        if ($modeselector !== null) {
            $templatecontext['mode_selector'] = $modeselector;
        }

        $this->content->text = $OUTPUT->render_from_template('block_dixeo_tutor/tutor', $templatecontext);
        if ($simplequiz2available && $quizmodeavailable) {
            $this->page->requires->css('/mod/simplequiz2/styles.css');
        }
        $displaymode = \local_dixeo\external\service_factory::get_settings_config()
            ->get('block_dixeo_tutor', 'displaymode', $coursecontext)
            ->get_value();
        if ($displaymode !== 'drawer' && $displaymode !== 'popup') {
            $displaymode = 'popup';
        }
        // Theme Dixeo always presents the tutor in drawer mode.
        if ($this->page->theme->name === 'dixeo') {
            $displaymode = 'drawer';
        }
        $opentooltip = get_string('tooltip_open_tutor', 'block_dixeo_tutor');
        $hidetooltip = get_string('tooltip_hide_tutor', 'block_dixeo_tutor');
        $readservice = new \block_dixeo_tutor\service\tutor_read_state_service();
        $this->page->requires->js_call_amd('local_dixeo/settings_modal', 'init');
        $this->page->requires->js_call_amd('block_dixeo_tutor/tutor', 'init', [
            $courseid,
            $USER->id,
            $displaymode,
            $opentooltip,
            $hidetooltip,
            $readservice->get_last_read((int) $USER->id, $courseid),
            $quizmodeavailable,
            $guidemodeavailable,
            $teachmodeavailable,
            $modeservice->get_last_activity((int) $USER->id, $courseid),
        ]);
        return $this->content;
    }

    /**
     * Whether Dixeo features run on this block's page.
     *
     * A page inside a course uses that course. Any other page uses the Dixeo layer.
     *
     * @return bool
     */
    private function dixeo_is_enabled(): bool {
        return \local_dixeo\external\service_factory::get_settings_config()->enabled_for($this->dixeo_page_context());
    }

    /**
     * Whether this course has the tutor turned on.
     *
     * @return bool
     */
    private function tutor_is_enabled(): bool {
        return \local_dixeo\external\service_factory::get_settings_config()
            ->get('block_dixeo_tutor', 'tutor_enabled', $this->dixeo_page_context())
            ->get_value() === '1';
    }

    /**
     * Editing-mode explanation for why this block is hidden.
     *
     * @return string
     */
    private function disabled_block_text(): string {
        return \local_dixeo\external\service_factory::get_settings_config()->disabled_block_text($this->dixeo_page_context());
    }

    /**
     * Course context for a page inside a course, otherwise the Dixeo layer.
     *
     * @return \context
     */
    private function dixeo_page_context(): \context {
        $courseid = (int) ($this->page->course->id ?? 0);
        return $courseid > SITEID
            ? \context_course::instance($courseid)
            : \context_system::instance();
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
     * Build course / section / activity hierarchy for practice quiz setup.
     * Only sections and activities the given user may access are listed: hidden or access-restricted
     * ones are skipped, so the tree never leaks names or cmids the user cannot see on the course page.
     * Activity rows omit module types excluded by {@see is_quiz_topic_excluded()} (admin setting,
     * hardcoded types such as h5pactivity, and any mod name containing "certificate").
     * Tutor page visibility is not applied here. Sections with no included activities are omitted.
     *
     * @param int $courseid
     * @param \moodle_page $page
     * @return array{course: array, sections: array}
     */
    public static function build_practice_quiz_hierarchy(int $courseid, \moodle_page $page): array {
        $course = get_course($courseid);
        $modinfo = get_fast_modinfo($course);
        $sectionmap = [];

        foreach ($modinfo->get_section_info_all() as $section) {
            if ((int) $section->section === 0) {
                continue;
            }
            // Covers hidden sections, unmet restrictions and delegated subsections of a hidden parent.
            if (!$section->uservisible) {
                continue;
            }
            $sectionmap[(int) $section->section] = [
                'num' => (int) $section->section,
                'name' => get_section_name($course, $section),
                'activities' => [],
            ];
        }

        foreach ($modinfo->get_cms() as $cm) {
            if (!$cm->uservisible) {
                continue;
            }
            if (self::is_quiz_topic_excluded($cm->modname, \context_course::instance($courseid))) {
                continue;
            }
            $sectionnum = (int) $cm->sectionnum;
            if ($sectionnum === 0 || !isset($sectionmap[$sectionnum])) {
                continue;
            }
            $sectionmap[$sectionnum]['activities'][] = [
                'cmid' => (int) $cm->id,
                'name' => $cm->get_formatted_name(),
            ];
        }

        $sections = [];
        foreach ($sectionmap as $section) {
            if ($section['activities'] !== []) {
                $sections[] = $section;
            }
        }

        return [
            'course' => [
                'name' => format_string($course->fullname, true, ['context' => \context_course::instance($courseid)]),
            ],
            'sections' => $sections,
        ];
    }

    /**
     * Handle the creation of a new block instance.
     * This method is called when the block is added to a course.
     * It configures the block settings (display on all pages, weight, etc.).
     *
     * @return bool True on success
     */
    public function instance_create(): bool {
        global $DB;

        // Update the block.
        $bi = $DB->get_record('block_instances', ['id' => $this->instance->id]);
        $bi->showinsubcontexts = 1;
        $bi->pagetypepattern = '*';
        $bi->defaultweight = 5;
        $DB->update_record('block_instances', $bi);

        return true;
    }
}
