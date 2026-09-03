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
 * Portuguese language strings for the Dixeo Student Tutor block.
 *
 * @package    block_dixeo_tutor
 * @copyright  2025 Edunao SAS (contact@edunao.com)
 * @author     Pierre FACQ <pierre.facq@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['aria_assistant_message'] = 'Mensagem do assistente';
$string['aria_chat_messages'] = 'Mensagens do chat';
$string['aria_copy_message'] = 'Copiar mensagem';
$string['aria_load_older_messages'] = 'Carregar mensagens anteriores';
$string['aria_message_copied'] = 'Copiado';
$string['aria_read_message'] = 'Ler mensagem em voz alta';
$string['aria_send_message'] = 'Enviar mensagem';
$string['aria_sender_assistant'] = 'Assistente';
$string['aria_sender_you'] = 'Você';
$string['aria_skip_to_input'] = 'Ir para o campo de mensagem';
$string['aria_stop_reading'] = 'Parar leitura';
$string['aria_type_message'] = 'Escreva a sua mensagem';
$string['aria_your_message'] = 'A sua mensagem';
$string['assistanttitle'] = 'Pergunte ao Ed';
$string['check_for_updates'] = 'Verificar atualizações';
$string['connection_lost'] = 'Ligação perdida. A tentar reconectar...';
$string['custom_lesson_label'] = 'Lição personalizada';
$string['custom_lesson_view'] = 'Ver lição';
$string['deleteconversation'] = 'Eliminar a minha conversa';
$string['deleteconversationconfirm'] = 'Esta ação elimina definitivamente toda a sua conversa com o tutor nesta disciplina. É removida do serviço Dixeo de imediato e, pouco depois, do fornecedor de IA que gerou as respostas. Não pode ser anulada.';
$string['deleteconversationfailed'] = 'Não foi possível eliminar a sua conversa. Nada foi alterado; tente novamente.';
$string['dixeo_tutor:addinstance'] = 'Adicionar um novo bloco Tutor Dixeo para Estudantes';
$string['dixeo_tutor:talktotutor'] = 'Interagir com o Tutor de IA';
$string['editingmode'] = 'O Tutor Dixeo não está disponível no modo de edição.';
$string['error_apierror'] = 'Ocorreu um problema na comunicação com o serviço de IA.';
$string['error_check_updates'] = 'Não foi possível verificar atualizações. Por favor, atualize a página.';
$string['error_job_access'] = 'Não foi possível obter o estado do trabalho.';
$string['error_mode_not_available'] = 'O modo de tutor selecionado não está disponível.';
$string['error_network'] = 'Ocorreu um erro de rede. Por favor, verifique a sua ligação e tente novamente.';
$string['error_timeout'] = 'O pedido expirou. Por favor, verifique a sua ligação e tente novamente.';
$string['errorsendmessage'] = 'Ocorreu um erro ao enviar a sua mensagem. Por favor, tente novamente.';
$string['eventconversationdeleted'] = 'Conversa do tutor Dixeo eliminada';
$string['eventconversationdeleteddesc'] = 'O utilizador com id \'{$a->userid}\' eliminou a sua conversa do tutor na disciplina \'{$a->courseid}\' (deleted={$a->deleted}).';
$string['eventconversationviewed'] = 'Conversa do tutor Dixeo visualizada';
$string['eventconversationvieweddesc'] = 'O utilizador com id \'{$a->userid}\' visualizou a conversa do tutor na disciplina \'{$a->courseid}\' (messagecount={$a->messagecount}, sinceid=\'{$a->sinceid}\').';
$string['eventjobstatusviewed'] = 'Estado da tarefa do tutor Dixeo visualizado';
$string['eventjobstatusvieweddesc'] = 'O utilizador com id \'{$a->userid}\' visualizou o estado da tarefa do tutor na disciplina \'{$a->courseid}\' (jobid=\'{$a->jobid}\', status=\'{$a->status}\').';
$string['eventmessagesent'] = 'Mensagem do tutor Dixeo enviada';
$string['eventmessagesentdesc'] = 'O utilizador com id \'{$a->userid}\' enviou uma mensagem ao tutor na disciplina \'{$a->courseid}\' (jobid=\'{$a->jobid}\').';
$string['eventprivacyrequestfailed'] = 'Pedido de privacidade do tutor Dixeo falhou';
$string['eventprivacyrequestfaileddesc'] = 'Um pedido de privacidade não conseguiu contactar a API Dixeo para o utilizador com id \'{$a->userid}\' (errorcode=\'{$a->errorcode}\').';
$string['guide_banner_exit'] = 'Sair do modo socrático';
$string['guide_banner_title'] = 'Modo socrático';
$string['guide_completion_exit'] = 'Sair';
$string['guide_completion_restart'] = 'Iniciar uma nova sessão';
$string['guide_review_back'] = 'Voltar à conversa';
$string['guide_review_session'] = 'Rever sessão';
$string['guide_setup_cancel'] = 'Cancelar';
$string['guide_setup_error'] = 'Não foi possível iniciar o modo guia. Tente novamente.';
$string['guide_setup_instructions'] = 'No modo Guia-me, o tutor usa um diálogo socrático: faz perguntas para o ajudar a raciocinar o tema em vez de receber primeiro a resposta. Descreva abaixo do que precisa de ajuda e responda pelas suas palavras durante a sessão.';
$string['guide_setup_prompt'] = 'Em que precisa de orientação?';
$string['guide_setup_prompt_placeholder'] = 'Por exemplo: não compreendo como funciona a fotossíntese…';
$string['guide_setup_start'] = 'Iniciar sessão';
$string['guide_setup_starting'] = 'A preparar a sua sessão socrática…';
$string['guide_setup_title'] = 'Guie-me';
$string['guide_topic_label'] = 'Sessão guiada';
$string['load_older_messages'] = 'Carregar mensagens anteriores';
$string['message_too_long'] = 'A mensagem não pode exceder {$a} caracteres.';
$string['messageprovider:privacyfailure'] = 'Pedidos de privacidade do tutor Dixeo falhados';
$string['mode_selector_title'] = 'Modo do tutor';
$string['modeguide'] = 'Guie-me';
$string['modeguide_desc'] = 'O tutor usa uma abordagem socrática e guia-o com perguntas.';
$string['modenormal'] = 'Padrão';
$string['modenormal_desc'] = 'Faça perguntas e obtenha respostas diretas do tutor.';
$string['modequiz'] = 'Testa-me';
$string['modequiz_desc'] = 'Pratique com um questionário gerado a partir do conteúdo da disciplina.';
$string['modeteach'] = 'Ensina-me';
$string['modeteach_desc'] = 'Peça uma lição personalizada sobre um tema à sua escolha.';
$string['notenrolled'] = 'Tem de estar inscrito nesta disciplina para usar o tutor.';
$string['placeholder'] = 'Escreva a sua mensagem...';
$string['pluginname'] = 'Tutor Dixeo para Estudantes';
$string['practice_quiz_label'] = 'Questionário de prática';
$string['privacy:metadata'] = 'O bloco Dixeo Student Tutor armazena contexto proativo em fila (id de utilizador, id de disciplina, texto da mensagem) na base de dados Moodle até ser enviado. As conversas do tutor são processadas pelo local_dixeo e transferidas para a API Dixeo. A retenção, exportação e eliminação de conversas regem-se pelo local_dixeo e pelo acordo do site com o serviço Dixeo; as linhas proativas em fila são descritas em privacy:metadata:pendingpurpose.';
$string['privacy:metadata:courseid'] = 'O ID da disciplina em que o utilizador está inscrito.';
$string['privacy:metadata:externalpurpose'] = 'As mensagens do utilizador, o contexto da disciplina e um caminho de página minimizado do site são enviados para a API Dixeo (via local_dixeo) para gerar respostas do tutor de IA. As conversas são conservadas pelo serviço Dixeo; podem ser exportadas e eliminadas mediante pedido, e a eliminação remove o registo Dixeo de imediato e a cópia detida pelo fornecedor de IA pouco depois.';
$string['privacy:metadata:lastread'] = 'A hora da última mensagem do tutor que leu em cada disciplina (para indicadores de não lidas).';
$string['privacy:metadata:message'] = 'O conteúdo da mensagem enviada pelo utilizador.';
$string['privacy:metadata:pageurl'] = 'Um caminho de URL do site Moodle como contexto de página ao enviar a mensagem (restrito a este site; strings de consulta e fragmentos são removidos).';
$string['privacy:metadata:pending_courseid'] = 'A disciplina a que pertence o contexto proativo em fila.';
$string['privacy:metadata:pending_message'] = 'Linhas de contexto na primeira pessoa ainda não enviadas ao tutor.';
$string['privacy:metadata:pending_userid'] = 'O utilizador a quem pertence o contexto proativo em fila.';
$string['privacy:metadata:pendingpurpose'] = 'Armazena pedidos proativos do tutor em fila até serem enviados para a API Dixeo.';
$string['privacy:metadata:tutormode'] = 'O seu modo de tutor selecionado (padrão, guia, questionário ou ensino) em cada disciplina.';
$string['privacy:metadata:tutormodeactivity'] = 'A hora da sua última mensagem ao tutor ou alteração de modo em guia, questionário ou ensina-me, usada para regressar ao modo padrão após uma hora de inatividade.';
$string['privacy:metadata:userid'] = 'O ID do utilizador que envia a mensagem.';
$string['privacy:path:conversation'] = 'Conversa do tutor';
$string['privacyfailure_body'] = 'O pedido de privacidade do tutor Dixeo relativo a {$a} não pôde ser concluído: a API Dixeo estava inacessível. Volte a executar o pedido assim que a API estiver restabelecida.';
$string['privacyfailure_subject'] = 'Pedido de privacidade do tutor Dixeo falhou';
$string['proactive_default_name'] = 'aí';
$string['quiz_difficulty_easy'] = 'Fácil';
$string['quiz_difficulty_hard'] = 'Difícil';
$string['quiz_difficulty_medium'] = 'Médio';
$string['quiz_exit'] = 'Sair do questionário';
$string['quiz_generate_error'] = 'Não foi possível gerar o questionário de prática. Por favor, tente novamente.';
$string['quiz_generating'] = 'A gerar o seu questionário de prática…';
$string['quiz_panel_exit_fullscreen'] = 'Sair do ecrã inteiro';
$string['quiz_panel_fullscreen'] = 'Ecrã inteiro';
$string['quiz_review_best_score'] = 'Melhor pontuação: {$a->score}/{$a->total} ({$a->percent} %)';
$string['quiz_review_correct'] = 'Correta';
$string['quiz_review_correct_answer'] = 'Resposta correta';
$string['quiz_review_details'] = 'Ver resultados do questionário';
$string['quiz_review_exit_score'] = 'Esta tentativa: {$a->score}/{$a->total} ({$a->percent} %)';
$string['quiz_review_feedback'] = 'Feedback';
$string['quiz_review_incorrect'] = 'Incorreta';
$string['quiz_review_retake'] = 'Repetir questionário';
$string['quiz_review_your_answer'] = 'A sua resposta';
$string['quiz_setup_cancel'] = 'Cancelar';
$string['quiz_setup_count'] = 'Número de perguntas';
$string['quiz_setup_difficulty'] = 'Dificuldade';
$string['quiz_setup_loading'] = 'A carregar tópicos…';
$string['quiz_setup_start'] = 'Iniciar questionário';
$string['quiz_setup_title'] = 'Questionário de prática';
$string['quiz_setup_topic'] = 'Tópico';
$string['resize_panel'] = 'Redimensionar painel do tutor';
$string['retry'] = 'Tentar novamente';
$string['send'] = 'Enviar';
$string['setting_displaymode'] = 'Modo de exibição';
$string['setting_displaymode_desc'] = 'Mostrar o tutor na gaveta de blocos (painel lateral) ou numa janela flutuante aberta por um botão.';
$string['setting_displaymode_drawer'] = 'Na gaveta de blocos';
$string['setting_displaymode_popup'] = 'Numa janela flutuante';
$string['setting_enabledmodes'] = 'Modos de tutor ativados';
$string['setting_enabledmodes_desc'] = 'Selecione quais modos de tutor opcionais estão disponíveis. O modo padrão está sempre ativado e não pode ser desativado.';
$string['setting_excludedmodules'] = 'Tipos de módulos excluídos';
$string['setting_excludedmodules_desc'] = 'Lista separada por vírgulas dos tipos de módulos de atividade onde o tutor deve ser ocultado (ex.: quiz, simplequiz2). O tutor não aparecerá nas páginas destes tipos de atividade.';
$string['setup_language'] = 'Idioma';
$string['talktotutor'] = 'Falar com o tutor';
$string['task_erase_conversations'] = 'Eliminar as conversas do tutor Dixeo';
$string['teach_generate_error'] = 'Não foi possível gerar a lição. Tente novamente.';
$string['teach_generating'] = 'A gerar a sua lição personalizada…';
$string['teach_lesson_close'] = 'Fechar lição';
$string['teach_lesson_fullscreen'] = 'Ecrã inteiro';
$string['teach_lesson_tts_play'] = 'Ler lição em voz alta';
$string['teach_lesson_tts_stop'] = 'Parar leitura';
$string['teach_setup_cancel'] = 'Cancelar';
$string['teach_setup_loading'] = 'A carregar tópicos…';
$string['teach_setup_prompt'] = 'Descreva o que quer aprender';
$string['teach_setup_prompt_placeholder'] = 'Por exemplo: explique este tópico em termos mais simples, ou aprofunde um aspeto…';
$string['teach_setup_start'] = 'Criar lição';
$string['teach_setup_title'] = 'Lição personalizada';
$string['teach_setup_topic'] = 'Tópico';
$string['timeout_message'] = 'A resposta está a demorar mais do que o esperado. O assistente pode ainda estar a processar o seu pedido.';
$string['tooltip_hide_tutor'] = 'Fechar Ed';
$string['tooltip_open_tutor'] = 'Perguntar ao Ed';
$string['unknownerror'] = 'Ocorreu um erro desconhecido.';
