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
 * French language strings for the Dixeo Student Tutor block.
 *
 * @package    block_dixeo_tutor
 * @copyright  2025 Edunao SAS (contact@edunao.com)
 * @author     Pierre FACQ <pierre.facq@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['aria_assistant_message'] = "Message de l'assistant";
$string['aria_chat_messages'] = 'Messages du chat';
$string['aria_send_message'] = 'Envoyer le message';
$string['aria_sender_assistant'] = 'Assistant';
$string['aria_sender_you'] = 'Vous';
$string['aria_sent_at'] = 'Envoyé à {$a}';
$string['aria_skip_to_input'] = 'Aller au champ de saisie';
$string['aria_type_message'] = 'Tapez votre message';
$string['aria_your_message'] = 'Votre message';
$string['assistanttitle'] = 'Demandez à Ed';
$string['check_for_updates'] = 'Vérifier les mises à jour';
$string['connection_lost'] = 'Connexion perdue. Tentative de reconnexion...';
$string['dixeo_tutor:addinstance'] = 'Ajouter un nouveau bloc Dixeo Tuteur';
$string['dixeo_tutor:talktotutor'] = 'Interagir avec le tuteur IA';
$string['editingmode'] = "Dixeo Tuteur n'est pas disponible en mode édition.";
$string['error_apierror'] = "Désolé, un problème de communication avec le service IA s'est produit.";
$string['error_check_updates'] = 'Impossible de vérifier les mises à jour. Veuillez rafraîchir la page.';
$string['error_job_access'] = 'Impossible de récupérer le statut du travail.';
$string['error_network'] = 'Une erreur réseau est survenue. Veuillez vérifier votre connexion et réessayer.';
$string['error_timeout'] = 'La requête a expiré. Veuillez vérifier votre connexion et réessayer.';
$string['errorsendmessage'] = "Désolé, une erreur s'est produite lors de l'envoi de votre message. Veuillez réessayer.";
$string['eventconversationviewed'] = 'Conversation du tuteur Dixeo consultée';
$string['eventconversationvieweddesc'] = 'L\'utilisateur avec l\'id \'{$a->userid}\' a consulté la conversation du tuteur dans le cours \'{$a->courseid}\' (messagecount={$a->messagecount}, sinceid=\'{$a->sinceid}\').';
$string['eventjobstatusviewed'] = 'Statut du travail du tuteur Dixeo consulté';
$string['eventjobstatusvieweddesc'] = 'L\'utilisateur avec l\'id \'{$a->userid}\' a consulté le statut du travail du tuteur dans le cours \'{$a->courseid}\' (jobid=\'{$a->jobid}\', status=\'{$a->status}\').';
$string['eventmessagesent'] = 'Message du tuteur Dixeo envoyé';
$string['eventmessagesentdesc'] = 'L\'utilisateur avec l\'id \'{$a->userid}\' a envoyé un message au tuteur dans le cours \'{$a->courseid}\' (jobid=\'{$a->jobid}\').';
$string['filecountlimit'] = 'Le tuteur IA est limité à 150 fichiers par cours (actuellement {$a} fichiers). Veuillez réduire le nombre de fichiers si nécessaire.';
$string['message_too_long'] = 'Le message ne peut pas dépasser {$a} caractères.';
$string['notenrolled'] = 'Vous devez être inscrit à ce cours pour utiliser le tuteur.';
$string['placeholder'] = 'Tapez votre message...';
$string['pluginname'] = 'Dixeo Tuteur';
$string['privacy:metadata'] = 'Le bloc Dixeo Student Tutor stocke un contexte proactif en file d\'attente (identifiant utilisateur, identifiant de cours, texte du message) dans la base de données Moodle jusqu\'à son envoi. Les conversations du tuteur sont traitées par local_dixeo et transmises à l\'API Dixeo. La conservation, l\'export et la suppression des conversations relèvent de local_dixeo et de l\'accord du site avec le service Dixeo ; les lignes proactives en file sont décrites sous privacy:metadata:pendingpurpose.';
$string['privacy:metadata:courseid'] = 'L\'identifiant du cours auquel l\'utilisateur est inscrit.';
$string['privacy:metadata:externalpurpose'] = 'Les messages de l\'utilisateur, le contexte du cours et un chemin de page Moodle minimal sont envoyés à l\'API Dixeo (via local_dixeo) pour générer les réponses du tuteur IA. Ce bloc ne stocke pas de conversations localement et n\'exporte ni ne supprime donc pas ces données ; ces contrôles doivent être fournis par local_dixeo et le contrat de l\'API Dixeo.';
$string['privacy:metadata:message'] = 'Le contenu du message envoyé par l\'utilisateur.';
$string['privacy:metadata:pageurl'] = 'Un chemin d\'URL du site Moodle servant de contexte de page lors de l\'envoi du message (limité à ce site ; les paramètres de requête et les fragments sont retirés).';
$string['privacy:metadata:pending_courseid'] = 'Le cours auquel appartient le contexte proactif mis en file.';
$string['privacy:metadata:pending_message'] = 'Lignes de contexte à la première personne pas encore envoyées au tuteur.';
$string['privacy:metadata:pending_userid'] = 'L\'utilisateur auquel appartient le contexte proactif mis en file.';
$string['privacy:metadata:pendingpurpose'] = 'Stocke les invites proactives du tuteur en file d\'attente jusqu\'à leur envoi à l\'API Dixeo.';
$string['privacy:metadata:userid'] = 'L\'identifiant de l\'utilisateur envoyant le message.';
$string['proactive_course_completed'] = 'J\'ai terminé le cours. Félicite-moi';
$string['proactive_default_name'] = 'là';
$string['proactive_first_visit'] = 'Bonjour, je m\'appelle {$a->name}. C\'est la première fois que j\'ouvre ce cours. Envoie-moi un message de bienvenue.';
$string['proactive_quiz_graded'] = 'J\'ai terminé le quiz « {$a->quizname} » avec une note de {$a->grade}/{$a->maxgrade}.';
$string['proactive_return_visit'] = 'Je reviens dans ce cours après {$a}. Accueille-moi à nouveau';
$string['quizrestriction'] = "Dixeo Tuteur n'est pas disponible sur les pages de quiz.";
$string['resize_panel'] = 'Redimensionner le panneau du tuteur';
$string['retry'] = 'Réessayer';
$string['send'] = 'Envoyer';
$string['setting_displaymode'] = 'Mode d\'affichage';
$string['setting_displaymode_desc'] = 'Afficher le tuteur dans le tiroir de blocs (panneau latéral) ou dans une fenêtre flottante ouverte par un bouton.';
$string['setting_displaymode_drawer'] = 'Dans le tiroir de blocs';
$string['setting_displaymode_popup'] = 'Dans une fenêtre flottante';
$string['setting_excludedmodules'] = 'Types de modules exclus';
$string['setting_excludedmodules_desc'] = 'Liste de types de modules d\'activité séparés par des virgules où le tuteur doit être masqué (ex : quiz,simplequiz2). Le tuteur n\'apparaîtra pas sur les pages de ces types d\'activité.';
$string['talktotutor'] = 'Parler au tuteur';
$string['timeout_message'] = "La réponse prend plus de temps que prévu. L'assistant travaille peut-être encore sur votre demande.";
$string['tooltip_hide_tutor'] = 'Fermer Ed';
$string['tooltip_open_tutor'] = 'Demander à Ed';
$string['unknownerror'] = "Une erreur inconnue s'est produite.";
$string['yesterday'] = 'hier';
