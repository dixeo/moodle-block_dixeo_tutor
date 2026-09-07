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
$string['aria_copy_message'] = 'Copia messaggio';
$string['aria_load_older_messages'] = 'Carica messaggi precedenti';
$string['aria_message_copied'] = 'Copiato';
$string['aria_read_message'] = 'Leggi il messaggio ad alta voce';
$string['aria_send_message'] = 'Invia messaggio';
$string['aria_sender_assistant'] = 'Assistente';
$string['aria_sender_you'] = 'Tu';
$string['aria_sent_at'] = 'Inviato alle {$a}';
$string['aria_skip_to_input'] = 'Vai al campo di inserimento';
$string['aria_stop_reading'] = 'Interrompi lettura';
$string['aria_type_message'] = 'Scrivi il tuo messaggio';
$string['aria_your_message'] = 'Il tuo messaggio';
$string['assistanttitle'] = 'Chiedi a Ed';
$string['check_for_updates'] = 'Controlla aggiornamenti';
$string['connection_lost'] = 'Connessione persa. Tentativo di riconnessione...';
$string['custom_lesson_label'] = 'Lezione personalizzata';
$string['custom_lesson_view'] = 'Visualizza lezione';
$string['deleteconversation'] = 'Elimina la mia conversazione';
$string['deleteconversationconfirm'] = 'Questa operazione elimina definitivamente l\'intera conversazione con il tutor in questo corso. Viene rimossa subito dal servizio Dixeo e poco dopo dal fornitore di IA che ha generato le risposte. Non può essere annullata.';
$string['deleteconversationfailed'] = 'Non è stato possibile eliminare la conversazione. Nulla è stato modificato; riprova.';
$string['dixeo_tutor:addinstance'] = 'Aggiungi un nuovo blocco Tutor Studente Dixeo';
$string['dixeo_tutor:talktotutor'] = 'Interagisci con il Tutor IA';
$string['editingmode'] = 'Tutor Studente Dixeo non è disponibile in modalità di modifica.';
$string['error_apierror'] = 'Si è verificato un problema di comunicazione con il servizio IA.';
$string['error_check_updates'] = 'Impossibile verificare gli aggiornamenti. Per favore, aggiorna la pagina.';
$string['error_job_access'] = 'Impossibile recuperare lo stato del lavoro.';
$string['error_mode_not_available'] = 'La modalità tutor selezionata non è disponibile.';
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
$string['guide_banner_exit'] = 'Esci dalla modalità socratica';
$string['guide_banner_title'] = 'Modalità socratica';
$string['guide_completion_exit'] = 'Esci';
$string['guide_completion_restart'] = 'Avvia una nuova sessione';
$string['guide_review_back'] = 'Torna alla conversazione';
$string['guide_review_session'] = 'Rivedi sessione';
$string['guide_setup_cancel'] = 'Annulla';
$string['guide_setup_error'] = 'Impossibile avviare la modalità guida. Riprova.';
$string['guide_setup_instructions'] = 'Nella modalità Guidami, il tutor usa un dialogo socratico: pone domande affinché tu ragioni sull\'argomento invece di ricevere subito la risposta. Descrivi sotto di cosa hai bisogno e rispondi con parole tue durante la sessione.';
$string['guide_setup_prompt'] = 'Su cosa hai bisogno di guida?';
$string['guide_setup_prompt_placeholder'] = 'Ad esempio: non capisco come funziona la fotosintesi…';
$string['guide_setup_start'] = 'Avvia sessione';
$string['guide_setup_starting'] = 'Preparazione della sessione socratica…';
$string['guide_setup_title'] = 'Guidami';
$string['guide_topic_label'] = 'Sessione guidata';
$string['load_older_messages'] = 'Carica messaggi precedenti';
$string['message_too_long'] = 'Il messaggio non può superare {$a} caratteri.';
$string['messageprovider:privacyfailure'] = 'Richieste di privacy del tutor Dixeo non riuscite';
$string['mode_selector_title'] = 'Modalità tutor';
$string['modeguide'] = 'Guidami';
$string['modeguide_desc'] = 'Il tutor usa un approccio socratico e ti guida con domande.';
$string['modenormal'] = 'Standard';
$string['modenormal_desc'] = 'Fai domande e ottieni risposte dirette dal tutor.';
$string['modequiz'] = 'Mettimi alla prova';
$string['modequiz_desc'] = 'Esercitati con un quiz generato dal contenuto del corso.';
$string['modeteach'] = 'Insegnami';
$string['modeteach_desc'] = 'Richiedi una lezione personalizzata su un argomento a tua scelta.';
$string['notenrolled'] = 'Devi essere iscritto a questo corso per utilizzare il tutor.';
$string['placeholder'] = 'Scrivi il tuo messaggio...';
$string['pluginname'] = 'Tutor Studente Dixeo';
$string['practice_quiz_label'] = 'Quiz di pratica';
$string['privacy:metadata'] = 'Il blocco Dixeo Student Tutor memorizza contesto proattivo in coda (id utente, id corso, testo del messaggio) nel database Moodle fino all\'invio. Le conversazioni del tutor sono elaborate da local_dixeo e trasferite all\'API Dixeo. Conservazione, esportazione ed eliminazione delle conversazioni dipendono da local_dixeo e dall\'accordo del sito con il servizio Dixeo; le righe proattive in coda sono descritte in privacy:metadata:pendingpurpose.';
$string['privacy:metadata:courseid'] = 'L\'ID del corso a cui l\'utente è iscritto.';
$string['privacy:metadata:externalpurpose'] = 'I messaggi dell\'utente, il contesto del corso e un percorso di pagina minimizzato del sito vengono inviati all\'API Dixeo (tramite local_dixeo) per generare le risposte del tutor IA. Le conversazioni sono conservate dal servizio Dixeo; possono essere esportate ed eliminate su richiesta, e l\'eliminazione rimuove subito il record Dixeo e poco dopo la copia detenuta dal fornitore di IA.';
$string['privacy:metadata:lastread'] = 'L\'ora dell\'ultimo messaggio del tutor che hai letto in ogni corso (per gli indicatori di non letto).';
$string['privacy:metadata:message'] = 'Il contenuto del messaggio inviato dall\'utente.';
$string['privacy:metadata:pageurl'] = 'Un percorso URL del sito Moodle come contesto di pagina al momento dell\'invio del messaggio (limitato a questo sito; stringhe di query e frammenti vengono rimossi).';
$string['privacy:metadata:pending_courseid'] = 'Il corso a cui appartiene il contesto proattivo in coda.';
$string['privacy:metadata:pending_message'] = 'Righe di contesto in prima persona non ancora inviate al tutor.';
$string['privacy:metadata:pending_userid'] = 'L\'utente a cui appartiene il contesto proattivo in coda.';
$string['privacy:metadata:pendingpurpose'] = 'Memorizza i prompt proattivi del tutor in coda fino all\'invio all\'API Dixeo.';
$string['privacy:metadata:tutormode'] = 'La modalità tutor selezionata (standard, guida, quiz o insegnamento) in ogni corso.';
$string['privacy:metadata:tutormodeactivity'] = 'L\'orario del tuo ultimo messaggio al tutor o cambio di modalità in guida, quiz o insegnami, usato per tornare alla modalità standard dopo un\'ora di inattività.';
$string['privacy:metadata:userid'] = 'L\'ID dell\'utente che invia il messaggio.';
$string['privacy:path:conversation'] = 'Conversazione del tutor';
$string['privacyfailure_body'] = 'La richiesta di privacy del tutor Dixeo relativa a {$a} non è andata a buon fine: l\'API Dixeo non era raggiungibile. Ripeti la richiesta quando l\'API sarà di nuovo disponibile.';
$string['privacyfailure_subject'] = 'Richiesta di privacy del tutor Dixeo non riuscita';
$string['proactive_course_completed'] = 'L\'allievo ha completato il corso. Congratulati con lui calorosamente.';
$string['proactive_default_name'] = 'lì';
$string['proactive_first_visit'] = 'Accogli l\'allievo per nome ({$a->name}). È la prima volta che apre questo corso. Invia un breve saluto cordiale e offri aiuto per iniziare.';
$string['proactive_guide_started'] = 'L\'allievo è appena entrato in modalità socratica (Guidami). Ha indicato di cosa ha bisogno: «{$a->userprompt}». Riassumi in guideTopic.title e guideTopic.description. Avvia un dialogo socratico su quell\'esigenza usando i contenuti del corso: poni una domanda mirata che richieda una risposta. Non fare una lezione e non dare la risposta completa. Attendi la risposta dell\'allievo.';
$string['proactive_quiz_graded'] = 'L\'allievo ha completato il quiz «{$a->quizname}» con un voto di {$a->grade}/{$a->maxgrade}. Riconosci il risultato e incoraggialo.';
$string['proactive_return_visit_delighted'] = 'L\'allievo continua questo corso oggi. Invia un saluto particolarmente caloroso ed entusiasta — allegro e motivante. Non fare riferimento alla sua assenza, al tempo trascorso o al fatto che sta tornando. Concentrati nel salutarlo e incoraggiarlo a continuare il corso.';
$string['proactive_return_visit_enthusiastic'] = 'L\'allievo continua questo corso oggi. Invia un saluto caloroso e vivace, con energia positiva. Non menzionare quanto tempo è stato assente né usare formule come « bentornato » — salutalo e aiutalo a riprendere da dove aveva lasciato.';
$string['proactive_return_visit_warm'] = 'L\'allievo continua questo corso oggi. Invia un breve saluto cordiale. Non menzionare quanto tempo è stato assente né usare formule come « bentornato » — salutalo in modo naturale e offri aiuto per continuare.';
$string['quiz_difficulty_easy'] = 'Facile';
$string['quiz_difficulty_hard'] = 'Difficile';
$string['quiz_difficulty_medium'] = 'Medio';
$string['quiz_exit'] = 'Esci dal quiz';
$string['quiz_generate_error'] = 'Impossibile generare il quiz di pratica. Per favore, riprova.';
$string['quiz_generating'] = 'Generazione del quiz di pratica…';
$string['quiz_me'] = 'Mettimi alla prova';
$string['quiz_panel_exit_fullscreen'] = 'Esci da schermo intero';
$string['quiz_panel_fullscreen'] = 'Schermo intero';
$string['quiz_review_ai_instructions'] = '[Revisione quiz di pratica] Ho completato il quiz di pratica «{$a->title}» con un punteggio migliore di {$a->score}/{$a->total}. Usa i risultati strutturati delle domande in questo messaggio. Congratulati con me se ho fatto bene. Se il mio punteggio è basso o ho sbagliato domande, sii comprensivo e incoraggiante — aiutami a restare motivato. Suggerisci argomenti o materiali del corso da rivedere per colmare le lacune e consiglia passi concreti successivi. Mantieni la risposta mirata e utile.';
$string['quiz_review_best_score'] = 'Miglior punteggio: {$a->score}/{$a->total} ({$a->percent} %)';
$string['quiz_review_correct'] = 'Corretto';
$string['quiz_review_correct_answer'] = 'Risposta corretta';
$string['quiz_review_details'] = 'Visualizza risultati del quiz';
$string['quiz_review_exit_score'] = 'Questo tentativo: {$a->score}/{$a->total} ({$a->percent} %)';
$string['quiz_review_feedback'] = 'Feedback';
$string['quiz_review_incorrect'] = 'Errato';
$string['quiz_review_retake'] = 'Ripeti quiz';
$string['quiz_review_your_answer'] = 'La tua risposta';
$string['quiz_setup_cancel'] = 'Annulla';
$string['quiz_setup_count'] = 'Numero di domande';
$string['quiz_setup_difficulty'] = 'Difficoltà';
$string['quiz_setup_loading'] = 'Caricamento argomenti…';
$string['quiz_setup_start'] = 'Inizia il quiz';
$string['quiz_setup_title'] = 'Quiz di pratica';
$string['quiz_setup_topic'] = 'Argomento';
$string['quizrestriction'] = 'Tutor Studente Dixeo non è disponibile nelle pagine del quiz.';
$string['resize_panel'] = 'Ridimensiona il pannello del tutor';
$string['retry'] = 'Riprova';
$string['send'] = 'Invia';
$string['setting_displaymode'] = 'Modalità di visualizzazione';
$string['setting_displaymode_desc'] = 'Mostra il tutor nel cassetto dei blocchi (pannello laterale) o in una finestra a comparsa aperta con un pulsante.';
$string['setting_displaymode_drawer'] = 'Nel cassetto dei blocchi';
$string['setting_displaymode_popup'] = 'In una finestra a comparsa';
$string['setting_enabledmodes'] = 'Modalità tutor abilitate';
$string['setting_enabledmodes_desc'] = 'Seleziona quali modalità tutor opzionali sono disponibili. La modalità standard è sempre abilitata e non può essere disattivata.';
$string['setting_excludedmodules'] = 'Tipi di moduli esclusi';
$string['setting_excludedmodules_desc'] = 'Elenco separato da virgole dei tipi di moduli di attività in cui il tutor deve essere nascosto (es: quiz,simplequiz2). Il tutor non apparirà nelle pagine di questi tipi di attività.';
$string['setup_language'] = 'Lingua';
$string['talktotutor'] = 'Parla con il tutor';
$string['task_erase_conversations'] = 'Elimina le conversazioni del tutor Dixeo';
$string['teach_generate_error'] = 'Impossibile generare la lezione. Riprova.';
$string['teach_generating'] = 'Generazione della lezione personalizzata…';
$string['teach_lesson_close'] = 'Chiudi lezione';
$string['teach_lesson_fullscreen'] = 'Schermo intero';
$string['teach_lesson_tts_play'] = 'Leggi la lezione ad alta voce';
$string['teach_lesson_tts_stop'] = 'Interrompi lettura';
$string['teach_setup_cancel'] = 'Annulla';
$string['teach_setup_loading'] = 'Caricamento argomenti…';
$string['teach_setup_prompt'] = 'Descrivi cosa vuoi imparare';
$string['teach_setup_prompt_placeholder'] = 'Ad esempio: spiegami questo argomento in termini più semplici, o approfondisci un aspetto…';
$string['teach_setup_prompt_required'] = 'Descrivi cosa vuoi imparare.';
$string['teach_setup_start'] = 'Crea lezione';
$string['teach_setup_title'] = 'Lezione personalizzata';
$string['teach_setup_topic'] = 'Argomento';
$string['timeout_message'] = 'La risposta sta impiegando più tempo del previsto. L\'assistente potrebbe ancora lavorare alla tua richiesta.';
$string['tooltip_hide_tutor'] = 'Chiudi Ed';
$string['tooltip_open_tutor'] = 'Chiedi a Ed';
$string['tutorpresentation'] = 'Ciao! Sono Ed, il tuo tutor IA. Come posso aiutarti con questo corso?';
$string['unknownerror'] = 'Si è verificato un errore sconosciuto.';
$string['yesterday'] = 'ieri';
