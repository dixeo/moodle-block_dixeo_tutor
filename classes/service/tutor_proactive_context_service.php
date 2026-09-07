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
 * Proactive tutor context queue: accumulate event-driven lines and flush to the API.
 *
 * @package    block_dixeo_tutor
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_dixeo_tutor\service;

use core\event\course_completed;
use core\event\course_viewed;
use core\event\user_graded;
use grade_item;
use block_dixeo_tutor\service\tutor_context_schema;
use block_dixeo_tutor\service\tutor_mode_service;
use local_dixeo\api\exception\api_exception;
use local_dixeo\dto\operation_result;
use local_dixeo\dto\tutor_message;
use local_dixeo\external\service_factory;

/**
 * Manages per-user, per-course queued context for proactive tutor messages.
 *
 * Proactive events are queued as JSON in the DB. They are sent to the API only when:
 * - the tutor UI loads on a page where the block would render (client flush), or
 * - an event fires during a request where {@see \block_dixeo_tutor::is_tutor_available_on_page()} is true.
 *
 * Excluded pages (e.g. quiz) never trigger an immediate server flush; the queue is drained on the
 * next course page visit where the tutor is available.
 */
class tutor_proactive_context_service {
    /** @var string Database table owned by this block. */
    public const TABLE = 'block_dixeo_tutor_pending';

    /** @var int Minimum gap between return-visit proactive lines. */
    private const RETURN_VISIT_GAP = 86400;

    /** @var int Elapsed time above which return-visit tone becomes enthusiastic. */
    private const RETURN_VISIT_ENTHUSIASTIC = 7 * DAYSECS;

    /** @var int Elapsed time above which return-visit tone becomes especially warm. */
    private const RETURN_VISIT_DELIGHTED = 30 * DAYSECS;

    /** @var int Maximum encoded queue length stored in the pending table. */
    private const MAX_QUEUE_LENGTH = 2000;

    /** @var string User preference prefix: last time a course-view proactive line was queued (per course). */
    public const PREF_LAST_PROACTIVE_PREFIX = 'block_dixeo_tutor_lastproactive_';

    /** @var array<int, bool> Grade record ids processed in this request (debounce). */
    private static array $processedgrades = [];

    /**
     * Handle course viewed: welcome / return lines and session tracking.
     *
     * @param course_viewed $event The event.
     * @return operation_result|null Flush result when sent immediately.
     */
    public function handle_course_viewed(course_viewed $event): ?operation_result {
        $userid = (int) $event->userid;
        $courseid = (int) $event->courseid;

        if (!$this->can_use_tutor($userid, $courseid)) {
            return null;
        }

        $now = time();
        // First/return timing uses user_preferences (not user_lastaccess — that is updated in require_login).
        $lastproactive = $this->get_last_proactive_course_view($userid, $courseid);
        $record = $this->get_or_create_record($userid, $courseid, $now);
        $eventsbefore = count($this->decode_queue((string) ($record->message ?? '')));

        if ($lastproactive === 0) {
            $this->append_event($record, [
                'type' => 'first_visit',
                'time' => $now,
                'name' => $this->get_user_proactive_name($userid),
            ]);
        } else if (($now - $lastproactive) >= self::RETURN_VISIT_GAP) {
            $this->append_event($record, [
                'type' => $this->get_return_visit_event_type($now - $lastproactive),
                'time' => $now,
            ]);
        }

        $eventsafter = count($this->decode_queue((string) ($record->message ?? '')));
        if ($eventsafter > $eventsbefore) {
            $this->mark_proactive_course_view($userid, $courseid, $now);
        }

        $this->save_record($record);

        // Defer flush to the tutor UI (init on pages where the block renders).
        return null;
    }

    /**
     * Handle course completed for the completing user.
     *
     * @param course_completed $event The event.
     * @return operation_result|null
     */
    public function handle_course_completed(course_completed $event): ?operation_result {
        $userid = (int) $event->relateduserid;
        $courseid = (int) $event->courseid;

        if (!$this->can_use_tutor($userid, $courseid)) {
            return null;
        }

        $record = $this->get_or_create_record($userid, $courseid, time());
        $this->append_event($record, [
            'type' => 'course_completed',
            'time' => time(),
        ]);
        $this->save_record($record);

        // Defer flush to tutor UI (same as course_viewed). Server-side flush empties the queue
        // before client _flushPendingContext runs, so PROACTIVE_REPLY_READY never fires.
        return null;
    }

    /**
     * Handle quiz grade events for mod_quiz only.
     *
     * @param user_graded $event The event.
     * @return operation_result|null
     */
    public function handle_user_graded(user_graded $event): ?operation_result {
        $userid = (int) $event->relateduserid;
        $courseid = (int) $event->courseid;
        $gradeid = (int) $event->objectid;

        if ($userid <= 0 || $courseid <= 0) {
            return null;
        }

        if (isset(self::$processedgrades[$gradeid])) {
            return null;
        }

        if (!$this->can_use_tutor($userid, $courseid)) {
            return null;
        }

        $itemid = (int) ($event->other['itemid'] ?? 0);
        if ($itemid <= 0) {
            return null;
        }

        $gradeitem = grade_item::fetch(['id' => $itemid]);
        if (!$gradeitem || $gradeitem->itemtype !== 'mod' || $gradeitem->itemmodule !== 'quiz') {
            return null;
        }

        $finalgrade = $event->other['finalgrade'] ?? null;
        if ($finalgrade === null || $finalgrade === '') {
            return null;
        }

        self::$processedgrades[$gradeid] = true;

        $quizname = $gradeitem->itemname ?: get_string('modulename', 'quiz');
        $maxgrade = (float) $gradeitem->grademax;
        if ($maxgrade <= 0) {
            $maxgrade = 100;
        }
        $score = (float) $finalgrade;

        $record = $this->get_or_create_record($userid, $courseid, time());
        $this->append_event($record, [
            'type' => 'quiz_graded',
            'time' => time(),
            'quizname' => $quizname,
            'grade' => format_float($score, 0),
            'maxgrade' => format_float($maxgrade, 0),
        ]);
        $this->save_record($record);

        if ($this->should_flush_immediately($userid, $courseid)) {
            return $this->flush($userid, $courseid, $this->current_page_url());
        }

        return null;
    }

    /**
     * Flush queued context to the tutor API and clear the message field.
     *
     * @param int $userid The user id.
     * @param int $courseid The course id.
     * @param string $pageurl Optional page URL for context.
     * @return operation_result|null Null when queue empty or user cannot use tutor.
     * @throws api_exception When the remote tutor API rejects the submit payload.
     */
    public function flush(
        int $userid,
        int $courseid,
        string $pageurl = ''
    ): ?operation_result {
        if (!$this->can_use_tutor($userid, $courseid)) {
            return null;
        }

        $record = $this->get_record($userid, $courseid);
        if (!$record) {
            return null;
        }

        $events = $this->decode_queue((string) ($record->message ?? ''));
        if ($events === []) {
            return null;
        }

        $instructions = $this->build_instructions_from_events($events, $userid);
        if ($instructions === '') {
            return null;
        }

        if (strlen($instructions) > self::MAX_QUEUE_LENGTH) {
            $instructions = \core_text::substr($instructions, 0, self::MAX_QUEUE_LENGTH);
        }

        $now = time();
        $modeservice = new tutor_mode_service();
        $mode = $modeservice->get_mode($userid, $courseid);

        try {
            $result = service_factory::get_tutor_service()->submit(
                $courseid,
                $userid,
                tutor_message::system(
                    tutor_context_schema::proactive_context($events, $userid, $courseid, $now),
                    '',
                    $instructions,
                    true
                ),
                $mode
            );
        } catch (api_exception $e) {
            throw $e;
        }

        $record->message = '';
        $record->timemodified = time();
        $this->save_record($record);

        return $result;
    }

    /**
     * Whether the user may use the tutor in this course.
     *
     * @param int $userid
     * @param int $courseid
     * @return bool
     */
    public function can_use_tutor(int $userid, int $courseid): bool {
        if ($userid <= 0 || $courseid <= 0) {
            return false;
        }

        try {
            $context = \context_course::instance($courseid);
        } catch (\Exception $e) {
            return false;
        }

        return has_capability('block/dixeo_tutor:talktotutor', $context, $userid);
    }

    /**
     * Whether queued proactive context may be sent during this HTTP request.
     * True only when the user is viewing a course page where the tutor block would render
     * (not e.g. an excluded quiz attempt page).
     *
     * @param int $userid
     * @param int $courseid
     * @return bool
     */
    public function should_flush_immediately(int $userid, int $courseid): bool {
        global $PAGE;

        if (CLI_SCRIPT || during_initial_install()) {
            return false;
        }

        if (empty($PAGE) || !($PAGE instanceof \moodle_page)) {
            return false;
        }

        if ((int) $PAGE->course->id !== $courseid) {
            return false;
        }

        if (!block_load_class('dixeo_tutor')) {
            return false;
        }

        return \block_dixeo_tutor::is_tutor_available_on_page($PAGE, $userid);
    }

    /**
     * Current page URL for tutor API context, when available.
     *
     * @return string
     */
    private function current_page_url(): string {
        global $PAGE;
        if (empty($PAGE->url)) {
            return '';
        }
        return $PAGE->url->out(false);
    }

    /**
     * Given name for proactive context (firstname, else full name).
     *
     * @param int $userid
     * @return string
     */
    private function get_user_proactive_name(int $userid): string {
        $user = \core_user::get_user($userid, '*', MUST_EXIST);
        $firstname = trim((string) ($user->firstname ?? ''));
        if ($firstname !== '') {
            return $firstname;
        }
        $fullname = trim(fullname($user));
        if ($fullname !== '') {
            return $fullname;
        }
        $username = trim((string) ($user->username ?? ''));
        return $username !== '' ? $username : get_string('proactive_default_name', 'block_dixeo_tutor');
    }

    /**
     * Language pack code for a user (site default when unset).
     *
     * @param int $userid
     * @return string
     */
    private function resolve_user_language(int $userid): string {
        global $CFG;

        $user = \core_user::get_user($userid, '*', MUST_EXIST);
        if (!empty($user->lang) && $user->lang !== 'auto') {
            return $user->lang;
        }

        return $CFG->lang;
    }

    /**
     * Proactive instruction line in the user's preferred language.
     *
     * @param string $identifier Lang string identifier in this block.
     * @param int $userid User receiving the proactive message.
     * @param string|object|null $a Placeholder value(s) for get_string.
     * @return string
     */
    private function proactive_string(string $identifier, int $userid, $a = null): string {
        $lang = $this->resolve_user_language($userid);
        return get_string_manager()->get_string($identifier, 'block_dixeo_tutor', $a, $lang);
    }

    /**
     * Event type for a return visit, scaled by time since last course view.
     *
     * @param int $elapsed Seconds since last proactive course-view line.
     * @return string
     */
    private function get_return_visit_event_type(int $elapsed): string {
        if ($elapsed >= self::RETURN_VISIT_DELIGHTED) {
            return 'return_visit_delighted';
        }
        if ($elapsed >= self::RETURN_VISIT_ENTHUSIASTIC) {
            return 'return_visit_enthusiastic';
        }
        return 'return_visit_warm';
    }

    /**
     * When a course-view proactive line was last queued for this user and course.
     *
     * @param int $userid
     * @param int $courseid
     * @return int Unix timestamp, or 0 if never.
     */
    private function get_last_proactive_course_view(int $userid, int $courseid): int {
        return (int) get_user_preferences(self::PREF_LAST_PROACTIVE_PREFIX . $courseid, 0, $userid);
    }

    /**
     * Record that a course-view proactive line was queued.
     *
     * @param int $userid
     * @param int $courseid
     * @param int $timestamp
     * @return void
     */
    private function mark_proactive_course_view(int $userid, int $courseid, int $timestamp): void {
        set_user_preference(self::PREF_LAST_PROACTIVE_PREFIX . $courseid, $timestamp, $userid);
    }

    /**
     * Decode queued proactive events from the pending record message field.
     *
     * @param string $raw
     * @return array
     */
    public function decode_queue(string $raw): array {
        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (is_array($decoded) && $this->is_event_list($decoded)) {
            return $decoded;
        }

        return [];
    }

    /**
     * Whether the value is a list of proactive events.
     *
     * @param array $events
     * @return bool
     */
    private function is_event_list(array $events): bool {
        if ($events === []) {
            return true;
        }

        foreach ($events as $event) {
            if (!is_array($event)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Encode proactive events for storage in the pending table.
     *
     * @param array $events Queued proactive events.
     * @return string
     */
    private function encode_queue(array $events): string {
        $json = json_encode($events, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            return '[]';
        }

        if (strlen($json) <= self::MAX_QUEUE_LENGTH) {
            return $json;
        }

        while (count($events) > 1 && strlen(json_encode($events, JSON_UNESCAPED_UNICODE)) > self::MAX_QUEUE_LENGTH) {
            array_shift($events);
        }

        $json = json_encode($events, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            return '[]';
        }

        if (strlen($json) > self::MAX_QUEUE_LENGTH) {
            return \core_text::substr($json, 0, self::MAX_QUEUE_LENGTH);
        }

        return $json;
    }

    /**
     * Append a structured event to the pending queue.
     *
     * @param \stdClass $record Pending context row.
     * @param array $event Structured proactive event.
     * @return void
     */
    private function append_event(\stdClass $record, array $event): void {
        if (empty($event['type'])) {
            return;
        }

        $events = $this->decode_queue((string) ($record->message ?? ''));
        $events[] = $event;
        $record->message = $this->encode_queue($events);
        $record->timemodified = time();
    }

    /**
     * Build combined AI instructions from queued proactive events.
     *
     * @param array $events Queued proactive events.
     * @param int $userid User id for language resolution.
     * @return string
     */
    private function build_instructions_from_events(array $events, int $userid): string {
        $lines = [];

        foreach ($events as $event) {
            if (!is_array($event)) {
                continue;
            }

            $type = (string) ($event['type'] ?? '');
            if ($type === '') {
                continue;
            }

            $a = $this->instruction_placeholders($type, $event);
            $lines[] = $this->proactive_string('proactive_' . $type, $userid, $a);
        }

        return trim(implode("\n\n", array_filter($lines, static function (string $line): bool {
            return trim($line) !== '';
        })));
    }

    /**
     * Build placeholders for a proactive instruction string.
     *
     * @param string $type Proactive event type.
     * @param array $event Structured proactive event.
     * @return object|null
     */
    private function instruction_placeholders(string $type, array $event): ?object {
        if ($type === 'first_visit') {
            return (object) [
                'name' => (string) ($event['name'] ?? get_string('proactive_default_name', 'block_dixeo_tutor')),
            ];
        }

        if ($type === 'quiz_graded') {
            return (object) [
                'quizname' => (string) ($event['quizname'] ?? ''),
                'grade' => (string) ($event['grade'] ?? ''),
                'maxgrade' => (string) ($event['maxgrade'] ?? ''),
            ];
        }

        return null;
    }

    /**
     * Fetch the pending proactive context record.
     *
     * @param int $userid
     * @param int $courseid
     * @return \stdClass|null
     */
    private function get_record(int $userid, int $courseid): ?\stdClass {
        global $DB;
        return $DB->get_record(self::TABLE, ['userid' => $userid, 'courseid' => $courseid]) ?: null;
    }

    /**
     * Fetch or create the pending proactive context record.
     *
     * @param int $userid
     * @param int $courseid
     * @param int $now
     * @return \stdClass
     */
    private function get_or_create_record(int $userid, int $courseid, int $now): \stdClass {
        $record = $this->get_record($userid, $courseid);
        if ($record) {
            return $record;
        }

        $record = (object) [
            'userid' => $userid,
            'courseid' => $courseid,
            'message' => '',
            'timemodified' => $now,
        ];
        $record->id = $this->insert_record($record);
        return $record;
    }

    /**
     * Persist a pending proactive context record.
     *
     * @param \stdClass $record
     * @return void
     */
    private function save_record(\stdClass $record): void {
        global $DB;
        $record->timemodified = time();
        $DB->update_record(self::TABLE, $record);
    }

    /**
     * Insert a new pending proactive context record.
     *
     * @param \stdClass $record
     * @return int
     */
    private function insert_record(\stdClass $record): int {
        global $DB;
        return (int) $DB->insert_record(self::TABLE, $record);
    }
}
