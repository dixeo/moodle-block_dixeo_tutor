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
$string['aria_send_message'] = 'Enviar mensagem';
$string['aria_sender_assistant'] = 'Assistente';
$string['aria_sender_you'] = 'Você';
$string['aria_sent_at'] = 'Enviado às {$a}';
$string['aria_skip_to_input'] = 'Ir para o campo de mensagem';
$string['aria_type_message'] = 'Escreva a sua mensagem';
$string['aria_your_message'] = 'A sua mensagem';
$string['assistanttitle'] = 'Pergunte ao Ed';
$string['check_for_updates'] = 'Verificar atualizações';
$string['connection_lost'] = 'Ligação perdida. A tentar reconectar...';
$string['dixeo_tutor:addinstance'] = 'Adicionar um novo bloco Tutor Dixeo para Estudantes';
$string['dixeo_tutor:talktotutor'] = 'Interagir com o Tutor de IA';
$string['editingmode'] = 'O Tutor Dixeo não está disponível no modo de edição.';
$string['error_apierror'] = 'Ocorreu um problema na comunicação com o serviço de IA.';
$string['error_check_updates'] = 'Não foi possível verificar atualizações. Por favor, atualize a página.';
$string['error_job_access'] = 'Não foi possível obter o estado do trabalho.';
$string['error_network'] = 'Ocorreu um erro de rede. Por favor, verifique a sua ligação e tente novamente.';
$string['error_timeout'] = 'O pedido expirou. Por favor, verifique a sua ligação e tente novamente.';
$string['errorsendmessage'] = 'Ocorreu um erro ao enviar a sua mensagem. Por favor, tente novamente.';
$string['eventconversationviewed'] = 'Conversa do tutor Dixeo visualizada';
$string['eventconversationvieweddesc'] = 'O utilizador com id \'{$a->userid}\' visualizou a conversa do tutor na disciplina \'{$a->courseid}\' (messagecount={$a->messagecount}, sinceid=\'{$a->sinceid}\').';
$string['eventjobstatusviewed'] = 'Estado da tarefa do tutor Dixeo visualizado';
$string['eventjobstatusvieweddesc'] = 'O utilizador com id \'{$a->userid}\' visualizou o estado da tarefa do tutor na disciplina \'{$a->courseid}\' (jobid=\'{$a->jobid}\', status=\'{$a->status}\').';
$string['eventmessagesent'] = 'Mensagem do tutor Dixeo enviada';
$string['eventmessagesentdesc'] = 'O utilizador com id \'{$a->userid}\' enviou uma mensagem ao tutor na disciplina \'{$a->courseid}\' (jobid=\'{$a->jobid}\').';
$string['filecountlimit'] = 'O tutor de IA está limitado a 150 ficheiros por disciplina (atualmente {$a} ficheiros). Por favor, reduza o número de ficheiros se necessário.';
$string['message_too_long'] = 'A mensagem não pode exceder {$a} caracteres.';
$string['notenrolled'] = 'Tem de estar inscrito nesta disciplina para usar o tutor.';
$string['placeholder'] = 'Escreva a sua mensagem...';
$string['pluginname'] = 'Tutor Dixeo para Estudantes';
$string['privacy:metadata'] = 'O bloco Dixeo Student Tutor armazena contexto proativo em fila (id de utilizador, id de disciplina, texto da mensagem) na base de dados Moodle até ser enviado. As conversas do tutor são processadas pelo local_dixeo e transferidas para a API Dixeo. A retenção, exportação e eliminação de conversas regem-se pelo local_dixeo e pelo acordo do site com o serviço Dixeo; as linhas proativas em fila são descritas em privacy:metadata:pendingpurpose.';
$string['privacy:metadata:courseid'] = 'O ID da disciplina em que o utilizador está inscrito.';
$string['privacy:metadata:externalpurpose'] = 'As mensagens do utilizador, o contexto da disciplina e um caminho de página minimizado do site são enviados para a API Dixeo (via local_dixeo) para gerar respostas do tutor de IA. Este bloco não armazena conversas localmente e, por isso, não exporta nem elimina esses dados; esses controlos devem ser fornecidos pelo local_dixeo e pelo contrato da API Dixeo.';
$string['privacy:metadata:message'] = 'O conteúdo da mensagem enviada pelo utilizador.';
$string['privacy:metadata:pageurl'] = 'Um caminho de URL do site Moodle como contexto de página ao enviar a mensagem (restrito a este site; strings de consulta e fragmentos são removidos).';
$string['privacy:metadata:pending_courseid'] = 'A disciplina a que pertence o contexto proativo em fila.';
$string['privacy:metadata:pending_message'] = 'Linhas de contexto na primeira pessoa ainda não enviadas ao tutor.';
$string['privacy:metadata:pending_userid'] = 'O utilizador a que pertence o contexto proativo em fila.';
$string['privacy:metadata:pendingpurpose'] = 'Armazena pedidos proativos do tutor em fila até serem enviados para a API Dixeo.';
$string['privacy:metadata:userid'] = 'O ID do utilizador que envia a mensagem.';
$string['proactive_course_completed'] = 'Concluí a disciplina. Parabéns-me';
$string['proactive_default_name'] = 'aí';
$string['proactive_first_visit'] = 'Olá, o meu nome é {$a->name}. Esta é a primeira vez que abro esta disciplina. Envia-me uma mensagem de boas-vindas.';
$string['proactive_quiz_graded'] = 'Concluí o questionário «{$a->quizname}» com uma nota de {$a->grade}/{$a->maxgrade}.';
$string['proactive_return_visit'] = 'Estou a voltar a esta disciplina depois de {$a}. Dá-me as boas-vindas de volta';
$string['quizrestriction'] = 'O Tutor Dixeo não está disponível nas páginas de questionários.';
$string['resize_panel'] = 'Redimensionar painel do tutor';
$string['retry'] = 'Tentar novamente';
$string['send'] = 'Enviar';
$string['setting_displaymode'] = 'Modo de exibição';
$string['setting_displaymode_desc'] = 'Mostrar o tutor na gaveta de blocos (painel lateral) ou numa janela flutuante aberta por um botão.';
$string['setting_displaymode_drawer'] = 'Na gaveta de blocos';
$string['setting_displaymode_popup'] = 'Numa janela flutuante';
$string['setting_excludedmodules'] = 'Tipos de módulos excluídos';
$string['setting_excludedmodules_desc'] = 'Lista separada por vírgulas dos tipos de módulos de atividade onde o tutor deve ser ocultado (ex.: quiz, simplequiz2). O tutor não aparecerá nas páginas destes tipos de atividade.';
$string['talktotutor'] = 'Falar com o tutor';
$string['timeout_message'] = 'A resposta está a demorar mais do que o esperado. O assistente pode ainda estar a processar o seu pedido.';
$string['tooltip_hide_tutor'] = 'Fechar Ed';
$string['tooltip_open_tutor'] = 'Perguntar ao Ed';
$string['unknownerror'] = 'Ocorreu um erro desconhecido.';
$string['yesterday'] = 'ontem';
