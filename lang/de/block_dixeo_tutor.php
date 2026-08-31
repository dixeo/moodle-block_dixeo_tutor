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
$string['custom_lesson_label'] = 'Individuelle Lektion';
$string['custom_lesson_view'] = 'Lektion anzeigen';
$string['deleteconversation'] = 'Meine Konversation löschen';
$string['deleteconversationconfirm'] = 'Dadurch wird Ihre gesamte Konversation mit dem Tutor in diesem Kurs endgültig gelöscht. Sie wird sofort aus dem Dixeo-Dienst entfernt und kurz darauf beim KI-Anbieter, der die Antworten erzeugt hat. Dies kann nicht rückgängig gemacht werden.';
$string['deleteconversationfailed'] = 'Ihre Konversation konnte nicht gelöscht werden. Es wurde nichts geändert; bitte versuchen Sie es erneut.';
$string['dixeo_tutor:addinstance'] = 'Einen neuen Dixeo Student Tutor-Block hinzufügen';
$string['dixeo_tutor:talktotutor'] = 'Mit dem KI-Tutor interagieren';
$string['editingmode'] = 'Dixeo Student Tutor ist im Bearbeitungsmodus nicht verfügbar.';
$string['error_apierror'] = 'Bei der Kommunikation mit dem KI-Dienst ist ein Problem aufgetreten.';
$string['error_check_updates'] = 'Updates konnten nicht geprüft werden. Bitte laden Sie die Seite neu.';
$string['error_job_access'] = 'Der Auftragsstatus konnte nicht abgerufen werden.';
$string['error_mode_not_available'] = 'Der ausgewählte Tutor-Modus ist nicht verfügbar.';
$string['error_network'] = 'Netzwerkfehler. Bitte überprüfen Sie Ihre Verbindung und versuchen Sie es erneut.';
$string['error_timeout'] = 'Zeitüberschreitung der Anfrage. Bitte überprüfen Sie Ihre Verbindung und versuchen Sie es erneut.';
$string['errorsendmessage'] = 'Beim Senden Ihrer Nachricht ist ein Fehler aufgetreten. Bitte versuchen Sie es erneut.';
$string['eventconversationdeleted'] = 'Dixeo-Tutor-Konversation gelöscht';
$string['eventconversationdeleteddesc'] = 'Der Benutzer mit der ID \'{$a->userid}\' hat seine Tutor-Konversation im Kurs \'{$a->courseid}\' gelöscht (deleted={$a->deleted}).';
$string['eventconversationviewed'] = 'Dixeo-Tutor-Konversation angesehen';
$string['eventconversationvieweddesc'] = 'Der Benutzer mit der ID \'{$a->userid}\' hat die Tutor-Konversation im Kurs \'{$a->courseid}\' angesehen (messagecount={$a->messagecount}, sinceid=\'{$a->sinceid}\').';
$string['eventjobstatusviewed'] = 'Dixeo-Tutor-Jobstatus angesehen';
$string['eventjobstatusvieweddesc'] = 'Der Benutzer mit der ID \'{$a->userid}\' hat den Tutor-Jobstatus im Kurs \'{$a->courseid}\' angesehen (jobid=\'{$a->jobid}\', status=\'{$a->status}\').';
$string['eventmessagesent'] = 'Dixeo-Tutor-Nachricht gesendet';
$string['eventmessagesentdesc'] = 'Der Benutzer mit der ID \'{$a->userid}\' hat eine Tutor-Nachricht im Kurs \'{$a->courseid}\' gesendet (jobid=\'{$a->jobid}\').';
$string['eventprivacyrequestfailed'] = 'Datenschutzanfrage des Dixeo-Tutors fehlgeschlagen';
$string['eventprivacyrequestfaileddesc'] = 'Eine Datenschutzanfrage konnte die Dixeo-API für den Benutzer mit der ID \'{$a->userid}\' nicht erreichen (errorcode=\'{$a->errorcode}\').';
$string['filecountlimit'] = 'Der KI-Tutor ist auf 150 Dateien pro Kurs beschränkt (derzeit {$a} Dateien). Bitte reduzieren Sie bei Bedarf die Anzahl der Dateien.';
$string['guide_banner_exit'] = 'Sokratischen Modus beenden';
$string['guide_banner_title'] = 'Sokratischer Modus';
$string['guide_completion_exit'] = 'Beenden';
$string['guide_completion_restart'] = 'Neue Sitzung starten';
$string['guide_review_back'] = 'Zurück zur Unterhaltung';
$string['guide_review_session'] = 'Sitzung ansehen';
$string['guide_setup_cancel'] = 'Abbrechen';
$string['guide_setup_error'] = 'Der Begleitmodus konnte nicht gestartet werden. Bitte versuchen Sie es erneut.';
$string['guide_setup_instructions'] = 'Im Modus „Begleite mich“ führt der Tutor einen sokratischen Dialog: Er stellt Fragen, damit Sie das Thema selbst durchdenken, statt zuerst die Antwort zu erhalten. Beschreiben Sie unten, wobei Sie Hilfe brauchen, und antworten Sie während der Sitzung in eigenen Worten.';
$string['guide_setup_prompt'] = 'Wobei brauchen Sie Orientierung?';
$string['guide_setup_prompt_placeholder'] = 'Zum Beispiel: Ich verstehe nicht, wie Photosynthese funktioniert…';
$string['guide_setup_start'] = 'Sitzung starten';
$string['guide_setup_starting'] = 'Ihre sokratische Sitzung wird vorbereitet…';
$string['guide_setup_title'] = 'Führe mich';
$string['guide_topic_label'] = 'Geführte Sitzung';
$string['load_older_messages'] = 'Ältere Nachrichten laden';
$string['message_too_long'] = 'Die Nachricht darf maximal {$a} Zeichen enthalten.';
$string['messageprovider:privacyfailure'] = 'Fehlgeschlagene Datenschutzanfragen des Dixeo-Tutors';
$string['mode_selector_title'] = 'Tutor-Modus';
$string['modeguide'] = 'Führe mich';
$string['modeguide_desc'] = 'Der Tutor verwendet einen sokratischen Ansatz und leitet Sie mit Fragen.';
$string['modenormal'] = 'Standard';
$string['modenormal_desc'] = 'Stellen Sie Fragen und erhalten Sie direkte Antworten vom Tutor.';
$string['modequiz'] = 'Quiz mich';
$string['modequiz_desc'] = 'Üben Sie mit einem aus dem Kursinhalt generierten Quiz.';
$string['modeteach'] = 'Unterrichte mich';
$string['modeteach_desc'] = 'Fordern Sie eine individuelle Lektion zu einem Thema Ihrer Wahl an.';
$string['notenrolled'] = 'Sie müssen in diesen Kurs eingeschrieben sein, um den Tutor zu nutzen.';
$string['placeholder'] = 'Nachricht eingeben...';
$string['pluginname'] = 'Dixeo Student Tutor';
$string['practice_quiz_label'] = 'Übungsquiz';
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
$string['privacy:metadata:tutormode'] = 'Ihr ausgewählter Tutor-Modus (Standard, Guide, Quiz oder Teach) in jedem Kurs.';
$string['privacy:metadata:tutormodeactivity'] = 'Der Zeitpunkt Ihrer letzten Tutor-Nachricht oder Modusänderung in Guide, Quiz oder Teach me, der verwendet wird, um nach einer Stunde Inaktivität zum Standardmodus zurückzukehren.';
$string['privacy:metadata:userid'] = 'Die ID des Benutzers, der die Nachricht sendet.';
$string['privacy:path:conversation'] = 'Tutor-Konversation';
$string['privacyfailure_body'] = 'Die Datenschutzanfrage des Dixeo-Tutors zu {$a} konnte nicht abgeschlossen werden: Die Dixeo-API war nicht erreichbar. Starten Sie die Anfrage erneut, sobald die API wieder verfügbar ist.';
$string['privacyfailure_subject'] = 'Datenschutzanfrage des Dixeo-Tutors fehlgeschlagen';
$string['proactive_course_completed'] = 'Der Lernende hat den Kurs abgeschlossen. Gratuliere ihm herzlich.';
$string['proactive_default_name'] = 'dort';
$string['proactive_first_visit'] = 'Begrüße den Lernenden mit dem Vornamen ({$a->name}). Er öffnet diesen Kurs zum ersten Mal. Sende eine kurze, freundliche Willkommensnachricht und biete an, beim Einstieg zu helfen.';
$string['proactive_guide_started'] = 'Der Lernende hat gerade den sokratischen Modus (Begleite mich) betreten. Er hat angegeben, wobei er Hilfe braucht: „{$a->userprompt}". Fassen Sie dies in guideTopic.title und guideTopic.description zusammen. Starten Sie einen sokratischen Dialog zu diesem Bedarf anhand des Kursinhalts: stellen Sie eine gezielte Frage, die eine Antwort erfordert. Halten Sie keinen Vortrag und geben Sie nicht die vollständige Antwort. Warten Sie auf die Antwort des Lernenden.';
$string['proactive_quiz_graded'] = 'Der Lernende hat das Quiz „{$a->quizname}“ mit der Note {$a->grade}/{$a->maxgrade} abgeschlossen. Würdige das Ergebnis und ermutige ihn.';
$string['proactive_return_visit_delighted'] = 'Der Lernende setzt diesen Kurs heute fort. Sende eine besonders warme, enthusiastische Begrüßung — fröhlich und motivierend. Beziehe dich in keiner Weise auf seine Abwesenheit, vergangene Zeit oder seine Rückkehr. Konzentriere dich darauf, ihn zu begrüßen und zum Weitermachen im Kurs zu ermutigen.';
$string['proactive_return_visit_enthusiastic'] = 'Der Lernende setzt diesen Kurs heute fort. Sende eine warme, positive Begrüßung. Erwähne nicht, wie lange er weg war, und vermeide Formulierungen wie „Willkommen zurück“ — begrüße ihn und hilf ihm, dort weiterzumachen, wo er aufgehört hat.';
$string['proactive_return_visit_warm'] = 'Der Lernende setzt diesen Kurs heute fort. Sende eine kurze, freundliche Begrüßung. Erwähne nicht, wie lange er weg war, und vermeide Formulierungen wie „Willkommen zurück“ — begrüße ihn natürlich und biete an, beim Weitermachen zu helfen.';
$string['quiz_difficulty_easy'] = 'Leicht';
$string['quiz_difficulty_hard'] = 'Schwer';
$string['quiz_difficulty_medium'] = 'Mittel';
$string['quiz_exit'] = 'Quiz beenden';
$string['quiz_generate_error'] = 'Das Übungsquiz konnte nicht erstellt werden. Bitte versuchen Sie es erneut.';
$string['quiz_generating'] = 'Ihr Übungsquiz wird erstellt…';
$string['quiz_me'] = 'Quiz mich';
$string['quiz_panel_exit_fullscreen'] = 'Vollbild beenden';
$string['quiz_panel_fullscreen'] = 'Vollbild';
$string['quiz_review_ai_instructions'] = '[Übungsquiz-Auswertung] Ich habe das Übungsquiz „{$a->title}“ mit einer besten Punktzahl von {$a->score}/{$a->total} abgeschlossen. Nutze die strukturierten Frageergebnisse in dieser Nachricht. Gratuliere mir, wenn ich gut abgeschnitten habe. War meine Punktzahl niedrig oder habe ich Fragen falsch beantwortet, sei unterstützend und ermutigend — hilf mir, motiviert zu bleiben. Schlage konkrete Themen oder Kursinhalte zum Nacharbeiten vor und empfehle nächste Schritte. Halte deine Antwort fokussiert und hilfreich.';
$string['quiz_review_best_score'] = 'Beste Punktzahl: {$a->score}/{$a->total} ({$a->percent} %)';
$string['quiz_review_correct'] = 'Richtig';
$string['quiz_review_correct_answer'] = 'Richtige Antwort';
$string['quiz_review_details'] = 'Quiz-Ergebnisse anzeigen';
$string['quiz_review_exit_score'] = 'Dieser Versuch: {$a->score}/{$a->total} ({$a->percent} %)';
$string['quiz_review_feedback'] = 'Feedback';
$string['quiz_review_incorrect'] = 'Falsch';
$string['quiz_review_retake'] = 'Quiz wiederholen';
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
$string['setting_enabledmodes'] = 'Aktivierte Tutor-Modi';
$string['setting_enabledmodes_desc'] = 'Wählen Sie die optionalen Tutor-Modi aus, die verfügbar sein sollen. Der Standardmodus ist immer aktiviert und kann nicht deaktiviert werden.';
$string['setting_excludedmodules'] = 'Ausgeschlossene Modultypen';
$string['setting_excludedmodules_desc'] = 'Kommagetrennte Liste von Aktivitätsmodultypen, auf deren Seiten der Tutor ausgeblendet werden soll (z. B. quiz, simplequiz2). Der Tutor erscheint nicht auf den Seiten dieser Aktivitätstypen.';
$string['setup_language'] = 'Sprache';
$string['talktotutor'] = 'Mit dem Tutor sprechen';
$string['task_erase_conversations'] = 'Dixeo-Tutor-Gespräche löschen';
$string['teach_generate_error'] = 'Die Lektion konnte nicht erstellt werden. Bitte versuchen Sie es erneut.';
$string['teach_generating'] = 'Ihre individuelle Lektion wird erstellt…';
$string['teach_lesson_close'] = 'Lektion schließen';
$string['teach_lesson_fullscreen'] = 'Vollbild';
$string['teach_lesson_tts_play'] = 'Lektion vorlesen';
$string['teach_lesson_tts_stop'] = 'Vorlesen stoppen';
$string['teach_setup_cancel'] = 'Abbrechen';
$string['teach_setup_loading'] = 'Themen werden geladen…';
$string['teach_setup_prompt'] = 'Beschreiben Sie, was Sie lernen möchten';
$string['teach_setup_prompt_placeholder'] = 'Zum Beispiel: Erklären Sie dieses Thema in einfacheren Worten oder gehen Sie tiefer in einen Aspekt ein…';
$string['teach_setup_prompt_required'] = 'Bitte beschreiben Sie, was Sie lernen möchten.';
$string['teach_setup_start'] = 'Lektion erstellen';
$string['teach_setup_title'] = 'Individuelle Lektion';
$string['teach_setup_topic'] = 'Thema';
$string['timeout_message'] = 'Die Antwort dauert länger als erwartet. Der Assistent arbeitet möglicherweise noch an Ihrer Anfrage.';
$string['tooltip_hide_tutor'] = 'Ed schließen';
$string['tooltip_open_tutor'] = 'Frag Ed';
$string['tutorpresentation'] = 'Hallo! Ich bin Ed, Ihr KI-Tutor. Wie kann ich Ihnen bei diesem Kurs helfen?';
$string['unknownerror'] = 'Ein unbekannter Fehler ist aufgetreten.';
$string['yesterday'] = 'gestern';
