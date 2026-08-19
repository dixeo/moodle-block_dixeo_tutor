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
$string['aria_copy_message'] = 'Copier le message';
$string['aria_load_older_messages'] = 'Charger les messages plus anciens';
$string['aria_message_copied'] = 'Copié';
$string['aria_read_message'] = 'Lire le message à voix haute';
$string['aria_send_message'] = 'Envoyer le message';
$string['aria_sender_assistant'] = 'Assistant';
$string['aria_sender_you'] = 'Vous';
$string['aria_sent_at'] = 'Envoyé à {$a}';
$string['aria_skip_to_input'] = 'Aller au champ de saisie';
$string['aria_stop_reading'] = 'Arrêter la lecture';
$string['aria_type_message'] = 'Tapez votre message';
$string['aria_your_message'] = 'Votre message';
$string['assistanttitle'] = 'Demandez à Ed';
$string['check_for_updates'] = 'Vérifier les mises à jour';
$string['connection_lost'] = 'Connexion perdue. Tentative de reconnexion...';
$string['custom_lesson_label'] = 'Leçon personnalisée';
$string['custom_lesson_view'] = 'Voir la leçon';
$string['dixeo_tutor:addinstance'] = 'Ajouter un nouveau bloc Dixeo Tuteur';
$string['dixeo_tutor:talktotutor'] = 'Interagir avec le tuteur IA';
$string['editingmode'] = "Dixeo Tuteur n'est pas disponible en mode édition.";
$string['error_apierror'] = "Désolé, un problème de communication avec le service IA s'est produit.";
$string['error_check_updates'] = 'Impossible de vérifier les mises à jour. Veuillez rafraîchir la page.';
$string['error_job_access'] = 'Impossible de récupérer le statut du travail.';
$string['error_mode_not_available'] = 'Le mode de tuteur sélectionné n\'est pas disponible.';
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
$string['guide_banner_exit'] = 'Quitter le mode socratique';
$string['guide_banner_help'] = 'En mode socratique, le tuteur pose des questions pour vous aider à raisonner plutôt que de donner la réponse d\'emblée. Répondez avec vos propres mots. Si vous êtes bloqué, dites-le et vous recevrez un indice. Fermez cette barre pour revenir au mode standard.';
$string['guide_banner_help_label'] = 'À propos du mode socratique';
$string['guide_banner_title'] = 'Mode socratique';
$string['load_older_messages'] = 'Charger les messages plus anciens';
$string['message_too_long'] = 'Le message ne peut pas dépasser {$a} caractères.';
$string['mode_selector_title'] = 'Mode du tuteur';
$string['modeguide'] = 'Guide-moi';
$string['modeguide_desc'] = 'Le tuteur utilise une approche socratique et vous guide avec des questions.';
$string['modenormal'] = 'Standard';
$string['modenormal_desc'] = 'Posez des questions et obtenez des réponses directes du tuteur.';
$string['modequiz'] = 'Teste-moi';
$string['modequiz_desc'] = 'Entraînez-vous avec un quiz généré à partir du contenu du cours.';
$string['modeteach'] = 'Enseigne-moi';
$string['modeteach_desc'] = 'Demandez une leçon personnalisée sur un sujet de votre choix.';
$string['notenrolled'] = 'Vous devez être inscrit à ce cours pour utiliser le tuteur.';
$string['placeholder'] = 'Tapez votre message...';
$string['pluginname'] = 'Dixeo Tuteur';
$string['privacy:metadata'] = 'Le bloc Dixeo Student Tutor stocke un contexte proactif en file d\'attente (identifiant utilisateur, identifiant de cours, texte du message) dans la base de données Moodle jusqu\'à son envoi. Les conversations du tuteur sont traitées par local_dixeo et transmises à l\'API Dixeo. La conservation, l\'export et la suppression des conversations relèvent de local_dixeo et de l\'accord du site avec le service Dixeo ; les lignes proactives en file sont décrites sous privacy:metadata:pendingpurpose.';
$string['privacy:metadata:courseid'] = 'L\'identifiant du cours auquel l\'utilisateur est inscrit.';
$string['privacy:metadata:externalpurpose'] = 'Les messages de l\'utilisateur, le contexte du cours et un chemin de page Moodle minimal sont envoyés à l\'API Dixeo (via local_dixeo) pour générer les réponses du tuteur IA. Ce bloc ne stocke pas de conversations localement et n\'exporte ni ne supprime donc pas ces données ; ces contrôles doivent être fournis par local_dixeo et le contrat de l\'API Dixeo.';
$string['privacy:metadata:lastread'] = 'L\'heure du dernier message du tuteur que vous avez lu dans chaque cours (pour les indicateurs de non-lu).';
$string['privacy:metadata:message'] = 'Le contenu du message envoyé par l\'utilisateur.';
$string['privacy:metadata:pageurl'] = 'Un chemin d\'URL du site Moodle servant de contexte de page lors de l\'envoi du message (limité à ce site ; les paramètres de requête et les fragments sont retirés).';
$string['privacy:metadata:pending_courseid'] = 'Le cours auquel appartient le contexte proactif en file d\'attente.';
$string['privacy:metadata:pending_message'] = 'Lignes de contexte à la première personne pas encore envoyées au tuteur.';
$string['privacy:metadata:pending_userid'] = 'L\'utilisateur auquel appartient le contexte proactif en file d\'attente.';
$string['privacy:metadata:pendingpurpose'] = 'Stocke les invites proactives du tuteur en file d\'attente jusqu\'à leur envoi à l\'API Dixeo.';
$string['privacy:metadata:tutormode'] = 'Votre mode de tuteur sélectionné (standard, guide, quiz ou enseignement) dans chaque cours.';
$string['privacy:metadata:tutormodeactivity'] = 'L\'heure de votre dernier message au tuteur ou changement de mode en guide, quiz ou enseignement, utilisée pour revenir au mode standard après une heure d\'inactivité.';
$string['privacy:metadata:userid'] = 'L\'identifiant de l\'utilisateur envoyant le message.';
$string['proactive_course_completed'] = 'L\'apprenant a terminé le cours. Félicite-le chaleureusement.';
$string['proactive_default_name'] = 'là';
$string['proactive_first_visit'] = 'Accueille l\'apprenant par son prénom ({$a->name}). C\'est sa première visite dans ce cours. Envoie un message de bienvenue bref et chaleureux et propose de l\'aider à démarrer.';
$string['proactive_guide_started'] = 'L\'apprenant vient d\'entrer en mode socratique (Guide-moi). Analyse la conversation jusqu\'ici et le contenu de ce cours. Lance un dialogue socratique sur tout sujet pertinent dans le contexte du cours : pose une question ciblée qui exige une réponse. Ne fais pas de cours magistral et ne donne pas la réponse complète. Attends la réponse de l\'apprenant.';
$string['proactive_quiz_graded'] = 'L\'apprenant a terminé le quiz « {$a->quizname} » avec une note de {$a->grade}/{$a->maxgrade}. Reconnais son résultat et encourage-le.';
$string['proactive_return_visit_delighted'] = 'L\'apprenant poursuit ce cours aujourd\'hui. Envoie un accueil particulièrement chaleureux et enthousiaste — joyeux et motivant. Ne fais aucune référence à son absence, au temps écoulé ou au fait qu\'il revient. Concentre-toi sur le saluer et l\'encourager à continuer le cours.';
$string['proactive_return_visit_enthusiastic'] = 'L\'apprenant poursuit ce cours aujourd\'hui. Envoie un accueil chaleureux et enthousiaste, avec une énergie positive. Ne mentionne pas depuis combien de temps il n\'était pas là ni n\'utilise de formules du type « bon retour » — salue-le et aide-le à reprendre où il en était.';
$string['proactive_return_visit_warm'] = 'L\'apprenant poursuit ce cours aujourd\'hui. Envoie un accueil bref et chaleureux. Ne mentionne pas son absence ni n\'utilise de formules du type « bon retour » — salue-le naturellement et propose de l\'aider à continuer.';
$string['quiz_difficulty_easy'] = 'Facile';
$string['quiz_difficulty_hard'] = 'Difficile';
$string['quiz_difficulty_medium'] = 'Moyen';
$string['quiz_exit'] = 'Quitter le quiz';
$string['quiz_generate_error'] = 'Impossible de générer le quiz d\'entraînement. Veuillez réessayer.';
$string['quiz_generating'] = 'Génération de votre quiz d\'entraînement…';
$string['quiz_me'] = 'Teste-moi';
$string['quiz_panel_exit_fullscreen'] = 'Quitter le plein écran';
$string['quiz_panel_fullscreen'] = 'Plein écran';
$string['quiz_review_ai_instructions'] = '[Revue du quiz d\'entraînement] J\'ai terminé le quiz d\'entraînement « {$a->title} » avec un meilleur score de {$a->score}/{$a->total}. Utilise les résultats structurés des questions dans ce message. Félicite-moi si j\'ai bien réussi. Si mon score est faible ou si j\'ai raté des questions, sois encourageant et bienveillant — aide-moi à rester motivé. Suggère des sujets ou du contenu de cours à réviser pour combler mes lacunes, et recommande des prochaines étapes concrètes. Garde ta réponse ciblée et utile.';
$string['quiz_review_best_score'] = 'Meilleur score : {$a->score}/{$a->total} ({$a->percent} %)';
$string['quiz_review_correct'] = 'Correct';
$string['quiz_review_correct_answer'] = 'Bonne réponse';
$string['quiz_review_exit_score'] = 'Cette tentative : {$a->score}/{$a->total} ({$a->percent} %)';
$string['quiz_review_feedback'] = 'Retour';
$string['quiz_review_incorrect'] = 'Incorrect';
$string['quiz_review_retake'] = 'Refaire le quiz';
$string['quiz_review_your_answer'] = 'Votre réponse';
$string['quiz_setup_cancel'] = 'Annuler';
$string['quiz_setup_count'] = 'Nombre de questions';
$string['quiz_setup_difficulty'] = 'Difficulté';
$string['quiz_setup_loading'] = 'Chargement des sujets…';
$string['quiz_setup_start'] = 'Commencer le quiz';
$string['quiz_setup_title'] = 'Quiz d\'entraînement';
$string['quiz_setup_topic'] = 'Sujet';
$string['quizrestriction'] = "Dixeo Tuteur n'est pas disponible sur les pages de quiz.";
$string['resize_panel'] = 'Redimensionner le panneau du tuteur';
$string['retry'] = 'Réessayer';
$string['send'] = 'Envoyer';
$string['setting_displaymode'] = 'Mode d\'affichage';
$string['setting_displaymode_desc'] = 'Afficher le tuteur dans le tiroir de blocs (panneau latéral) ou dans une fenêtre flottante ouverte par un bouton.';
$string['setting_displaymode_drawer'] = 'Dans le tiroir de blocs';
$string['setting_displaymode_popup'] = 'Dans une fenêtre flottante';
$string['setting_enabledmodes'] = 'Modes de tuteur activés';
$string['setting_enabledmodes_desc'] = 'Sélectionnez les modes de tuteur optionnels disponibles. Le mode standard est toujours activé et ne peut pas être désactivé.';
$string['setting_excludedmodules'] = 'Types de modules exclus';
$string['setting_excludedmodules_desc'] = 'Liste de types de modules d\'activité séparés par des virgules où le tuteur doit être masqué (ex : quiz,simplequiz2). Le tuteur n\'apparaîtra pas sur les pages de ces types d\'activité.';
$string['setup_language'] = 'Langue';
$string['talktotutor'] = 'Parler au tuteur';
$string['teach_generate_error'] = 'Impossible de générer la leçon. Veuillez réessayer.';
$string['teach_generating'] = 'Génération de votre leçon personnalisée…';
$string['teach_lesson_close'] = 'Fermer la leçon';
$string['teach_lesson_fullscreen'] = 'Plein écran';
$string['teach_lesson_tts_play'] = 'Lire la leçon à voix haute';
$string['teach_lesson_tts_stop'] = 'Arrêter la lecture';
$string['teach_setup_cancel'] = 'Annuler';
$string['teach_setup_loading'] = 'Chargement des sujets…';
$string['teach_setup_prompt'] = 'Décrivez ce que vous voulez apprendre';
$string['teach_setup_prompt_placeholder'] = 'Par exemple : expliquez-moi ce sujet en termes plus simples, ou approfondissez un aspect…';
$string['teach_setup_prompt_required'] = 'Décrivez ce que vous voulez apprendre.';
$string['teach_setup_start'] = 'Créer la leçon';
$string['teach_setup_title'] = 'Leçon personnalisée';
$string['teach_setup_topic'] = 'Sujet';
$string['timeout_message'] = "La réponse prend plus de temps que prévu. L'assistant travaille peut-être encore sur votre demande.";
$string['tooltip_hide_tutor'] = 'Fermer Ed';
$string['tooltip_open_tutor'] = 'Demander à Ed';
$string['unknownerror'] = "Une erreur inconnue s'est produite.";
$string['yesterday'] = 'hier';
