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
$string['aria_sent_at'] = 'Enviado a las {$a}';
$string['aria_skip_to_input'] = 'Ir al campo de mensaje';
$string['aria_stop_reading'] = 'Detener lectura';
$string['aria_type_message'] = 'Escribe tu mensaje';
$string['aria_your_message'] = 'Tu mensaje';
$string['assistanttitle'] = 'Pregunta a Ed';
$string['check_for_updates'] = 'Buscar actualizaciones';
$string['connection_lost'] = 'Conexión perdida. Intentando reconectar...';
$string['custom_lesson_label'] = 'Lección personalizada';
$string['custom_lesson_view'] = 'Ver lección';
$string['dixeo_tutor:addinstance'] = 'Agregar un nuevo bloque del Tutor Estudiantil Dixeo';
$string['dixeo_tutor:talktotutor'] = 'Interactuar con el Tutor de IA';
$string['editingmode'] = 'Tutor Estudiantil Dixeo no está disponible en modo de edición.';
$string['error_apierror'] = 'Lo sentimos, hubo un problema de comunicación con el servicio de IA.';
$string['error_check_updates'] = 'No se pudieron verificar las actualizaciones. Por favor, actualice la página.';
$string['error_job_access'] = 'No se pudo obtener el estado del trabajo.';
$string['error_mode_not_available'] = 'El modo de tutor seleccionado no está disponible.';
$string['error_network'] = 'Ocurrió un error de red. Por favor, verifique su conexión e inténtelo de nuevo.';
$string['error_timeout'] = 'La solicitud ha expirado. Por favor, verifique su conexión e inténtelo de nuevo.';
$string['errorsendmessage'] = 'Lo sentimos, hubo un error al enviar su mensaje. Por favor, inténtelo de nuevo.';
$string['eventconversationviewed'] = 'Conversación del tutor Dixeo consultada';
$string['eventconversationvieweddesc'] = 'El usuario con id \'{$a->userid}\' consultó la conversación del tutor en el curso \'{$a->courseid}\' (messagecount={$a->messagecount}, sinceid=\'{$a->sinceid}\').';
$string['eventjobstatusviewed'] = 'Estado del trabajo del tutor Dixeo consultado';
$string['eventjobstatusvieweddesc'] = 'El usuario con id \'{$a->userid}\' consultó el estado del trabajo del tutor en el curso \'{$a->courseid}\' (jobid=\'{$a->jobid}\', status=\'{$a->status}\').';
$string['eventmessagesent'] = 'Mensaje del tutor Dixeo enviado';
$string['eventmessagesentdesc'] = 'El usuario con id \'{$a->userid}\' envió un mensaje al tutor en el curso \'{$a->courseid}\' (jobid=\'{$a->jobid}\').';
$string['filecountlimit'] = 'El tutor IA está limitado a 150 archivos por curso (actualmente {$a} archivos). Por favor, reduzca el número de archivos si es necesario.';
$string['guide_banner_exit'] = 'Salir del modo socrático';
$string['guide_banner_help'] = 'En el modo socrático, el tutor te hace preguntas para que razones el tema en lugar de darte la respuesta de inmediato. Responde con tus propias palabras. Si te quedas atascado, dilo y recibirás una pista. Cierra esta barra para volver al modo estándar.';
$string['guide_banner_help_label'] = 'Acerca del modo socrático';
$string['guide_banner_title'] = 'Modo socrático';
$string['load_older_messages'] = 'Cargar mensajes anteriores';
$string['message_too_long'] = 'El mensaje no puede exceder {$a} caracteres.';
$string['mode_selector_title'] = 'Modo del tutor';
$string['modeguide'] = 'Guíame';
$string['modeguide_desc'] = 'El tutor usa un enfoque socrático y te guía con preguntas.';
$string['modenormal'] = 'Estándar';
$string['modenormal_desc'] = 'Haz preguntas y obtén respuestas directas del tutor.';
$string['modequiz'] = 'Ponme a prueba';
$string['modequiz_desc'] = 'Practica con un cuestionario generado a partir del contenido del curso.';
$string['modeteach'] = 'Enséñame';
$string['modeteach_desc'] = 'Solicita una lección personalizada sobre el tema que elijas.';
$string['notenrolled'] = 'Debe estar inscrito en este curso para usar el tutor.';
$string['placeholder'] = 'Escribe tu mensaje...';
$string['pluginname'] = 'Tutor Estudiantil Dixeo';
$string['privacy:metadata'] = 'El bloque Dixeo Student Tutor almacena contexto proactivo en cola (id de usuario, id de curso, texto del mensaje) en la base de datos de Moodle hasta que se envía. Las conversaciones del tutor las procesa local_dixeo y se transfieren a la API de Dixeo. La retención, exportación y eliminación de conversaciones se rigen por local_dixeo y el acuerdo del sitio con el servicio Dixeo; las filas proactivas en cola se describen en privacy:metadata:pendingpurpose.';
$string['privacy:metadata:courseid'] = 'El ID del curso en el que está inscrito el usuario.';
$string['privacy:metadata:externalpurpose'] = 'Los mensajes del usuario, el contexto del curso y una ruta de página minimizada del sitio se envían a la API de Dixeo (a través de local_dixeo) para generar respuestas del tutor IA. Este bloque no almacena conversaciones localmente y por tanto no exporta ni elimina esos datos; esos controles deben proporcionarlos local_dixeo y el contrato de la API de Dixeo.';
$string['privacy:metadata:lastread'] = 'La hora del último mensaje del tutor que has leído en cada curso (para los indicadores de no leído).';
$string['privacy:metadata:message'] = 'El contenido del mensaje enviado por el usuario.';
$string['privacy:metadata:pageurl'] = 'Una ruta URL del sitio Moodle como contexto de página al enviar el mensaje (restringida a este sitio; se eliminan cadenas de consulta y fragmentos).';
$string['privacy:metadata:pending_courseid'] = 'El curso al que pertenece el contexto proactivo en cola.';
$string['privacy:metadata:pending_message'] = 'Líneas de contexto en primera persona aún no enviadas al tutor.';
$string['privacy:metadata:pending_userid'] = 'El usuario al que pertenece el contexto proactivo en cola.';
$string['privacy:metadata:pendingpurpose'] = 'Almacena las indicaciones proactivas del tutor en cola hasta que se envían a la API de Dixeo.';
$string['privacy:metadata:tutormode'] = 'Tu modo de tutor seleccionado (estándar, guía, cuestionario o enseñanza) en cada curso.';
$string['privacy:metadata:tutormodeactivity'] = 'La hora de tu último mensaje al tutor o cambio de modo en guía, cuestionario o enséñame, usada para volver al modo estándar tras una hora de inactividad.';
$string['privacy:metadata:userid'] = 'El ID del usuario que envía el mensaje.';
$string['proactive_course_completed'] = 'El alumno completó el curso. Felicítalo calurosamente.';
$string['proactive_default_name'] = 'ahí';
$string['proactive_first_visit'] = 'Da la bienvenida al alumno por su nombre ({$a->name}). Es la primera vez que abre este curso. Envía un saludo breve y amable y ofrece ayuda para empezar.';
$string['proactive_guide_started'] = 'El alumno acaba de entrar en el modo socrático (Guíame). Analiza la conversación hasta ahora y el contenido de este curso. Inicia un diálogo socrático sobre cualquier tema relevante del contexto del curso: formula una pregunta concreta que requiera respuesta. No des una lección ni la respuesta completa. Espera a que el alumno responda.';
$string['proactive_quiz_graded'] = 'El alumno completó el cuestionario «{$a->quizname}» con una calificación de {$a->grade}/{$a->maxgrade}. Reconoce su resultado y anímalo.';
$string['proactive_return_visit_delighted'] = 'El alumno continúa con este curso hoy. Envía un saludo especialmente cálido y entusiasta — alegre y motivador. No hagas referencia a su ausencia, al tiempo transcurrido ni a que está volviendo. Céntrate en saludarlo y animarlo a continuar el curso.';
$string['proactive_return_visit_enthusiastic'] = 'El alumno continúa con este curso hoy. Envía un saludo cálido y animado, con energía positiva. No menciones cuánto tiempo lleva ausente ni uses frases como «bienvenido de nuevo» — salúdalo y ayúdalo a retomar donde lo dejó.';
$string['proactive_return_visit_warm'] = 'El alumno continúa con este curso hoy. Envía un saludo breve y amable. No menciones cuánto tiempo lleva ausente ni uses frases como «bienvenido de nuevo» — salúdalo con naturalidad y ofrécele ayuda para continuar.';
$string['quiz_difficulty_easy'] = 'Fácil';
$string['quiz_difficulty_hard'] = 'Difícil';
$string['quiz_difficulty_medium'] = 'Medio';
$string['quiz_exit'] = 'Salir del cuestionario';
$string['quiz_generate_error'] = 'No se pudo generar el cuestionario de práctica. Por favor, inténtelo de nuevo.';
$string['quiz_generating'] = 'Generando tu cuestionario de práctica…';
$string['quiz_me'] = 'Ponme a prueba';
$string['quiz_panel_exit_fullscreen'] = 'Salir de pantalla completa';
$string['quiz_panel_fullscreen'] = 'Pantalla completa';
$string['quiz_review_ai_instructions'] = '[Revisión del cuestionario de práctica] Terminé el cuestionario de práctica «{$a->title}» con una mejor puntuación de {$a->score}/{$a->total}. Usa los resultados estructurados de las preguntas en este mensaje. Felicítame si lo hice bien. Si mi puntuación fue baja o fallé preguntas, sé comprensivo y animador — ayúdame a mantener la motivación. Sugiere temas o material del curso para repasar y cerrar lagunas de conocimiento, y recomienda próximos pasos concretos. Mantén tu respuesta enfocada y útil.';
$string['quiz_review_best_score'] = 'Mejor puntuación: {$a->score}/{$a->total} ({$a->percent} %)';
$string['quiz_review_correct'] = 'Correcta';
$string['quiz_review_correct_answer'] = 'Respuesta correcta';
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
$string['quizrestriction'] = 'Tutor Estudiantil Dixeo no está disponible en las páginas de cuestionarios.';
$string['resize_panel'] = 'Cambiar tamaño del panel del tutor';
$string['retry'] = 'Reintentar';
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
$string['talktotutor'] = 'Hablar con el tutor';
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
$string['teach_setup_prompt_required'] = 'Describe qué quieres aprender.';
$string['teach_setup_start'] = 'Crear lección';
$string['teach_setup_title'] = 'Lección personalizada';
$string['teach_setup_topic'] = 'Tema';
$string['timeout_message'] = 'La respuesta está tardando más de lo esperado. El asistente puede estar trabajando aún en su solicitud.';
$string['tooltip_hide_tutor'] = 'Cerrar Ed';
$string['tooltip_open_tutor'] = 'Preguntar a Ed';
$string['unknownerror'] = 'Ocurrió un error desconocido.';
$string['yesterday'] = 'ayer';
