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
$string['aria_sent_at'] = 'Sent at {$a}';
$string['aria_skip_to_input'] = 'Skip to message input';
$string['aria_stop_reading'] = 'Stop reading';
$string['aria_type_message'] = 'Type your message';
$string['aria_your_message'] = 'Your message';
$string['assistanttitle'] = 'Ask Ed';
$string['check_for_updates'] = 'Check for updates';
$string['connection_lost'] = 'Connection lost. Attempting to reconnect...';
$string['dixeo_tutor:addinstance'] = 'Add a new Dixeo Student Tutor block';
$string['dixeo_tutor:talktotutor'] = 'Interact with the AI Tutor';
$string['editingmode'] = 'Dixeo Student Tutor is not available in editing mode.';
$string['error_apierror'] = 'Sorry, there was a problem communicating with the AI service.';
$string['error_check_updates'] = 'Unable to check for updates. Please try refreshing the page.';
$string['error_job_access'] = 'Unable to retrieve job status.';
$string['error_network'] = 'Network error occurred. Please check your connection and try again.';
$string['error_timeout'] = 'Request timed out. Please check your connection and try again.';
$string['errorsendmessage'] = 'Sorry, there was an error sending your message. Please try again.';
$string['eventconversationviewed'] = 'Dixeo tutor conversation viewed';
$string['eventconversationvieweddesc'] = 'The user with id \'{$a->userid}\' viewed tutor conversation in course \'{$a->courseid}\' (messagecount={$a->messagecount}, sinceid=\'{$a->sinceid}\').';
$string['eventjobstatusviewed'] = 'Dixeo tutor job status viewed';
$string['eventjobstatusvieweddesc'] = 'The user with id \'{$a->userid}\' viewed tutor job status in course \'{$a->courseid}\' (jobid=\'{$a->jobid}\', status=\'{$a->status}\').';
$string['eventmessagesent'] = 'Dixeo tutor message sent';
$string['eventmessagesentdesc'] = 'The user with id \'{$a->userid}\' sent a tutor message in course \'{$a->courseid}\' (jobid=\'{$a->jobid}\').';
$string['filecountlimit'] = 'The AI tutor is limited to 150 files per course (currently {$a} files). Please reduce the number of files if needed.';
$string['load_older_messages'] = 'Load older messages';
$string['message_too_long'] = 'Message cannot exceed {$a} characters.';
$string['mode_selector_title'] = 'Tutor mode';
$string['modeguide'] = 'Guide me';
$string['modeguide_desc'] = 'The tutor uses a Socratic approach and guides you with questions.';
$string['modenormal'] = 'Normal';
$string['modenormal_desc'] = 'Ask questions and get direct answers from the tutor.';
$string['modequiz'] = 'Quiz me';
$string['modequiz_desc'] = 'Practice with a quiz generated from course content.';
$string['modeteach'] = 'Teach me';
$string['modeteach_desc'] = 'Request a custom lesson on a topic of your choice.';
$string['notenrolled'] = 'You must be enrolled in this course to use the tutor.';
$string['placeholder'] = 'Type your message...';
$string['pluginname'] = 'Dixeo Student Tutor';
$string['privacy:metadata'] = 'The Dixeo Student Tutor block stores queued proactive context (user id, course id, message text) in the Moodle database until it is sent. Tutor conversations are processed by local_dixeo and transferred to the Dixeo API. Retention, export, and deletion of conversations are governed by local_dixeo and the site\'s agreement with the Dixeo service; queued proactive rows in this block are described under privacy:metadata:pendingpurpose.';
$string['privacy:metadata:courseid'] = 'The ID of the course the user is enrolled in.';
$string['privacy:metadata:externalpurpose'] = 'User messages, course context, and a minimized site page path are sent to the Dixeo API (via local_dixeo) to generate AI tutor responses. This block has no local conversation storage and therefore does not export or delete conversation data; those controls must be provided by local_dixeo and the Dixeo API contract.';
$string['privacy:metadata:lastread'] = 'The time of the latest tutor message you have read in each course (for unread indicators).';
$string['privacy:metadata:message'] = 'The content of the message sent by the user.';
$string['privacy:metadata:pageurl'] = 'A Moodle site URL path for the page context when the message was sent (restricted to this site; query strings and fragments are removed).';
$string['privacy:metadata:pending_courseid'] = 'The course the queued proactive context belongs to.';
$string['privacy:metadata:pending_message'] = 'Queued first-person context lines not yet sent to the tutor.';
$string['privacy:metadata:pending_userid'] = 'The user the queued proactive context belongs to.';
$string['privacy:metadata:pendingpurpose'] = 'Stores queued proactive tutor prompts until they are sent to the Dixeo API.';
$string['privacy:metadata:tutormode'] = 'Your selected tutor mode (normal, guide, quiz, or teach) in each course.';
$string['privacy:metadata:userid'] = 'The ID of the user sending the message.';
$string['proactive_course_completed'] = 'I completed the course. Congratulate me';
$string['proactive_default_name'] = 'there';
$string['proactive_first_visit'] = 'Hi, my name is {$a->name}. This is my first time opening this course. Send a welcome message.';
$string['proactive_quiz_graded'] = 'I completed the quiz "{$a->quizname}" with a grade of {$a->grade}/{$a->maxgrade}.';
$string['proactive_return_visit_delighted'] = 'I am continuing with this course today. Send me an especially warm, enthusiastic welcome — cheerful and motivating. Do not reference my absence, elapsed time, or returning in any way. Focus on greeting me and encouraging me to continue the course.';
$string['proactive_return_visit_enthusiastic'] = 'I am continuing with this course today. Send me a warm, upbeat welcome with positive energy. Do not mention how long since I was last here or use "welcome back" phrasing — greet me and help me pick up where I left off.';
$string['proactive_return_visit_warm'] = 'I am continuing with this course today. Send me a brief, friendly welcome. Do not mention time away or use "welcome back" phrasing — greet me naturally and offer to help me continue.';
$string['quiz_difficulty_easy'] = 'Easy';
$string['quiz_difficulty_hard'] = 'Hard';
$string['quiz_difficulty_medium'] = 'Medium';
$string['quiz_exit'] = 'Exit quiz';
$string['quiz_generate_error'] = 'Could not generate the practice quiz. Please try again.';
$string['quiz_generating'] = 'Generating your practice quiz…';
$string['quiz_me'] = 'Quiz me';
$string['quiz_review_ai_instructions'] = '[Practice quiz review] I finished the practice quiz "{$a->title}" with a best score of {$a->score}/{$a->total}. Use the structured question results in this message. Congratulate me if I did well. If my score was low or I missed questions, be supportive and encouraging — help me feel motivated, not discouraged. Briefly explain key mistakes using the question details and feedback. Suggest specific topics or course material to review to fill knowledge gaps, and recommend concrete next steps. Keep your response focused and helpful.';
$string['quiz_review_best_score'] = 'Best score: {$a->score}/{$a->total} ({$a->percent}%)';
$string['quiz_review_correct'] = 'Correct';
$string['quiz_review_correct_answer'] = 'Correct answer';
$string['quiz_review_exit_score'] = 'This attempt: {$a->score}/{$a->total} ({$a->percent}%)';
$string['quiz_review_feedback'] = 'Feedback';
$string['quiz_review_incorrect'] = 'Incorrect';
$string['quiz_review_your_answer'] = 'Your answer';
$string['quiz_setup_cancel'] = 'Cancel';
$string['quiz_setup_count'] = 'Number of questions';
$string['quiz_setup_difficulty'] = 'Difficulty';
$string['quiz_setup_loading'] = 'Loading topics…';
$string['quiz_setup_start'] = 'Start quiz';
$string['quiz_setup_title'] = 'Practice quiz';
$string['quiz_setup_topic'] = 'Topic';
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
$string['timeout_message'] = 'The response is taking longer than expected. The assistant may still be working on your request.';
$string['tooltip_hide_tutor'] = 'Close Ed';
$string['tooltip_open_tutor'] = 'Ask Ed';
$string['unknownerror'] = 'An unknown error occurred.';
$string['yesterday'] = 'yesterday';
