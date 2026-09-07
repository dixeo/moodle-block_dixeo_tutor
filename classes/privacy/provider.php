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
 * Privacy provider for the Dixeo Tutor block.
 *
 * Declares personal data in the local pending-context table and data
 * transferred to the Dixeo API via local_dixeo. Conversation retention,
 * export, and deletion depend on local_dixeo and the site's Dixeo API
 * agreement; queued proactive rows are described in metadata only here.
 *
 * @package    block_dixeo_tutor
 * @copyright  2025 Edunao SAS (contact@edunao.com)
 * @author     Pierre FACQ <pierre.facq@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_dixeo_tutor\privacy;

use block_dixeo_tutor\service\tutor_mode_service;
use block_dixeo_tutor\service\tutor_read_state_service;
use core_privacy\local\metadata\collection;

/**
 * Privacy metadata provider for block_dixeo_tutor.
 *
 * Queued proactive context may be stored in block_dixeo_tutor_pending.
 * Conversation payloads are forwarded to the Dixeo API through local_dixeo.
 */
class provider implements \core_privacy\local\metadata\provider {
    /**
     * Describe stored and transmitted personal data.
     *
     * @param collection $collection The privacy metadata collection.
     * @return collection The updated collection.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'block_dixeo_tutor_pending',
            [
                'userid' => 'privacy:metadata:pending_userid',
                'courseid' => 'privacy:metadata:pending_courseid',
                'message' => 'privacy:metadata:pending_message',
            ],
            'privacy:metadata:pendingpurpose'
        );

        $collection->add_external_location_link(
            'dixeo_api',
            [
                'userid' => 'privacy:metadata:userid',
                'courseid' => 'privacy:metadata:courseid',
                'message' => 'privacy:metadata:message',
                'pageurl' => 'privacy:metadata:pageurl',
            ],
            'privacy:metadata:externalpurpose'
        );

        $collection->add_user_preference(
            tutor_read_state_service::PREF_LAST_READ_PREFIX,
            'privacy:metadata:lastread'
        );

        $collection->add_user_preference(
            tutor_mode_service::PREF_MODE_PREFIX,
            'privacy:metadata:tutormode'
        );

        return $collection;
    }
}
