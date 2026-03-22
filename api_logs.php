<?php

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/tablelib.php');
require_once(__DIR__ . '/locallib.php');

admin_externalpage_setup('local_sync_cert_logs');

$page = optional_param('page', 0, PARAM_INT);
$perpage = optional_param('perpage', 20, PARAM_INT);
$datefrom = optional_param('datefrom', '', PARAM_TEXT);
$dateto = optional_param('dateto', '', PARAM_TEXT);

$context = context_system::instance();
require_capability('moodle/site:config', $context);

$PAGE->set_url(new moodle_url('/local/sync_cert/api_logs.php'));
$PAGE->set_title(get_string('api_logs', 'local_sync_cert'));
$PAGE->set_heading(get_string('api_logs', 'local_sync_cert'));

echo $OUTPUT->header();

// Formulario de filtros
echo html_writer::start_tag('form', array('method' => 'get', 'class' => 'mb-3'));
echo html_writer::start_div('row g-3');

// Filtro fecha desde
echo html_writer::start_div('col-md-3');
echo html_writer::label(get_string('from','local_sync_cert'), 'datefrom', true, array('class' => 'form-label'));
echo html_writer::empty_tag('input', array(
    'type' => 'date',
    'name' => 'datefrom',
    'id' => 'datefrom',
    'value' => $datefrom,
    'class' => 'form-control'
));
echo html_writer::end_div();

// Filtro fecha hasta
echo html_writer::start_div('col-md-3');
echo html_writer::label(get_string('to','local_sync_cert'), 'dateto', true, array('class' => 'form-label'));
echo html_writer::empty_tag('input', array(
    'type' => 'date',
    'name' => 'dateto',
    'id' => 'dateto',
    'value' => $dateto,
    'class' => 'form-control'
));
echo html_writer::end_div();

// Botón filtrar
echo html_writer::start_div('col-md-2 align-self-end');
echo html_writer::tag('button', get_string('filter'), array('type' => 'submit', 'class' => 'btn btn-primary'));
if ($datefrom || $dateto) {
    echo ' ';
    echo html_writer::link(
        new moodle_url('/local/sync_cert/api_logs.php'),
        get_string('clear'),
        array('class' => 'btn btn-secondary')
    );
}
echo html_writer::end_div();

echo html_writer::end_div();
echo html_writer::end_tag('form');

// Construir query con filtros
$where = '1=1';
$params = array();

if (!empty($datefrom)) {
    $timestamp_from = strtotime($datefrom);
    if ($timestamp_from) {
        $where .= ' AND timecreated >= :datefrom';
        $params['datefrom'] = $timestamp_from;
    }
}

if (!empty($dateto)) {
    $timestamp_to = strtotime($dateto . ' 23:59:59');
    if ($timestamp_to) {
        $where .= ' AND timecreated <= :dateto';
        $params['dateto'] = $timestamp_to;
    }
}

// Obtener registros con paginación
$total_records = $DB->count_records_select('local_sync_cert_api_calls', $where, $params);
$logs = $DB->get_records_select('local_sync_cert_api_calls', $where, $params, 'timecreated DESC', '*', $page * $perpage, $perpage);

// Mostrar tabla
if ($logs) {
    echo html_writer::start_div('table-responsive');
    echo html_writer::start_tag('table', array('class' => 'table table-striped table-bordered'));
    echo html_writer::start_tag('thead', array('class' => 'table-light'));
    echo html_writer::start_tag('tr');
    echo html_writer::tag('th', 'ID');
    echo html_writer::tag('th', get_string('date'));
    echo html_writer::tag('th', get_string('total', 'local_sync_cert'));
    echo html_writer::tag('th', get_string('success', 'local_sync_cert'));
    echo html_writer::tag('th', get_string('errors', 'local_sync_cert'));
    echo html_writer::tag('th', get_string('actions'));
    echo html_writer::end_tag('tr');
    echo html_writer::end_tag('thead');
    echo html_writer::start_tag('tbody');

    foreach ($logs as $log) {
        echo html_writer::start_tag('tr');
        echo html_writer::tag('td', $log->id);
        echo html_writer::tag('td', userdate($log->timecreated, get_string('strftimedatetime', 'langconfig')));
        echo html_writer::tag('td', $log->total_processed);
        echo html_writer::tag('td',
            html_writer::tag('span', $log->success_count, array('class' => 'badge bg-success'))
        );
        $error_class = $log->error_count > 0 ? 'badge bg-danger' : 'badge bg-secondary';
        echo html_writer::tag('td',
            html_writer::tag('span', $log->error_count, array('class' => $error_class))
        );

        $url = new moodle_url('/local/sync_cert/api_log_detail.php', array('id' => $log->id));
        echo html_writer::tag('td',
            html_writer::link($url, get_string('view'), array('class' => 'btn btn-sm btn-primary'))
        );
        echo html_writer::end_tag('tr');
    }

    echo html_writer::end_tag('tbody');
    echo html_writer::end_tag('table');
    echo html_writer::end_div();

    // Paginación
    if ($total_records > $perpage) {
        $baseurl = new moodle_url('/local/sync_cert/api_logs.php', array(
            'datefrom' => $datefrom,
            'dateto' => $dateto,
            'perpage' => $perpage
        ));
        echo $OUTPUT->paging_bar($total_records, $page, $perpage, $baseurl);
    }
} else {
    echo $OUTPUT->notification(get_string('no_records', 'local_sync_cert'), 'info');
}

echo $OUTPUT->footer();
