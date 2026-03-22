<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once(__DIR__ . '/locallib.php');

admin_externalpage_setup('local_sync_cert_logs');

$id = required_param('id', PARAM_INT);
$page = optional_param('page', 0, PARAM_INT);
$perpage = optional_param('perpage', 20, PARAM_INT);
$filter_status = optional_param('status', '', PARAM_INT);

$context = context_system::instance();
require_capability('moodle/site:config', $context);

$PAGE->set_url(new moodle_url('/local/sync_cert/api_log_detail.php', array('id' => $id)));
$PAGE->set_title(get_string('api_log_detail', 'local_sync_cert'));
$PAGE->set_heading(get_string('api_log_detail', 'local_sync_cert'));

$log = $DB->get_record('local_sync_cert_api_calls', array('id' => $id), '*', MUST_EXIST);

echo $OUTPUT->header();

// Resumen de la llamada
echo $OUTPUT->heading(get_string('call_summary', 'local_sync_cert'), 3);
echo html_writer::start_tag('table', array('class' => 'table table-bordered mb-4'));
echo html_writer::tag('tr',
    html_writer::tag('th', get_string('date'), array('width' => '30%')) .
    html_writer::tag('td', userdate($log->timecreated, get_string('strftimedatetime', 'langconfig')))
);
echo html_writer::tag('tr',
    html_writer::tag('th', get_string('total', 'local_sync_cert')) .
    html_writer::tag('td', $log->total_processed)
);
echo html_writer::tag('tr',
    html_writer::tag('th', get_string('success', 'local_sync_cert')) .
    html_writer::tag('td', html_writer::tag('span', $log->success_count, array('class' => 'badge bg-success')))
);
echo html_writer::tag('tr',
    html_writer::tag('th', get_string('errors', 'local_sync_cert')) .
    html_writer::tag('td', html_writer::tag('span', $log->error_count, array('class' => 'badge bg-danger')))
);
echo html_writer::end_tag('table');

// Filtros
echo html_writer::start_tag('form', array('method' => 'get', 'class' => 'mb-3'));
echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'id', 'value' => $id));
echo html_writer::start_div('row g-3');
echo html_writer::start_div('col-md-3');
echo html_writer::label(get_string('filter_by_status', 'local_sync_cert'), 'status', true, array('class' => 'form-label'));
echo html_writer::select(
    array('' => get_string('all'), '1' => get_string('success', 'local_sync_cert'), '0' => get_string('errors', 'local_sync_cert')),
    'status',
    $filter_status,
    false,
    array('class' => 'form-select', 'id' => 'status')
);
echo html_writer::end_div();
echo html_writer::start_div('col-md-2 align-self-end');
echo html_writer::tag('button', get_string('filter'), array('type' => 'submit', 'class' => 'btn btn-primary'));
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_tag('form');

// Obtener registros detallados de local_sync_cert_send_log
// Necesitamos encontrar los logs que corresponden a esta ejecución del cron
// Los identificamos por timestamp cercano (dentro de 1 minuto de diferencia)
$time_start = $log->timecreated - 60;
$time_end = $log->timecreated + 300; // 5 minutos después

$where = 'timecreated BETWEEN :time_start AND :time_end';
$params = array('time_start' => $time_start, 'time_end' => $time_end);

if ($filter_status !== '') {
    $where .= ' AND status = :status';
    $params['status'] = $filter_status;
}

$total_detail_records = $DB->count_records_select('local_sync_cert_send_log', $where, $params);
$detail_logs = $DB->get_records_select('local_sync_cert_send_log', $where, $params, 'timecreated DESC', '*', $page * $perpage, $perpage);

// Mostrar tabla detallada
echo $OUTPUT->heading(get_string('detailed_records', 'local_sync_cert'), 3);

if ($detail_logs) {
    echo html_writer::start_div('table-responsive');
    echo html_writer::start_tag('table', array('class' => 'table table-sm table-striped'));
    echo html_writer::start_tag('thead');
    echo html_writer::start_tag('tr');
    echo html_writer::tag('th', 'Log ID');
    echo html_writer::tag('th', get_string('status'));
    echo html_writer::tag('th', get_string('date'));
    echo html_writer::tag('th', get_string('response'));
    echo html_writer::end_tag('tr');
    echo html_writer::end_tag('thead');
    echo html_writer::start_tag('tbody');

    foreach ($detail_logs as $detail) {
        $row_class = $detail->status == 1 ? 'table-success' : 'table-danger';
        echo html_writer::start_tag('tr', array('class' => $row_class));
        echo html_writer::tag('td', $detail->log_id);

        $status_badge = $detail->status == 1 ?
            html_writer::tag('span', get_string('success', 'local_sync_cert'), array('class' => 'badge bg-success')) :
            html_writer::tag('span', get_string('error', 'local_sync_cert'), array('class' => 'badge bg-danger'));
        echo html_writer::tag('td', $status_badge);

        echo html_writer::tag('td', userdate($detail->timecreated, get_string('strftimedate', 'langconfig')));

        // Formatear response JSON
        $response_obj = json_decode($detail->response);
        $response_html = '<pre style="margin:0; font-size:0.85em; max-width:400px; overflow-x:auto;">' .
            json_encode($response_obj, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) .
            '</pre>';
        echo html_writer::tag('td', $response_html);

        echo html_writer::end_tag('tr');
    }

    echo html_writer::end_tag('tbody');
    echo html_writer::end_tag('table');
    echo html_writer::end_div();

    // Paginación
    if ($total_detail_records > $perpage) {
        $baseurl = new moodle_url('/local/sync_cert/api_log_detail.php', array(
            'id' => $id,
            'status' => $filter_status,
            'perpage' => $perpage
        ));
        echo $OUTPUT->paging_bar($total_detail_records, $page, $perpage, $baseurl);
    }
} else {
    echo $OUTPUT->notification(get_string('no_records', 'local_sync_cert'), 'info');
}

// Botón volver
echo html_writer::link(
    new moodle_url('/local/sync_cert/api_logs.php'),
    get_string('back'),
    array('class' => 'btn btn-secondary mt-3')
);

echo $OUTPUT->footer();
