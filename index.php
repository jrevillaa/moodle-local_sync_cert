<?php

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');

global $CFG,$PAGE,$DB,$OUTPUT;

require_once($CFG->libdir.'/adminlib.php');
include_once('locallib.php');

$url = new moodle_url('/local/sync_cert/index.php');

$export = optional_param('export', null, PARAM_TEXT);

$PAGE->set_url($url);
$strtitle = 'Reporte de envíos fallidos';
admin_externalpage_setup('local_sync_cert_report', '', null, '', array('pagelayout' => 'report'));
$PAGE->set_context(\context_system::instance());

$PAGE->navbar->add($strtitle);
$PAGE->set_title($strtitle);
$PAGE->set_heading($strtitle);
$PAGE->set_pagelayout('report');

$data = local_sync_cert_get_errors();

$table = new html_table();
$table->head  = array('N', 'Curso','Usuario Employee','Campo de Error','Mensaje de Error','Código de Error','Fecha de Error');

foreach ($data as $k => $row) {
    $table->data[] = array_merge([$k+1],$row);
}

$exporturl = new moodle_url('/local/sync_cert/index.php', [
    'export' => 'xls'
] );
$xlsstring = get_string('application/vnd.ms-excel', 'mimetypes');
$xlsicon = html_writer::img($OUTPUT->image_url('f/spreadsheet'), $xlsstring, array('title' => $xlsstring,'style' => 'height:2rem;'));

if($export == null){
    echo $OUTPUT->header();
    echo  '<br>Exportar: ' . html_writer::link($exporturl, $xlsicon);
    echo '<br><br><br>';
    echo html_writer::table($table);

    echo $OUTPUT->footer();
}else{
    require_once($CFG->libdir . '/excellib.class.php');

    $workbook = new MoodleExcelWorkbook("reporte_sync_cert_global");
    $worksheet = $workbook->add_worksheet("reporte_sync_cert_global");
    $boldformat = $workbook->add_format();
    $boldformat->set_bold(true);
    $row = $col = 0;

    foreach ($table->head as $colname) {
        $worksheet->write_string($row, $col++, $colname, $boldformat);
    }
    $row++; $col = 0;

    foreach ($table->data as $entry) {
        foreach ($entry as $value) {
            $worksheet->write_string($row, $col++, $value);
        }
        $row++; $col = 0;
    }

    $workbook->close();
    exit();
}

