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
 * Spanish language strings for the Dixeo Student Tutor block.
 *
 * @package    block_dixeo_tutor
 * @copyright  2025 Edunao SAS (contact@edunao.com)
 * @author     Pierre FACQ <pierre.facq@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['aria_assistant_message'] = 'Mensaje del asistente';
$string['aria_chat_messages'] = 'Mensajes del chat';
$string['aria_send_message'] = 'Enviar mensaje';
$string['aria_sender_assistant'] = 'Asistente';
$string['aria_sender_you'] = 'Tú';
$string['aria_sent_at'] = 'Enviado a las {$a}';
$string['aria_skip_to_input'] = 'Ir al campo de mensaje';
$string['aria_type_message'] = 'Escribe tu mensaje';
$string['aria_your_message'] = 'Tu mensaje';
$string['assistanttitle'] = 'Pregunta a Ed';
$string['check_for_updates'] = 'Buscar actualizaciones';
$string['connection_lost'] = 'Conexión perdida. Intentando reconectar...';
$string['deleteconversation'] = 'Eliminar mi conversación';
$string['deleteconversationconfirm'] = 'Esto elimina de forma permanente toda su conversación con el tutor en este curso. Se retira del servicio Dixeo de inmediato, y del proveedor de IA que generó las respuestas poco después. No se puede deshacer.';
$string['deleteconversationfailed'] = 'No se ha podido eliminar su conversación. No se ha modificado nada; inténtelo de nuevo.';
$string['dixeo_tutor:addinstance'] = 'Agregar un nuevo bloque del Tutor Estudiantil Dixeo';
$string['dixeo_tutor:talktotutor'] = 'Interactuar con el Tutor de IA';
$string['editingmode'] = 'Tutor Estudiantil Dixeo no está disponible en modo de edición.';
$string['error_apierror'] = 'Lo sentimos, hubo un problema de comunicación con el servicio de IA.';
$string['error_check_updates'] = 'No se pudieron verificar las actualizaciones. Por favor, actualice la página.';
$string['error_job_access'] = 'No se pudo obtener el estado del trabajo.';
$string['error_network'] = 'Ocurrió un error de red. Por favor, verifique su conexión e inténtelo de nuevo.';
$string['error_timeout'] = 'La solicitud ha expirado. Por favor, verifique su conexión e inténtelo de nuevo.';
$string['errorsendmessage'] = 'Lo sentimos, hubo un error al enviar su mensaje. Por favor, inténtelo de nuevo.';
$string['eventconversationdeleted'] = 'Conversación del tutor Dixeo eliminada';
$string['eventconversationdeleteddesc'] = 'El usuario con id \'{$a->userid}\' eliminó su conversación del tutor en el curso \'{$a->courseid}\' (deleted={$a->deleted}).';
$string['eventconversationviewed'] = 'Conversación del tutor Dixeo consultada';
$string['eventconversationvieweddesc'] = 'El usuario con id \'{$a->userid}\' consultó la conversación del tutor en el curso \'{$a->courseid}\' (messagecount={$a->messagecount}, sinceid=\'{$a->sinceid}\').';
$string['eventjobstatusviewed'] = 'Estado del trabajo del tutor Dixeo consultado';
$string['eventjobstatusvieweddesc'] = 'El usuario con id \'{$a->userid}\' consultó el estado del trabajo del tutor en el curso \'{$a->courseid}\' (jobid=\'{$a->jobid}\', status=\'{$a->status}\').';
$string['eventmessagesent'] = 'Mensaje del tutor Dixeo enviado';
$string['eventmessagesentdesc'] = 'El usuario con id \'{$a->userid}\' envió un mensaje al tutor en el curso \'{$a->courseid}\' (jobid=\'{$a->jobid}\').';
$string['eventprivacyrequestfailed'] = 'Solicitud de privacidad del tutor Dixeo fallida';
$string['eventprivacyrequestfaileddesc'] = 'Una solicitud de privacidad no pudo contactar con la API de Dixeo para el usuario con id \'{$a->userid}\' (errorcode=\'{$a->errorcode}\').';
$string['filecountlimit'] = 'El tutor IA está limitado a 150 archivos por curso (actualmente {$a} archivos). Por favor, reduzca el número de archivos si es necesario.';
$string['message_too_long'] = 'El mensaje no puede exceder {$a} caracteres.';
$string['messageprovider:privacyfailure'] = 'Solicitudes de privacidad del tutor Dixeo fallidas';
$string['notenrolled'] = 'Debe estar inscrito en este curso para usar el tutor.';
$string['placeholder'] = 'Escribe tu mensaje...';
$string['pluginname'] = 'Tutor Estudiantil Dixeo';
$string['privacy:metadata:courseid'] = 'El ID del curso en el que está inscrito el usuario.';
$string['privacy:metadata:externalpurpose'] = 'Los mensajes del usuario, el contexto del curso y una ruta de página minimizada del sitio se envían a la API de Dixeo (a través de local_dixeo) para generar respuestas del tutor IA. Las conversaciones las conserva el servicio Dixeo; pueden exportarse y eliminarse bajo petición, y la eliminación retira el registro de Dixeo de inmediato y la copia en poder del proveedor de IA poco después.';
$string['privacy:metadata:message'] = 'El contenido del mensaje enviado por el usuario.';
$string['privacy:metadata:pageurl'] = 'Una ruta URL del sitio Moodle como contexto de página al enviar el mensaje (restringida a este sitio; se eliminan cadenas de consulta y fragmentos).';
$string['privacy:metadata:userid'] = 'El ID del usuario que envía el mensaje.';
$string['privacy:path:conversation'] = 'Conversación del tutor';
$string['privacyfailure_body'] = 'La solicitud de privacidad del tutor Dixeo relativa a {$a} no pudo completarse: la API de Dixeo no estaba disponible. Vuelva a lanzar la solicitud cuando la API se haya restablecido.';
$string['privacyfailure_subject'] = 'Solicitud de privacidad del tutor Dixeo fallida';
$string['quizrestriction'] = 'Tutor Estudiantil Dixeo no está disponible en las páginas de cuestionarios.';
$string['resize_panel'] = 'Cambiar tamaño del panel del tutor';
$string['retry'] = 'Reintentar';
$string['send'] = 'Enviar';
$string['setting_displaymode'] = 'Modo de visualización';
$string['setting_displaymode_desc'] = 'Mostrar el tutor en el cajón de bloques (panel lateral) o en una ventana emergente flotante abierta con un botón.';
$string['setting_displaymode_drawer'] = 'En el cajón de bloques';
$string['setting_displaymode_popup'] = 'En una ventana emergente';
$string['setting_excludedmodules'] = 'Tipos de módulos excluidos';
$string['setting_excludedmodules_desc'] = 'Lista de tipos de módulos de actividad separados por comas donde el tutor debe ocultarse (ej: quiz,simplequiz2). El tutor no aparecerá en las páginas de estos tipos de actividad.';
$string['talktotutor'] = 'Hablar con el tutor';
$string['task_erase_conversations'] = 'Eliminar las conversaciones del tutor Dixeo';
$string['timeout_message'] = 'La respuesta está tardando más de lo esperado. El asistente puede estar trabajando aún en su solicitud.';
$string['tooltip_hide_tutor'] = 'Cerrar Ed';
$string['tooltip_open_tutor'] = 'Preguntar a Ed';
$string['tutorpresentation'] = '¡Hola! Soy Ed, tu tutor de IA. ¿Cómo puedo ayudarte con este curso?';
$string['unknownerror'] = 'Ocurrió un error desconocido.';
$string['yesterday'] = 'ayer';
