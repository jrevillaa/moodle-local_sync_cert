<?php

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');

global $CFG,$PAGE,$DB,$OUTPUT;

require_once($CFG->libdir.'/adminlib.php');
include_once('locallib.php');

$url = new moodle_url('/local/sync_cert/index.php');

$export = optional_param('export', null, PARAM_TEXT);
$delete = optional_param('delete', 0, PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_INT);

$PAGE->set_url($url);
$strtitle = 'Reporte de envíos fallidos';
admin_externalpage_setup('local_sync_cert_report', '', null, '', array('pagelayout' => 'report'));
$PAGE->set_context(\context_system::instance());

$PAGE->navbar->add($strtitle);
$PAGE->set_title($strtitle);
$PAGE->set_heading($strtitle);
$PAGE->set_pagelayout('report');

// ========================================
// MANEJAR ELIMINACIÓN DE REGISTRO
// ========================================
if ($delete && $confirm && confirm_sesskey()) {
    $transaction = $DB->start_delegated_transaction();

    try {
        // Eliminar logs primero
        $DB->delete_records('local_sync_cert_send_log', ['log_id' => $delete]);

        // Eliminar registro de send
        $DB->delete_records('local_sync_cert_send', ['id' => $delete]);

        $transaction->allow_commit();

        redirect(
            $url,
            'Registro eliminado correctamente',
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    } catch (Exception $e) {
        $transaction->rollback($e);
        redirect(
            $url,
            'Error al eliminar el registro',
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }
}

// ========================================
// MOSTRAR CONFIRMACIÓN DE ELIMINACIÓN
// ========================================
if ($delete && !$confirm) {
    $send = $DB->get_record('local_sync_cert_send', ['id' => $delete]);

    if ($send) {
        $user = $DB->get_record('user', ['id' => $send->user_id]);
        $course = get_course($send->course_id);

        echo $OUTPUT->header();

        echo $OUTPUT->confirm(
            '¿Está seguro de que desea eliminar el registro de sincronización para el usuario <strong>' .
            fullname($user) . ' (' . $user->username . ')</strong> en el curso <strong>' .
            $course->fullname . '</strong>?<br><br>Esto también eliminará todos los logs asociados. Esta acción no se puede deshacer.',
            new moodle_url('/local/sync_cert/index.php', [
                'delete' => $delete,
                'confirm' => 1,
                'sesskey' => sesskey()
            ]),
            new moodle_url('/local/sync_cert/index.php')
        );

        echo $OUTPUT->footer();
        exit;
    }
}

$data = local_sync_cert_get_errors();

// ========================================
// CONSTRUIR TABLA CON BOTONES DE ACCIÓN
// ========================================
$table = new html_table();
$table->head  = array('N', 'Curso','Usuario Employee','Campo de Error','Mensaje de Error','Código de Error','Fecha de Error','Acciones');
$table->attributes['class'] = 'generaltable table-striped';

foreach ($data as $k => $row) {
    // Botones de acción
    $actions = '';

    // Botón Ver logs
    $viewurl = new moodle_url('/local/sync_cert/view_logs.php', ['id' => $row['send_id']]);
    $actions .= html_writer::link(
        $viewurl,
        'Ver logs',
        ['class' => 'btn btn-sm btn-info', 'style' => 'margin-right: 5px;']
    );

    // Botón Eliminar
    $deleteurl = new moodle_url('/local/sync_cert/index.php', [
        'delete' => $row['send_id'],
        'sesskey' => sesskey()
    ]);
    $actions .= html_writer::link(
        $deleteurl,
        'Eliminar',
        ['class' => 'btn btn-sm btn-danger']
    );

    // Agregar fila con acciones
    $table->data[] = array(
        $k+1,
        $row['course'],
        $row['user'],
        $row['field'],
        $row['message'],
        $row['error_code'],
        $row['time'],
        $actions
    );
}

$exporturl = new moodle_url('/local/sync_cert/index.php', [
    'export' => 'xls'
] );
$xlsstring = get_string('application/vnd.ms-excel', 'mimetypes');
$xlsicon = html_writer::img($OUTPUT->image_url('f/spreadsheet'), $xlsstring, array('title' => $xlsstring,'style' => 'height:2rem;'));

if($export == null){
    echo $OUTPUT->header();

    if (empty($data)) {
        echo $OUTPUT->notification('No hay envíos fallidos en este momento', 'success');
    } else {
        echo  '<br>Exportar: ' . html_writer::link($exporturl, $xlsicon);
        echo '<br><br><br>';
        echo html_writer::table($table);
    }

    echo $OUTPUT->footer();
}else{
    require_once($CFG->libdir . '/excellib.class.php');

    $workbook = new MoodleExcelWorkbook("reporte_sync_cert_global");
    $worksheet = $workbook->add_worksheet("reporte_sync_cert_global");
    $boldformat = $workbook->add_format();
    $boldformat->set_bold(true);
    $row = $col = 0;

    // Excluir columna "Acciones" de la exportación
    $headers = array_slice($table->head, 0, -1);

    foreach ($headers as $colname) {
        $worksheet->write_string($row, $col++, $colname, $boldformat);
    }
    $row++; $col = 0;

    foreach ($table->data as $entry) {
        // Excluir última columna (Acciones) de la exportación
        $exportdata = array_slice($entry, 0, -1);
        foreach ($exportdata as $value) {
            $worksheet->write_string($row, $col++, $value);
        }
        $row++; $col = 0;
    }

    $workbook->close();
    exit();
}
