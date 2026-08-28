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
 * Italian language strings for the Dixeo Student Tutor block.
 *
 * @package    block_dixeo_tutor
 * @copyright  2025 Edunao SAS (contact@edunao.com)
 * @author     Pierre FACQ <pierre.facq@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['aria_assistant_message'] = "Messaggio dell'assistente";
$string['aria_chat_messages'] = 'Messaggi della chat';
$string['aria_send_message'] = 'Invia messaggio';
$string['aria_sender_assistant'] = 'Assistente';
$string['aria_sender_you'] = 'Tu';
$string['aria_sent_at'] = 'Inviato alle {$a}';
$string['aria_skip_to_input'] = 'Vai al campo di inserimento';
$string['aria_type_message'] = 'Scrivi il tuo messaggio';
$string['aria_your_message'] = 'Il tuo messaggio';
$string['assistanttitle'] = 'Chiedi a Ed';
$string['check_for_updates'] = 'Controlla aggiornamenti';
$string['connection_lost'] = 'Connessione persa. Tentativo di riconnessione...';
$string['deleteconversation'] = 'Elimina la mia conversazione';
$string['deleteconversationconfirm'] = 'Questa operazione elimina definitivamente l\'intera conversazione con il tutor in questo corso. Viene rimossa subito dal servizio Dixeo e poco dopo dal fornitore di IA che ha generato le risposte. Non può essere annullata.';
$string['deleteconversationfailed'] = 'Non è stato possibile eliminare la conversazione. Nulla è stato modificato; riprova.';
$string['dixeo_tutor:addinstance'] = 'Aggiungi un nuovo blocco Tutor Studente Dixeo';
$string['dixeo_tutor:talktotutor'] = 'Interagisci con il Tutor IA';
$string['editingmode'] = 'Tutor Studente Dixeo non è disponibile in modalità di modifica.';
$string['error_apierror'] = 'Si è verificato un problema di comunicazione con il servizio IA.';
$string['error_check_updates'] = 'Impossibile verificare gli aggiornamenti. Per favore, aggiorna la pagina.';
$string['error_job_access'] = 'Impossibile recuperare lo stato del lavoro.';
$string['error_network'] = 'Si è verificato un errore di rete. Per favore, verifica la tua connessione e riprova.';
$string['error_timeout'] = 'La richiesta è scaduta. Per favore, verifica la tua connessione e riprova.';
$string['errorsendmessage'] = "Si è verificato un errore nell'invio del messaggio. Per favore, riprova.";
$string['eventconversationdeleted'] = 'Conversazione del tutor Dixeo eliminata';
$string['eventconversationdeleteddesc'] = 'L\'utente con id \'{$a->userid}\' ha eliminato la propria conversazione del tutor nel corso \'{$a->courseid}\' (deleted={$a->deleted}).';
$string['eventconversationviewed'] = 'Conversazione del tutor Dixeo visualizzata';
$string['eventconversationvieweddesc'] = 'L\'utente con id \'{$a->userid}\' ha visualizzato la conversazione del tutor nel corso \'{$a->courseid}\' (messagecount={$a->messagecount}, sinceid=\'{$a->sinceid}\').';
$string['eventjobstatusviewed'] = 'Stato del lavoro del tutor Dixeo visualizzato';
$string['eventjobstatusvieweddesc'] = 'L\'utente con id \'{$a->userid}\' ha visualizzato lo stato del lavoro del tutor nel corso \'{$a->courseid}\' (jobid=\'{$a->jobid}\', status=\'{$a->status}\').';
$string['eventmessagesent'] = 'Messaggio del tutor Dixeo inviato';
$string['eventmessagesentdesc'] = 'L\'utente con id \'{$a->userid}\' ha inviato un messaggio al tutor nel corso \'{$a->courseid}\' (jobid=\'{$a->jobid}\').';
$string['eventprivacyrequestfailed'] = 'Richiesta di privacy del tutor Dixeo non riuscita';
$string['eventprivacyrequestfaileddesc'] = 'Una richiesta di privacy non è riuscita a contattare l\'API Dixeo per l\'utente con id \'{$a->userid}\' (errorcode=\'{$a->errorcode}\').';
$string['filecountlimit'] = 'Il tutor IA è limitato a 150 file per corso (attualmente {$a} file). Si prega di ridurre il numero di file se necessario.';
$string['message_too_long'] = 'Il messaggio non può superare {$a} caratteri.';
$string['messageprovider:privacyfailure'] = 'Richieste di privacy del tutor Dixeo non riuscite';
$string['notenrolled'] = 'Devi essere iscritto a questo corso per utilizzare il tutor.';
$string['placeholder'] = 'Scrivi il tuo messaggio...';
$string['pluginname'] = 'Tutor Studente Dixeo';
$string['privacy:metadata:courseid'] = 'L\'ID del corso a cui l\'utente è iscritto.';
$string['privacy:metadata:externalpurpose'] = 'I messaggi dell\'utente, il contesto del corso e un percorso di pagina minimizzato del sito vengono inviati all\'API Dixeo (tramite local_dixeo) per generare le risposte del tutor IA. Le conversazioni sono conservate dal servizio Dixeo; possono essere esportate ed eliminate su richiesta, e l\'eliminazione rimuove subito il record Dixeo e poco dopo la copia detenuta dal fornitore di IA.';
$string['privacy:metadata:message'] = 'Il contenuto del messaggio inviato dall\'utente.';
$string['privacy:metadata:pageurl'] = 'Un percorso URL del sito Moodle come contesto di pagina al momento dell\'invio del messaggio (limitato a questo sito; stringhe di query e frammenti vengono rimossi).';
$string['privacy:metadata:userid'] = 'L\'ID dell\'utente che invia il messaggio.';
$string['privacy:path:conversation'] = 'Conversazione del tutor';
$string['privacyfailure_body'] = 'La richiesta di privacy del tutor Dixeo relativa a {$a} non è andata a buon fine: l\'API Dixeo non era raggiungibile. Ripeti la richiesta quando l\'API sarà di nuovo disponibile.';
$string['privacyfailure_subject'] = 'Richiesta di privacy del tutor Dixeo non riuscita';
$string['quizrestriction'] = 'Tutor Studente Dixeo non è disponibile nelle pagine del quiz.';
$string['resize_panel'] = 'Ridimensiona il pannello del tutor';
$string['retry'] = 'Riprova';
$string['send'] = 'Invia';
$string['setting_displaymode'] = 'Modalità di visualizzazione';
$string['setting_displaymode_desc'] = 'Mostra il tutor nel cassetto dei blocchi (pannello laterale) o in una finestra a comparsa aperta con un pulsante.';
$string['setting_displaymode_drawer'] = 'Nel cassetto dei blocchi';
$string['setting_displaymode_popup'] = 'In una finestra a comparsa';
$string['setting_excludedmodules'] = 'Tipi di moduli esclusi';
$string['setting_excludedmodules_desc'] = 'Elenco separato da virgole dei tipi di moduli di attività in cui il tutor deve essere nascosto (es: quiz,simplequiz2). Il tutor non apparirà nelle pagine di questi tipi di attività.';
$string['talktotutor'] = 'Parla con il tutor';
$string['task_erase_conversations'] = 'Elimina le conversazioni del tutor Dixeo';
$string['timeout_message'] = 'La risposta sta impiegando più tempo del previsto. L\'assistente potrebbe ancora lavorare alla tua richiesta.';
$string['tooltip_hide_tutor'] = 'Chiudi Ed';
$string['tooltip_open_tutor'] = 'Chiedi a Ed';
$string['tutorpresentation'] = 'Ciao! Sono Ed, il tuo tutor IA. Come posso aiutarti con questo corso?';
$string['unknownerror'] = 'Si è verificato un errore sconosciuto.';
$string['yesterday'] = 'ieri';
