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
$string['aria_copy_message'] = 'Copiar mensaje';
$string['aria_load_older_messages'] = 'Cargar mensajes anteriores';
$string['aria_message_copied'] = 'Copiado';
$string['aria_read_message'] = 'Leer mensaje en voz alta';
$string['aria_send_message'] = 'Enviar mensaje';
$string['aria_sender_assistant'] = 'Asistente';
$string['aria_sender_you'] = 'Tú';
$string['aria_skip_to_input'] = 'Ir al campo de mensaje';
$string['aria_stop_reading'] = 'Detener lectura';
$string['aria_type_message'] = 'Escribe tu mensaje';
$string['aria_your_message'] = 'Tu mensaje';
$string['assistanttitle'] = 'Pregunta a Ed';
$string['check_for_updates'] = 'Buscar actualizaciones';
$string['connection_lost'] = 'Conexión perdida. Intentando reconectar...';
$string['custom_lesson_label'] = 'Lección personalizada';
$string['custom_lesson_view'] = 'Ver lección';
$string['deleteconversation'] = 'Eliminar mi conversación';
$string['deleteconversationconfirm'] = 'Esto elimina de forma permanente toda su conversación con el tutor en este curso. Se retira del servicio Dixeo de inmediato, y del proveedor de IA que generó las respuestas poco después. No se puede deshacer.';
$string['deleteconversationfailed'] = 'No se ha podido eliminar su conversación. No se ha modificado nada; inténtelo de nuevo.';
$string['disabled_tutor_course'] = 'Este bloque no está disponible porque el tutor se ha desactivado para este curso.';
$string['displaymode'] = 'Modo de visualización';
$string['displaymode_drawer'] = 'En el cajón de bloques';
$string['displaymode_help'] = 'Mostrar el tutor en el cajón de bloques (panel lateral) o en una ventana emergente flotante abierta con un botón.';
$string['displaymode_popup'] = 'En una ventana emergente';
$string['dixeo_tutor:addinstance'] = 'Agregar un nuevo bloque del Tutor Estudiantil Dixeo';
$string['dixeo_tutor:talktotutor'] = 'Interactuar con el Tutor de IA';
$string['editingmode'] = 'Tutor Estudiantil Dixeo no está disponible en modo de edición.';
$string['enabledmodes'] = 'Modos de tutor habilitados';
$string['enabledmodes_guide'] = 'Guíame';
$string['enabledmodes_help'] = 'Seleccione qué modos de tutor opcionales están disponibles. El modo estándar siempre está habilitado y no se puede desactivar.';
$string['enabledmodes_quiz'] = 'Ponme a prueba';
$string['enabledmodes_teach'] = 'Enséñame';
$string['error_apierror'] = 'Lo sentimos, hubo un problema de comunicación con el servicio de IA.';
$string['error_check_updates'] = 'No se pudieron verificar las actualizaciones. Por favor, actualice la página.';
$string['error_job_access'] = 'No se pudo obtener el estado del trabajo.';
$string['error_mode_not_available'] = 'El modo de tutor seleccionado no está disponible.';
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
$string['excludedmodules'] = 'Tipos de módulos excluidos';
$string['excludedmodules_help'] = 'Lista de tipos de módulos de actividad separados por comas donde el tutor debe ocultarse (ej: quiz,simplequiz2). El tutor no aparecerá en las páginas de estos tipos de actividad.';
$string['filecountlimit'] = 'El tutor IA está limitado a 150 archivos por curso (actualmente {$a} archivos). Por favor, reduzca el número de archivos si es necesario.';
$string['guide_banner_exit'] = 'Salir del modo socrático';
$string['guide_banner_title'] = 'Modo socrático';
$string['guide_completion_exit'] = 'Salir';
$string['guide_completion_restart'] = 'Iniciar una nueva sesión';
$string['guide_review_back'] = 'Volver a la conversación';
$string['guide_review_session'] = 'Revisar sesión';
$string['guide_setup_cancel'] = 'Cancelar';
$string['guide_setup_error'] = 'No se pudo iniciar el modo guía. Inténtalo de nuevo.';
$string['guide_setup_instructions'] = 'En el modo Guíame, el tutor usa un diálogo socrático: hace preguntas para que razones el tema en lugar de recibir primero la respuesta. Describe abajo en qué necesitas ayuda y responde con tus propias palabras durante la sesión.';
$string['guide_setup_prompt'] = '¿En qué necesitas orientación?';
$string['guide_setup_prompt_placeholder'] = 'Por ejemplo: no entiendo cómo funciona la fotosíntesis…';
$string['guide_setup_start'] = 'Iniciar sesión';
$string['guide_setup_starting'] = 'Preparando tu sesión socrática…';
$string['guide_setup_title'] = 'Guíame';
$string['guide_topic_label'] = 'Sesión guiada';
$string['load_older_messages'] = 'Cargar mensajes anteriores';
$string['message_too_long'] = 'El mensaje no puede exceder {$a} caracteres.';
$string['messageprovider:privacyfailure'] = 'Solicitudes de privacidad del tutor Dixeo fallidas';
$string['mode_selector_title'] = 'Modo del tutor';
$string['modeguide'] = 'Guíame';
$string['modeguide_desc'] = 'El tutor usa un enfoque socrático y te guía con preguntas.';
$string['modenormal'] = 'Estándar';
$string['modenormal_desc'] = 'Haz preguntas y obtén respuestas directas del tutor.';
$string['modequiz'] = 'Ponme a prueba';
$string['modequiz_desc'] = 'Practica con un cuestionario generado a partir del contenido del curso.';
$string['modeteach'] = 'Enséñame';
$string['modeteach_desc'] = 'Solicita una lección personalizada sobre el tema que elijas.';
$string['notice_coursecompleted_1'] = '¡Enhorabuena, {$a->name}, has completado {$a->course}! Es un gran logro.';
$string['notice_coursecompleted_2'] = '¡Bien hecho, {$a->name}! Has terminado {$a->course}. Sigo aquí si quieres repasar algo.';
$string['notice_coursecompleted_3'] = '¡Bravo, {$a->name}, curso completado! Si quieres volver sobre algún tema, solo tienes que preguntar.';
$string['notice_firstvisit_1'] = '¡Hola, {$a->name}! Soy Ed, tu tutor en este curso. Pregúntame lo que quieras cuando necesites ayuda.';
$string['notice_firstvisit_2'] = 'Hola, {$a->name}, soy Ed. Si algo de este curso no te queda claro, estoy aquí para explicártelo.';
$string['notice_firstvisit_3'] = '¡Hola, {$a->name}! Soy Ed, tu tutor del curso. ¿Te has atascado con un concepto o quieres comprobar lo que has entendido? Solo tienes que preguntar.';
$string['notice_quizgraded_high_1'] = '¡Muy bien, {$a->name}! {$a->grade}/{$a->maxgrade} en «{$a->quiz}». Dominas este tema.';
$string['notice_quizgraded_high_2'] = 'Excelente, {$a->name}: {$a->grade}/{$a->maxgrade} en «{$a->quiz}». ¡Sigue así!';
$string['notice_quizgraded_high_3'] = '¡Bien hecho, {$a->name}! Has obtenido {$a->grade}/{$a->maxgrade} en «{$a->quiz}».';
$string['notice_quizgraded_low_1'] = '{$a->name}, has obtenido {$a->grade}/{$a->maxgrade} en «{$a->quiz}». No te preocupes, es parte del aprendizaje. ¿Quieres que te diga qué repasar primero?';
$string['notice_quizgraded_low_2'] = 'Gracias por hacer «{$a->quiz}», {$a->name}. Tu {$a->grade}/{$a->maxgrade} muestra qué repasar: pregúntame y te indico la parte del curso adecuada.';
$string['notice_quizgraded_low_3'] = '{$a->name}, «{$a->quiz}» era difícil: {$a->grade}/{$a->maxgrade}. ¿Vemos juntos qué te ha costado?';
$string['notice_quizgraded_mid_1'] = 'Buen esfuerzo, {$a->name}: {$a->grade}/{$a->maxgrade} en «{$a->quiz}». Vas por buen camino.';
$string['notice_quizgraded_mid_2'] = 'Buen trabajo, {$a->name}, {$a->grade}/{$a->maxgrade} en «{$a->quiz}». Con un poco más de práctica lo tendrás.';
$string['notice_quizgraded_mid_3'] = '{$a->name}, has obtenido {$a->grade}/{$a->maxgrade} en «{$a->quiz}». ¡Una buena base! Repasa las preguntas en las que dudaste.';
$string['notice_returnvisit_1'] = '¡Hola, {$a->name}! Me alegra verte. ¿En qué puedo ayudarte?';
$string['notice_returnvisit_2'] = '¡Hola, {$a->name}! ¿Seguimos? Pregúntame lo que quieras sobre el curso.';
$string['notice_returnvisit_3'] = 'Hola, {$a->name}, soy Ed. ¿Necesitas una mano con algo de este curso?';
$string['notice_returnvisit_4'] = '¡Hola, {$a->name}! Si te surge una pregunta mientras trabajas, aquí estoy.';
$string['placeholder'] = 'Escribe tu mensaje...';
$string['pluginname'] = 'Tutor Estudiantil Dixeo';
$string['practice_quiz_label'] = 'Cuestionario de práctica';
$string['privacy:metadata'] = 'El bloque Dixeo Student Tutor almacena contexto proactivo en cola (id de usuario, id de curso, texto del mensaje) en la base de datos de Moodle hasta que se envía. Las conversaciones del tutor las procesa local_dixeo y se transfieren a la API de Dixeo. La retención, exportación y eliminación de conversaciones se rigen por local_dixeo y el acuerdo del sitio con el servicio Dixeo; las filas proactivas en cola se describen en privacy:metadata:pendingpurpose.';
$string['privacy:metadata:courseid'] = 'El ID del curso en el que está inscrito el usuario.';
$string['privacy:metadata:externalpurpose'] = 'Los mensajes del usuario, el contexto del curso y una ruta de página minimizada del sitio se envían a la API de Dixeo (a través de local_dixeo) para generar respuestas del tutor IA. Las conversaciones las conserva el servicio Dixeo; pueden exportarse y eliminarse bajo petición, y la eliminación retira el registro de Dixeo de inmediato y la copia en poder del proveedor de IA poco después.';
$string['privacy:metadata:lastmessage'] = 'La hora en la que escribiste al tutor por última vez en cada curso.';
$string['privacy:metadata:lastproactive'] = 'La hora en la que el tutor te saludó por última vez en cada curso.';
$string['privacy:metadata:lastread'] = 'La hora del último mensaje del tutor que has leído en cada curso (para los indicadores de no leído).';
$string['privacy:metadata:message'] = 'El contenido del mensaje enviado por el usuario.';
$string['privacy:metadata:pageurl'] = 'Una ruta URL del sitio Moodle como contexto de página al enviar el mensaje (restringida a este sitio; solo se conserva el parámetro numérico id).';
$string['privacy:metadata:pending_courseid'] = 'El curso al que pertenece el contexto proactivo en cola.';
$string['privacy:metadata:pending_message'] = 'Solicitudes «Guide me» aún no enviadas al tutor.';
$string['privacy:metadata:pending_userid'] = 'El usuario al que pertenece el contexto proactivo en cola.';
$string['privacy:metadata:pendingpurpose'] = 'Almacena las solicitudes «Guide me» hasta que se envían a la API de Dixeo.';
$string['privacy:metadata:tutormode'] = 'Tu modo de tutor seleccionado (estándar, guía, cuestionario o enseñanza) en cada curso.';
$string['privacy:metadata:tutormodeactivity'] = 'La hora de tu último mensaje al tutor o cambio de modo en guía, cuestionario o enséñame, usada para volver al modo estándar tras una hora de inactividad.';
$string['privacy:metadata:userid'] = 'El ID del usuario que envía el mensaje.';
$string['privacy:path:conversation'] = 'Conversación del tutor';
$string['privacy:path:pending'] = 'Contexto del tutor en cola';
$string['privacyfailure_body'] = 'La solicitud de privacidad del tutor Dixeo relativa a {$a} no pudo completarse: la API de Dixeo no estaba disponible. Vuelva a lanzar la solicitud cuando la API se haya restablecido.';
$string['privacyfailure_subject'] = 'Solicitud de privacidad del tutor Dixeo fallida';
$string['quiz_difficulty_easy'] = 'Fácil';
$string['quiz_difficulty_hard'] = 'Difícil';
$string['quiz_difficulty_medium'] = 'Medio';
$string['quiz_exit'] = 'Salir del cuestionario';
$string['quiz_generate_error'] = 'No se pudo generar el cuestionario de práctica. Por favor, inténtelo de nuevo.';
$string['quiz_generating'] = 'Generando tu cuestionario de práctica…';
$string['quiz_panel_exit_fullscreen'] = 'Salir de pantalla completa';
$string['quiz_panel_fullscreen'] = 'Pantalla completa';
$string['quiz_review_best_score'] = 'Mejor puntuación: {$a->score}/{$a->total} ({$a->percent} %)';
$string['quiz_review_correct'] = 'Correcta';
$string['quiz_review_correct_answer'] = 'Respuesta correcta';
$string['quiz_review_details'] = 'Ver resultados del cuestionario';
$string['quiz_review_exit_score'] = 'Este intento: {$a->score}/{$a->total} ({$a->percent} %)';
$string['quiz_review_feedback'] = 'Retroalimentación';
$string['quiz_review_incorrect'] = 'Incorrecta';
$string['quiz_review_retake'] = 'Repetir cuestionario';
$string['quiz_review_your_answer'] = 'Tu respuesta';
$string['quiz_setup_cancel'] = 'Cancelar';
$string['quiz_setup_count'] = 'Número de preguntas';
$string['quiz_setup_difficulty'] = 'Dificultad';
$string['quiz_setup_loading'] = 'Cargando temas…';
$string['quiz_setup_start'] = 'Empezar cuestionario';
$string['quiz_setup_title'] = 'Cuestionario de práctica';
$string['quiz_setup_topic'] = 'Tema';
$string['resize_panel'] = 'Cambiar tamaño del panel del tutor';
$string['send'] = 'Enviar';
$string['setting_displaymode'] = 'Modo de visualización';
$string['setting_displaymode_desc'] = 'Mostrar el tutor en el cajón de bloques (panel lateral) o en una ventana emergente flotante abierta con un botón.';
$string['setting_displaymode_drawer'] = 'En el cajón de bloques';
$string['setting_displaymode_popup'] = 'En una ventana emergente';
$string['setting_enabledmodes'] = 'Modos de tutor habilitados';
$string['setting_enabledmodes_desc'] = 'Seleccione qué modos de tutor opcionales están disponibles. El modo estándar siempre está habilitado y no se puede desactivar.';
$string['setting_excludedmodules'] = 'Tipos de módulos excluidos';
$string['setting_excludedmodules_desc'] = 'Lista de tipos de módulos de actividad separados por comas donde el tutor debe ocultarse (ej: quiz,simplequiz2). El tutor no aparecerá en las páginas de estos tipos de actividad.';
$string['setup_language'] = 'Idioma';
$string['sources_pages'] = 'p. {$a}';
$string['talktotutor'] = 'Hablar con el tutor';
$string['task_erase_conversations'] = 'Eliminar las conversaciones del tutor Dixeo';
$string['task_post_notice'] = 'Guardar un aviso del tutor en la conversación de Dixeo';
$string['teach_generate_error'] = 'No se pudo generar la lección. Inténtalo de nuevo.';
$string['teach_generating'] = 'Generando tu lección personalizada…';
$string['teach_lesson_close'] = 'Cerrar lección';
$string['teach_lesson_fullscreen'] = 'Pantalla completa';
$string['teach_lesson_tts_play'] = 'Leer lección en voz alta';
$string['teach_lesson_tts_stop'] = 'Detener lectura';
$string['teach_setup_cancel'] = 'Cancelar';
$string['teach_setup_loading'] = 'Cargando temas…';
$string['teach_setup_prompt'] = 'Describe qué quieres aprender';
$string['teach_setup_prompt_placeholder'] = 'Por ejemplo: explícame este tema en términos más sencillos, o profundiza en un aspecto…';
$string['teach_setup_start'] = 'Crear lección';
$string['teach_setup_title'] = 'Lección personalizada';
$string['teach_setup_topic'] = 'Tema';
$string['timeout_message'] = 'La respuesta está tardando más de lo esperado. El asistente puede estar trabajando aún en su solicitud.';
$string['tooltip_hide_tutor'] = 'Cerrar Ed';
$string['tooltip_open_tutor'] = 'Preguntar a Ed';
$string['tutor_enabled'] = 'Tutor';
$string['tutor_enabled_default'] = 'Tutor para cursos nuevos';
$string['tutor_enabled_default_help'] = 'Activa el tutor en los cursos creados después de este cambio. Los cursos existentes conservan la opción ya guardada.';
$string['tutor_enabled_help'] = 'Activa o desactiva el tutor en este curso. Al desactivarlo, esa opción se conserva en el curso.';
$string['tutorpresentation'] = '¡Hola! Soy Ed, tu tutor de IA. ¿Cómo puedo ayudarte con este curso?';
$string['unknownerror'] = 'Ocurrió un error desconocido.';
