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
$string['aria_send_message'] = 'Nachricht senden';
$string['aria_sender_assistant'] = 'Assistent';
$string['aria_sender_you'] = 'Sie';
$string['aria_sent_at'] = 'Gesendet um {$a}';
$string['aria_skip_to_input'] = 'Zum Nachrichteneingabefeld springen';
$string['aria_type_message'] = 'Nachricht eingeben';
$string['aria_your_message'] = 'Ihre Nachricht';
$string['assistanttitle'] = 'Fragen Sie Ed';
$string['check_for_updates'] = 'Auf Updates prüfen';
$string['connection_lost'] = 'Verbindung verloren. Verbindung wird wiederhergestellt...';
$string['deleteconversation'] = 'Meine Konversation löschen';
$string['deleteconversationconfirm'] = 'Dadurch wird Ihre gesamte Konversation mit dem Tutor in diesem Kurs endgültig gelöscht. Sie wird sofort aus dem Dixeo-Dienst entfernt und kurz darauf beim KI-Anbieter, der die Antworten erzeugt hat. Dies kann nicht rückgängig gemacht werden.';
$string['deleteconversationfailed'] = 'Ihre Konversation konnte nicht gelöscht werden. Es wurde nichts geändert; bitte versuchen Sie es erneut.';
$string['dixeo_tutor:addinstance'] = 'Einen neuen Dixeo Student Tutor-Block hinzufügen';
$string['dixeo_tutor:talktotutor'] = 'Mit dem KI-Tutor interagieren';
$string['editingmode'] = 'Dixeo Student Tutor ist im Bearbeitungsmodus nicht verfügbar.';
$string['error_apierror'] = 'Bei der Kommunikation mit dem KI-Dienst ist ein Problem aufgetreten.';
$string['error_check_updates'] = 'Updates konnten nicht geprüft werden. Bitte laden Sie die Seite neu.';
$string['error_job_access'] = 'Der Auftragsstatus konnte nicht abgerufen werden.';
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
$string['message_too_long'] = 'Die Nachricht darf maximal {$a} Zeichen enthalten.';
$string['messageprovider:privacyfailure'] = 'Fehlgeschlagene Datenschutzanfragen des Dixeo-Tutors';
$string['notenrolled'] = 'Sie müssen in diesen Kurs eingeschrieben sein, um den Tutor zu nutzen.';
$string['placeholder'] = 'Nachricht eingeben...';
$string['pluginname'] = 'Dixeo Student Tutor';
$string['privacy:metadata:courseid'] = 'Die ID des Kurses, in dem der Benutzer eingeschrieben ist.';
$string['privacy:metadata:externalpurpose'] = 'Benutzernachrichten, Kurskontext und ein minimierter Seitenpfad der Website werden (über local_dixeo) an die Dixeo-API gesendet, um KI-Tutor-Antworten zu erzeugen. Gespräche werden vom Dixeo-Dienst aufbewahrt; sie können auf Anfrage exportiert und gelöscht werden, wobei die Löschung den Dixeo-Datensatz sofort und die Kopie beim KI-Anbieter kurz darauf entfernt.';
$string['privacy:metadata:message'] = 'Der Inhalt der vom Benutzer gesendeten Nachricht.';
$string['privacy:metadata:pageurl'] = 'Ein Moodle-Seitenpfad als Seitenkontext beim Senden der Nachricht (auf diese Website beschränkt; Abfragezeichenfolgen und Fragmente werden entfernt).';
$string['privacy:metadata:userid'] = 'Die ID des Benutzers, der die Nachricht sendet.';
$string['privacy:path:conversation'] = 'Tutor-Konversation';
$string['privacyfailure_body'] = 'Die Datenschutzanfrage des Dixeo-Tutors zu {$a} konnte nicht abgeschlossen werden: Die Dixeo-API war nicht erreichbar. Starten Sie die Anfrage erneut, sobald die API wieder verfügbar ist.';
$string['privacyfailure_subject'] = 'Datenschutzanfrage des Dixeo-Tutors fehlgeschlagen';
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
$string['task_erase_conversations'] = 'Dixeo-Tutor-Gespräche löschen';
$string['timeout_message'] = 'Die Antwort dauert länger als erwartet. Der Assistent arbeitet möglicherweise noch an Ihrer Anfrage.';
$string['tooltip_hide_tutor'] = 'Ed schließen';
$string['tooltip_open_tutor'] = 'Frag Ed';
$string['tutorpresentation'] = 'Hallo! Ich bin Ed, Ihr KI-Tutor. Wie kann ich Ihnen bei diesem Kurs helfen?';
$string['unknownerror'] = 'Ein unbekannter Fehler ist aufgetreten.';
$string['yesterday'] = 'gestern';
