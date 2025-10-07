<?php
/**
 * View logs for a send record
 *
 * @package    local_sync_cert
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');

global $CFG, $PAGE, $DB, $OUTPUT;

require_once($CFG->libdir.'/adminlib.php');

$sendid = required_param('id', PARAM_INT);

$url = new moodle_url('/local/sync_cert/view_logs.php', ['id' => $sendid]);

$PAGE->set_url($url);
$strtitle = 'Logs de sincronización';
admin_externalpage_setup('local_sync_cert_report', '', null, '', array('pagelayout' => 'report'));
$PAGE->set_context(\context_system::instance());

$PAGE->navbar->add('Reporte de envíos fallidos', new moodle_url('/local/sync_cert/index.php'));
$PAGE->navbar->add($strtitle);
$PAGE->set_title($strtitle);
$PAGE->set_heading($strtitle);
$PAGE->set_pagelayout('report');

// Obtener registro de send
$send = $DB->get_record('local_sync_cert_send', ['id' => $sendid], '*', MUST_EXIST);
$user = $DB->get_record('user', ['id' => $send->user_id]);
$course = get_course($send->course_id);

// Obtener todos los logs
$logs = $DB->get_records('local_sync_cert_send_log',
    ['log_id' => $sendid],
    'timecreated DESC'
);

echo $OUTPUT->header();

// Botón volver
echo html_writer::link(
    new moodle_url('/local/sync_cert/index.php'),
    '← Volver al reporte',
    ['class' => 'btn btn-secondary mb-3']
);

echo $OUTPUT->heading('Historial de intentos de sincronización');

// Información del envío
echo html_writer::start_div('alert alert-info');
echo html_writer::tag('p', html_writer::tag('strong', 'Usuario: ') . fullname($user) . ' (' . $user->username . ')');
echo html_writer::tag('p', html_writer::tag('strong', 'Curso: ') . $course->fullname . ' (' . $course->shortname . ')');
echo html_writer::tag('p', html_writer::tag('strong', 'ID Certificado: ') . $send->cert_id);
echo html_writer::tag('p', html_writer::tag('strong', 'Total de intentos: ') . count($logs));
echo html_writer::end_div();

if (empty($logs)) {
    echo $OUTPUT->notification('No hay logs registrados para este envío', 'info');
} else {
    // Tabla de logs
    $table = new html_table();
    $table->head = [
        'ID',
        'Estado',
        'Respuesta',
        'Fecha de creación',
        'Fecha de modificación'
    ];

    $table->attributes['class'] = 'generaltable table-striped';

    foreach ($logs as $log) {
        // Estado
        if ($log->status == 1) {
            $status = html_writer::tag('span', 'Éxito', ['class' => 'badge badge-success']);
        } else {
            $status = html_writer::tag('span', 'Fallido', ['class' => 'badge badge-danger']);
        }

        // Formatear respuesta JSON
        $response = $log->response;
        $decoded = json_decode($response);

        if ($decoded) {
            $response = html_writer::tag('pre',
                json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
                ['style' => 'max-height: 200px; overflow: auto; font-size: 0.85em; background: #f5f5f5; padding: 10px;']
            );
        } else {
            $response = html_writer::tag('pre',
                $response,
                ['style' => 'max-height: 200px; overflow: auto; font-size: 0.85em;']
            );
        }

        $table->data[] = [
            $log->id,
            $status,
            $response,
            date('d/m/Y H:i:s', $log->timecreated),
            date('d/m/Y H:i:s', $log->timemodified)
        ];
    }

    echo html_writer::table($table);
}

// Botón volver al final
echo html_writer::link(
    new moodle_url('/local/sync_cert/index.php'),
    '← Volver al reporte',
    ['class' => 'btn btn-secondary mt-3']
);

echo $OUTPUT->footer();
