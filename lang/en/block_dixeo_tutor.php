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
$string['aria_copy_message'] = 'Copy message';
$string['aria_load_older_messages'] = 'Load older messages';
$string['aria_message_copied'] = 'Copied';
$string['aria_read_message'] = 'Read message aloud';
$string['aria_send_message'] = 'Send message';
$string['aria_sender_assistant'] = 'Assistant';
$string['aria_sender_you'] = 'You';
$string['aria_skip_to_input'] = 'Skip to message input';
$string['aria_stop_reading'] = 'Stop reading';
$string['aria_type_message'] = 'Type your message';
$string['aria_your_message'] = 'Your message';
$string['assistanttitle'] = 'Ask Ed';
$string['check_for_updates'] = 'Check for updates';
$string['connection_lost'] = 'Connection lost. Attempting to reconnect...';
$string['custom_lesson_label'] = 'Custom lesson';
$string['custom_lesson_view'] = 'View lesson';
$string['deleteconversation'] = 'Delete my conversation';
$string['deleteconversationconfirm'] = 'This permanently deletes your entire conversation with the tutor in this course. It is removed from the Dixeo service straight away, and from the AI provider that generated the answers shortly afterwards. It cannot be undone.';
$string['deleteconversationfailed'] = 'Your conversation could not be deleted. Nothing has been changed; please try again.';
$string['dixeo_tutor:addinstance'] = 'Add a new Dixeo Student Tutor block';
$string['dixeo_tutor:talktotutor'] = 'Interact with the AI Tutor';
$string['editingmode'] = 'Dixeo Student Tutor is not available in editing mode.';
$string['error_apierror'] = 'Sorry, there was a problem communicating with the AI service.';
$string['error_check_updates'] = 'Unable to check for updates. Please try refreshing the page.';
$string['error_job_access'] = 'Unable to retrieve job status.';
$string['error_mode_not_available'] = 'The selected tutor mode is not available.';
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
$string['guide_banner_exit'] = 'Exit Socratic Mode';
$string['guide_banner_title'] = 'Socratic Mode';
$string['guide_completion_exit'] = 'Exit';
$string['guide_completion_restart'] = 'Start new session';
$string['guide_review_back'] = 'Back to conversation';
$string['guide_review_session'] = 'Review session';
$string['guide_setup_cancel'] = 'Cancel';
$string['guide_setup_error'] = 'Could not start guide mode. Please try again.';
$string['guide_setup_instructions'] = 'In Guide me mode, the tutor uses a Socratic dialogue: it asks questions so you can reason through the topic instead of receiving the answer first. Describe what you need help with below, then reply in your own words during the session.';
$string['guide_setup_prompt'] = 'What do you need guidance with?';
$string['guide_setup_prompt_placeholder'] = 'For example: I do not understand how photosynthesis works…';
$string['guide_setup_start'] = 'Start session';
$string['guide_setup_starting'] = 'Preparing your Socratic session…';
$string['guide_setup_title'] = 'Guide me';
$string['guide_topic_label'] = 'Guided session';
$string['load_older_messages'] = 'Load older messages';
$string['message_too_long'] = 'Message cannot exceed {$a} characters.';
$string['messageprovider:privacyfailure'] = 'Dixeo tutor privacy request failures';
$string['mode_selector_title'] = 'Tutor mode';
$string['modeguide'] = 'Guide me';
$string['modeguide_desc'] = 'The tutor uses a Socratic approach and guides you with questions.';
$string['modenormal'] = 'Standard';
$string['modenormal_desc'] = 'Ask questions and get direct answers from the tutor.';
$string['modequiz'] = 'Quiz me';
$string['modequiz_desc'] = 'Practice with a quiz generated from course content.';
$string['modeteach'] = 'Teach me';
$string['modeteach_desc'] = 'Request a custom lesson on a topic of your choice.';
$string['notenrolled'] = 'You must be enrolled in this course to use the tutor.';
$string['placeholder'] = 'Type your message...';
$string['pluginname'] = 'Dixeo Student Tutor';
$string['practice_quiz_label'] = 'Practice quiz';
$string['privacy:metadata'] = 'The Dixeo Student Tutor block stores queued proactive context (user id, course id, message text) in the Moodle database until it is sent. Tutor conversations are processed by local_dixeo and transferred to the Dixeo API. Conversations can be exported and erased on request; queued proactive rows in this block are described under privacy:metadata:pendingpurpose.';
$string['privacy:metadata:courseid'] = 'The ID of the course the user is enrolled in.';
$string['privacy:metadata:externalpurpose'] = 'User messages, course context, and a minimized site page path are sent to the Dixeo API (via local_dixeo) to generate AI tutor responses. Conversations are retained by the Dixeo service and can be exported and erased on request; erasure removes the Dixeo record straight away and the copy held by the AI provider shortly afterwards.';
$string['privacy:metadata:lastread'] = 'The time of the latest tutor message you have read in each course (for unread indicators).';
$string['privacy:metadata:message'] = 'The content of the message sent by the user.';
$string['privacy:metadata:pageurl'] = 'A Moodle site URL path for the page context when the message was sent (restricted to this site; query strings and fragments are removed).';
$string['privacy:metadata:pending_courseid'] = 'The course the queued proactive context belongs to.';
$string['privacy:metadata:pending_message'] = 'Queued first-person context lines not yet sent to the tutor.';
$string['privacy:metadata:pending_userid'] = 'The user the queued proactive context belongs to.';
$string['privacy:metadata:pendingpurpose'] = 'Stores queued proactive tutor prompts until they are sent to the Dixeo API.';
$string['privacy:metadata:tutormode'] = 'Your selected tutor mode (standard, guide, quiz, or teach) in each course.';
$string['privacy:metadata:tutormodeactivity'] = 'The time of your last tutor message or mode change in guide, quiz, or teach me, used to return to standard mode after one hour of inactivity.';
$string['privacy:metadata:userid'] = 'The ID of the user sending the message.';
$string['privacy:path:conversation'] = 'Tutor conversation';
$string['privacyfailure_body'] = 'The Dixeo tutor privacy request for {$a} could not be completed: the Dixeo API was unreachable. Run the request again once the API is back.';
$string['privacyfailure_subject'] = 'Dixeo tutor privacy request failed';
$string['proactive_default_name'] = 'there';
$string['quiz_difficulty_easy'] = 'Easy';
$string['quiz_difficulty_hard'] = 'Hard';
$string['quiz_difficulty_medium'] = 'Medium';
$string['quiz_exit'] = 'Exit quiz';
$string['quiz_generate_error'] = 'Could not generate the practice quiz. Please try again.';
$string['quiz_generating'] = 'Generating your practice quiz…';
$string['quiz_panel_exit_fullscreen'] = 'Exit full screen';
$string['quiz_panel_fullscreen'] = 'Full screen';
$string['quiz_review_best_score'] = 'Best score: {$a->score}/{$a->total} ({$a->percent}%)';
$string['quiz_review_correct'] = 'Correct';
$string['quiz_review_correct_answer'] = 'Correct answer';
$string['quiz_review_details'] = 'View quiz results';
$string['quiz_review_exit_score'] = 'This attempt: {$a->score}/{$a->total} ({$a->percent}%)';
$string['quiz_review_feedback'] = 'Feedback';
$string['quiz_review_incorrect'] = 'Incorrect';
$string['quiz_review_retake'] = 'Retake quiz';
$string['quiz_review_your_answer'] = 'Your answer';
$string['quiz_setup_cancel'] = 'Cancel';
$string['quiz_setup_count'] = 'Number of questions';
$string['quiz_setup_difficulty'] = 'Difficulty';
$string['quiz_setup_loading'] = 'Loading topics…';
$string['quiz_setup_start'] = 'Start quiz';
$string['quiz_setup_title'] = 'Practice quiz';
$string['quiz_setup_topic'] = 'Topic';
$string['resize_panel'] = 'Resize tutor panel';
$string['retry'] = 'Retry';
$string['send'] = 'Send';
$string['setting_displaymode'] = 'Display mode';
$string['setting_displaymode_desc'] = 'Show the tutor in the block drawer (side panel) or in a floating popup window opened via a button.';
$string['setting_displaymode_drawer'] = 'In block drawer';
$string['setting_displaymode_popup'] = 'In a popup window';
$string['setting_enabledmodes'] = 'Enabled tutor modes';
$string['setting_enabledmodes_desc'] = 'Select which optional tutor modes are available. Standard mode is always enabled and cannot be turned off.';
$string['setting_excludedmodules'] = 'Excluded module types';
$string['setting_excludedmodules_desc'] = 'Comma-separated list of activity module types where the tutor should be hidden (e.g. quiz,simplequiz2). The tutor will not appear on pages of these activity types.';
$string['setup_language'] = 'Language';
$string['talktotutor'] = 'Talk to the tutor';
$string['task_erase_conversations'] = 'Erase Dixeo tutor conversations';
$string['teach_generate_error'] = 'Could not generate the lesson. Please try again.';
$string['teach_generating'] = 'Generating your custom lesson…';
$string['teach_lesson_close'] = 'Close lesson';
$string['teach_lesson_fullscreen'] = 'Full screen';
$string['teach_lesson_tts_play'] = 'Read lesson aloud';
$string['teach_lesson_tts_stop'] = 'Stop reading';
$string['teach_setup_cancel'] = 'Cancel';
$string['teach_setup_loading'] = 'Loading topics…';
$string['teach_setup_prompt'] = 'Describe what you want to learn';
$string['teach_setup_prompt_placeholder'] = 'For example: explain this topic in simpler terms, or go deeper into one aspect…';
$string['teach_setup_start'] = 'Create lesson';
$string['teach_setup_title'] = 'Custom lesson';
$string['teach_setup_topic'] = 'Topic';
$string['timeout_message'] = 'The response is taking longer than expected. The assistant may still be working on your request.';
$string['tooltip_hide_tutor'] = 'Close Ed';
$string['tooltip_open_tutor'] = 'Ask Ed';
$string['unknownerror'] = 'An unknown error occurred.';
