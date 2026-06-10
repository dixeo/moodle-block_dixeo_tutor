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
 * German language strings for the Dixeo Student Tutor block.
 *
 * @package    block_dixeo_tutor
 * @copyright  2025 Edunao SAS (contact@edunao.com)
 * @author     Pierre FACQ <pierre.facq@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['aria_assistant_message'] = 'Assistenten-Nachricht';
$string['aria_chat_messages'] = 'Chat-Nachrichten';
$string['aria_copy_message'] = 'Nachricht kopieren';
$string['aria_load_older_messages'] = 'Ältere Nachrichten laden';
$string['aria_message_copied'] = 'Kopiert';
$string['aria_read_message'] = 'Nachricht vorlesen';
$string['aria_send_message'] = 'Nachricht senden';
$string['aria_sender_assistant'] = 'Assistent';
$string['aria_sender_you'] = 'Sie';
$string['aria_sent_at'] = 'Gesendet um {$a}';
$string['aria_skip_to_input'] = 'Zum Nachrichteneingabefeld springen';
$string['aria_stop_reading'] = 'Vorlesen stoppen';
$string['aria_type_message'] = 'Nachricht eingeben';
$string['aria_your_message'] = 'Ihre Nachricht';
$string['assistanttitle'] = 'Fragen Sie Ed';
$string['check_for_updates'] = 'Auf Updates prüfen';
$string['connection_lost'] = 'Verbindung verloren. Verbindung wird wiederhergestellt...';
$string['dixeo_tutor:addinstance'] = 'Einen neuen Dixeo Student Tutor-Block hinzufügen';
$string['dixeo_tutor:talktotutor'] = 'Mit dem KI-Tutor interagieren';
$string['editingmode'] = 'Dixeo Student Tutor ist im Bearbeitungsmodus nicht verfügbar.';
$string['error_apierror'] = 'Bei der Kommunikation mit dem KI-Dienst ist ein Problem aufgetreten.';
$string['error_check_updates'] = 'Updates konnten nicht geprüft werden. Bitte laden Sie die Seite neu.';
$string['error_job_access'] = 'Der Auftragsstatus konnte nicht abgerufen werden.';
$string['error_network'] = 'Netzwerkfehler. Bitte überprüfen Sie Ihre Verbindung und versuchen Sie es erneut.';
$string['error_timeout'] = 'Zeitüberschreitung der Anfrage. Bitte überprüfen Sie Ihre Verbindung und versuchen Sie es erneut.';
$string['errorsendmessage'] = 'Beim Senden Ihrer Nachricht ist ein Fehler aufgetreten. Bitte versuchen Sie es erneut.';
$string['eventconversationviewed'] = 'Dixeo-Tutor-Konversation angesehen';
$string['eventconversationvieweddesc'] = 'Der Benutzer mit der ID \'{$a->userid}\' hat die Tutor-Konversation im Kurs \'{$a->courseid}\' angesehen (messagecount={$a->messagecount}, sinceid=\'{$a->sinceid}\').';
$string['eventjobstatusviewed'] = 'Dixeo-Tutor-Jobstatus angesehen';
$string['eventjobstatusvieweddesc'] = 'Der Benutzer mit der ID \'{$a->userid}\' hat den Tutor-Jobstatus im Kurs \'{$a->courseid}\' angesehen (jobid=\'{$a->jobid}\', status=\'{$a->status}\').';
$string['eventmessagesent'] = 'Dixeo-Tutor-Nachricht gesendet';
$string['eventmessagesentdesc'] = 'Der Benutzer mit der ID \'{$a->userid}\' hat eine Tutor-Nachricht im Kurs \'{$a->courseid}\' gesendet (jobid=\'{$a->jobid}\').';
$string['filecountlimit'] = 'Der KI-Tutor ist auf 150 Dateien pro Kurs beschränkt (derzeit {$a} Dateien). Bitte reduzieren Sie bei Bedarf die Anzahl der Dateien.';
$string['load_older_messages'] = 'Ältere Nachrichten laden';
$string['message_too_long'] = 'Die Nachricht darf maximal {$a} Zeichen enthalten.';
$string['notenrolled'] = 'Sie müssen in diesen Kurs eingeschrieben sein, um den Tutor zu nutzen.';
$string['placeholder'] = 'Nachricht eingeben...';
$string['pluginname'] = 'Dixeo Student Tutor';
$string['privacy:metadata'] = 'Der Block Dixeo Student Tutor speichert hinterlegten proaktiven Kontext (Benutzer-ID, Kurs-ID, Nachrichtentext) in der Moodle-Datenbank, bis er gesendet wird. Tutor-Gespräche werden von local_dixeo verarbeitet und an die Dixeo-API übertragen. Aufbewahrung, Export und Löschung von Gesprächen unterliegen local_dixeo und der Vereinbarung der Website mit dem Dixeo-Dienst; hinterlegte proaktive Zeilen werden unter privacy:metadata:pendingpurpose beschrieben.';
$string['privacy:metadata:courseid'] = 'Die ID des Kurses, in dem der Benutzer eingeschrieben ist.';
$string['privacy:metadata:externalpurpose'] = 'Benutzernachrichten, Kurskontext und ein minimierter Seitenpfad der Website werden (über local_dixeo) an die Dixeo-API gesendet, um KI-Tutor-Antworten zu erzeugen. Dieser Block speichert keine Gespräche lokal und exportiert oder löscht daher keine Gesprächsdaten; diese Kontrollen müssen von local_dixeo und dem Dixeo-API-Vertrag bereitgestellt werden.';
$string['privacy:metadata:lastread'] = 'Der Zeitpunkt der letzten Tutor-Nachricht, die Sie in jedem Kurs gelesen haben (für Ungelesen-Anzeigen).';
$string['privacy:metadata:message'] = 'Der Inhalt der vom Benutzer gesendeten Nachricht.';
$string['privacy:metadata:pageurl'] = 'Ein Moodle-Seitenpfad als Seitenkontext beim Senden der Nachricht (auf diese Website beschränkt; Abfragezeichenfolgen und Fragmente werden entfernt).';
$string['privacy:metadata:pending_courseid'] = 'Der Kurs, dem der eingereihte proaktive Kontext gehört.';
$string['privacy:metadata:pending_message'] = 'Ich-Formulierungen, die noch nicht an den Tutor gesendet wurden.';
$string['privacy:metadata:pending_userid'] = 'Der Benutzer, dem der eingereihte proaktive Kontext gehört.';
$string['privacy:metadata:pendingpurpose'] = 'Speichert proaktive Tutor-Eingaben in der Warteschlange, bis sie an die Dixeo-API gesendet werden.';
$string['privacy:metadata:userid'] = 'Die ID des Benutzers, der die Nachricht sendet.';
$string['proactive_course_completed'] = 'Ich habe den Kurs abgeschlossen. Gratuliere mir';
$string['proactive_default_name'] = 'dort';
$string['proactive_first_visit'] = 'Hallo, mein Name ist {$a->name}. Ich öffne diesen Kurs zum ersten Mal. Sende mir eine Willkommensnachricht.';
$string['proactive_quiz_graded'] = 'Ich habe das Quiz „{$a->quizname}“ mit der Note {$a->grade}/{$a->maxgrade} abgeschlossen.';
$string['proactive_return_visit'] = 'Ich kehre nach {$a} zu diesem Kurs zurück. Heiße mich willkommen';
$string['quiz_difficulty_easy'] = 'Leicht';
$string['quiz_difficulty_hard'] = 'Schwer';
$string['quiz_difficulty_medium'] = 'Mittel';
$string['quiz_exit'] = 'Quiz beenden';
$string['quiz_generate_error'] = 'Das Übungsquiz konnte nicht erstellt werden. Bitte versuchen Sie es erneut.';
$string['quiz_generating'] = 'Ihr Übungsquiz wird erstellt…';
$string['quiz_me'] = 'Quiz mich';
$string['quiz_review_ai_instructions'] = '[Übungsquiz-Auswertung] Ich habe das Übungsquiz „{$a->title}“ mit einer besten Punktzahl von {$a->score}/{$a->total} abgeschlossen. Nutze die strukturierten Frageergebnisse in dieser Nachricht. Gratuliere mir, wenn ich gut abgeschnitten habe. War meine Punktzahl niedrig oder habe ich Fragen falsch beantwortet, sei unterstützend und ermutigend — hilf mir, motiviert zu bleiben. Erkläre kurz wichtige Fehler anhand der Fragedetails und des Feedbacks. Schlage konkrete Themen oder Kursinhalte zum Nacharbeiten vor und empfehle nächste Schritte. Halte deine Antwort fokussiert und hilfreich.';
$string['quiz_review_best_score'] = 'Beste Punktzahl: {$a->score}/{$a->total} ({$a->percent} %)';
$string['quiz_review_correct'] = 'Richtig';
$string['quiz_review_correct_answer'] = 'Richtige Antwort';
$string['quiz_review_exit_score'] = 'Dieser Versuch: {$a->score}/{$a->total} ({$a->percent} %)';
$string['quiz_review_feedback'] = 'Feedback';
$string['quiz_review_incorrect'] = 'Falsch';
$string['quiz_review_your_answer'] = 'Deine Antwort';
$string['quiz_setup_cancel'] = 'Abbrechen';
$string['quiz_setup_count'] = 'Anzahl der Fragen';
$string['quiz_setup_difficulty'] = 'Schwierigkeit';
$string['quiz_setup_loading'] = 'Themen werden geladen…';
$string['quiz_setup_start'] = 'Quiz starten';
$string['quiz_setup_title'] = 'Übungsquiz';
$string['quiz_setup_topic'] = 'Thema';
$string['quizrestriction'] = 'Dixeo Student Tutor ist auf Quiz-Seiten nicht verfügbar.';
$string['resize_panel'] = 'Größe des Tutor-Panels ändern';
$string['retry'] = 'Erneut versuchen';
$string['send'] = 'Senden';
$string['setting_displaymode'] = 'Anzeigemodus';
$string['setting_displaymode_desc'] = 'Tutor im Block-Schublade (Seitenpanel) oder in einem schwebenden Popup-Fenster per Button anzeigen.';
$string['setting_displaymode_drawer'] = 'In der Block-Schublade';
$string['setting_displaymode_popup'] = 'In einem Popup-Fenster';
$string['setting_excludedmodules'] = 'Ausgeschlossene Modultypen';
$string['setting_excludedmodules_desc'] = 'Kommagetrennte Liste von Aktivitätsmodultypen, auf deren Seiten der Tutor ausgeblendet werden soll (z. B. quiz, simplequiz2). Der Tutor erscheint nicht auf den Seiten dieser Aktivitätstypen.';
$string['talktotutor'] = 'Mit dem Tutor sprechen';
$string['timeout_message'] = 'Die Antwort dauert länger als erwartet. Der Assistent arbeitet möglicherweise noch an Ihrer Anfrage.';
$string['tooltip_hide_tutor'] = 'Ed schließen';
$string['tooltip_open_tutor'] = 'Frag Ed';
$string['unknownerror'] = 'Ein unbekannter Fehler ist aufgetreten.';
$string['yesterday'] = 'gestern';
