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
 * Event fired when a tutor conversation is erased.
 *
 * @package    block_dixeo_tutor
 * @copyright  2025 Edunao SAS (contact@edunao.com)
 * @author     Pierre FACQ <pierre.facq@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_dixeo_tutor\event;

/**
 * Fired after a user erases their own tutor conversation.
 *
 * Records erasure evidence in the standard Moodle log: who, where, and how many
 * conversations the Dixeo API reported deleted. No message content is logged.
 */
class conversation_deleted extends tutor_course_base {
    /**
     * Init method.
     */
    protected function init(): void {
        parent::init();
        $this->data['crud'] = 'd';
    }

    /**
     * Localised event name.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('eventconversationdeleted', 'block_dixeo_tutor');
    }

    /**
     * Non-localised description for logs.
     *
     * @return string
     */
    public function get_description(): string {
        return get_string('eventconversationdeleteddesc', 'block_dixeo_tutor', (object) [
            'userid' => $this->userid,
            'courseid' => $this->courseid,
            'deleted' => (int) ($this->other['deleted'] ?? 0),
        ]);
    }

    /**
     * Create an event for a conversation erasure.
     *
     * @param int $courseid Course id.
     * @param int $userid Acting user id.
     * @param int $deleted Number of conversations the API reported deleted.
     * @return self
     */
    public static function create_for_course(int $courseid, int $userid, int $deleted): self {
        return self::create(self::build_course_data($courseid, $userid, ['deleted' => $deleted]));
    }

    /**
     * Custom validation.
     */
    protected function validate_data(): void {
        parent::validate_data();
        if (!array_key_exists('deleted', $this->other)) {
            throw new \coding_exception('The \'deleted\' value must be set in other.');
        }
    }
}
