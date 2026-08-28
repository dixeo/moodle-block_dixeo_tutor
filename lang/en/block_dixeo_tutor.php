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
 * English language strings for the Dixeo Student Tutor block.
 *
 * @package    block_dixeo_tutor
 * @copyright  2025 Edunao SAS (contact@edunao.com)
 * @author     Pierre FACQ <pierre.facq@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['aria_assistant_message'] = 'Assistant message';
$string['aria_chat_messages'] = 'Chat messages';
$string['aria_send_message'] = 'Send message';
$string['aria_sender_assistant'] = 'Assistant';
$string['aria_sender_you'] = 'You';
$string['aria_sent_at'] = 'Sent at {$a}';
$string['aria_skip_to_input'] = 'Skip to message input';
$string['aria_type_message'] = 'Type your message';
$string['aria_your_message'] = 'Your message';
$string['assistanttitle'] = 'Ask Ed';
$string['check_for_updates'] = 'Check for updates';
$string['connection_lost'] = 'Connection lost. Attempting to reconnect...';
$string['deleteconversation'] = 'Delete my conversation';
$string['deleteconversationconfirm'] = 'This permanently deletes your entire conversation with the tutor in this course. It is removed from the Dixeo service straight away, and from the AI provider that generated the answers shortly afterwards. It cannot be undone.';
$string['deleteconversationfailed'] = 'Your conversation could not be deleted. Nothing has been changed; please try again.';
$string['dixeo_tutor:addinstance'] = 'Add a new Dixeo Student Tutor block';
$string['dixeo_tutor:talktotutor'] = 'Interact with the AI Tutor';
$string['editingmode'] = 'Dixeo Student Tutor is not available in editing mode.';
$string['error_apierror'] = 'Sorry, there was a problem communicating with the AI service.';
$string['error_check_updates'] = 'Unable to check for updates. Please try refreshing the page.';
$string['error_job_access'] = 'Unable to retrieve job status.';
$string['error_network'] = 'Network error occurred. Please check your connection and try again.';
$string['error_timeout'] = 'Request timed out. Please check your connection and try again.';
$string['errorsendmessage'] = 'Sorry, there was an error sending your message. Please try again.';
$string['eventconversationdeleted'] = 'Dixeo tutor conversation deleted';
$string['eventconversationdeleteddesc'] = 'The user with id \'{$a->userid}\' deleted their tutor conversation in course \'{$a->courseid}\' (deleted={$a->deleted}).';
$string['eventconversationviewed'] = 'Dixeo tutor conversation viewed';
$string['eventconversationvieweddesc'] = 'The user with id \'{$a->userid}\' viewed tutor conversation in course \'{$a->courseid}\' (messagecount={$a->messagecount}, sinceid=\'{$a->sinceid}\').';
$string['eventjobstatusviewed'] = 'Dixeo tutor job status viewed';
$string['eventjobstatusvieweddesc'] = 'The user with id \'{$a->userid}\' viewed tutor job status in course \'{$a->courseid}\' (jobid=\'{$a->jobid}\', status=\'{$a->status}\').';
$string['eventmessagesent'] = 'Dixeo tutor message sent';
$string['eventmessagesentdesc'] = 'The user with id \'{$a->userid}\' sent a tutor message in course \'{$a->courseid}\' (jobid=\'{$a->jobid}\').';
$string['eventprivacyrequestfailed'] = 'Dixeo tutor privacy request failed';
$string['eventprivacyrequestfaileddesc'] = 'A privacy request could not reach the Dixeo API for the user with id \'{$a->userid}\' (errorcode=\'{$a->errorcode}\').';
$string['filecountlimit'] = 'The AI tutor is limited to 150 files per course (currently {$a} files). Please reduce the number of files if needed.';
$string['message_too_long'] = 'Message cannot exceed {$a} characters.';
$string['messageprovider:privacyfailure'] = 'Dixeo tutor privacy request failures';
$string['notenrolled'] = 'You must be enrolled in this course to use the tutor.';
$string['placeholder'] = 'Type your message...';
$string['pluginname'] = 'Dixeo Student Tutor';
$string['privacy:metadata:courseid'] = 'The ID of the course the user is enrolled in.';
$string['privacy:metadata:externalpurpose'] = 'User messages, course context, and a minimized site page path are sent to the Dixeo API (via local_dixeo) to generate AI tutor responses. Conversations are retained by the Dixeo service and can be exported and erased on request; erasure removes the Dixeo record straight away and the copy held by the AI provider shortly afterwards.';
$string['privacy:metadata:message'] = 'The content of the message sent by the user.';
$string['privacy:metadata:pageurl'] = 'A Moodle site URL path for the page context when the message was sent (restricted to this site; query strings and fragments are removed).';
$string['privacy:metadata:userid'] = 'The ID of the user sending the message.';
$string['privacy:path:conversation'] = 'Tutor conversation';
$string['privacyfailure_body'] = 'The Dixeo tutor privacy request for {$a} could not be completed: the Dixeo API was unreachable. Run the request again once the API is back.';
$string['privacyfailure_subject'] = 'Dixeo tutor privacy request failed';
$string['quizrestriction'] = 'Dixeo Student Tutor is not available on quiz pages.';
$string['resize_panel'] = 'Resize tutor panel';
$string['retry'] = 'Retry';
$string['send'] = 'Send';
$string['setting_displaymode'] = 'Display mode';
$string['setting_displaymode_desc'] = 'Show the tutor in the block drawer (side panel) or in a floating popup window opened via a button.';
$string['setting_displaymode_drawer'] = 'In block drawer';
$string['setting_displaymode_popup'] = 'In a popup window';
$string['setting_excludedmodules'] = 'Excluded module types';
$string['setting_excludedmodules_desc'] = 'Comma-separated list of activity module types where the tutor should be hidden (e.g. quiz,simplequiz2). The tutor will not appear on pages of these activity types.';
$string['talktotutor'] = 'Talk to the tutor';
$string['task_erase_conversations'] = 'Erase Dixeo tutor conversations';
$string['timeout_message'] = 'The response is taking longer than expected. The assistant may still be working on your request.';
$string['tooltip_hide_tutor'] = 'Close Ed';
$string['tooltip_open_tutor'] = 'Ask Ed';
$string['tutorpresentation'] = "Hi! I'm Ed, your AI tutor. How can I help you with this course?";
$string['unknownerror'] = 'An unknown error occurred.';
$string['yesterday'] = 'yesterday';
