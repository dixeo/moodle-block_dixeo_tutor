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
 * Event fired when a privacy request cannot reach the Dixeo API.
 *
 * Site scoped rather than course scoped: the request may fail before any course is
 * known, and the audience is the site administrator, not a course teacher.
 *
 * @package    block_dixeo_tutor
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @author     Pierre FACQ <pierre.facq@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_dixeo_tutor\event;

/**
 * Fired when the Dixeo API is unreachable while serving a privacy request.
 *
 * Core marks a privacy request complete even when a provider fails, so this event is
 * the only durable trace that the answer given to the subject was incomplete. Payload
 * is limited to the subject and the API error code: no message content is logged.
 */
class privacy_request_failed extends \core\event\base {
    /**
     * Init method.
     */
    protected function init(): void {
        $this->data['crud'] = 'r';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    /**
     * Localised event name.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('eventprivacyrequestfailed', 'block_dixeo_tutor');
    }

    /**
     * Non-localised description for logs.
     *
     * @return string
     */
    public function get_description(): string {
        return get_string('eventprivacyrequestfaileddesc', 'block_dixeo_tutor', (object) [
            'userid' => (int) $this->relateduserid,
            'errorcode' => (string) ($this->other['errorcode'] ?? ''),
        ]);
    }

    /**
     * Create an event for a privacy request the API could not serve.
     *
     * @param int $userid The user the request is about, or 0 when it targets a whole context.
     * @param string $errorcode The Dixeo API error code.
     * @return self
     */
    public static function create_for_user(int $userid, string $errorcode): self {
        return self::create([
            'context' => \context_system::instance(),
            'relateduserid' => $userid > 0 ? $userid : null,
            'other' => ['errorcode' => $errorcode],
        ]);
    }

    /**
     * Custom validation.
     */
    protected function validate_data(): void {
        parent::validate_data();
        if (!array_key_exists('errorcode', $this->other)) {
            throw new \coding_exception('The \'errorcode\' value must be set in other.');
        }
    }

    /**
     * Object id mapping for backup/restore.
     *
     * @return false
     */
    public static function get_objectid_mapping() {
        return false;
    }

    /**
     * Other mapping for backup/restore.
     *
     * @return false
     */
    public static function get_other_mapping() {
        return false;
    }
}
