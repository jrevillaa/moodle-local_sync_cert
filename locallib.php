<?php

defined('MOODLE_INTERNAL') || die();


function local_sync_cert_get_errors(){
    global $DB;
    $output = [];
    $data_fail = $DB->get_records_sql('SELECT * FROM {local_sync_cert_send} WHERE status = 0');
    foreach ($data_fail as $data) {
        $issues = $DB->get_record_sql('SELECT 
                                            * 
                                            FROM {local_sync_cert_send_log} 
                                            WHERE log_id = ' . $data->id . ' 
                                            AND status = 0 
                                            ORDER BY timecreated 
                                            DESC LIMIT 1');
	//var_dump(json_decode($issues->response)->errors);
        $msg = json_decode($issues->response)->errors;
//echo "<pre>";
//var_dump($msg);
//echo "</pre>";        
$course = get_course($data->course_id);
        $user = $DB->get_record('user', array('id' => $data->user_id));
        foreach ($msg as $item) {
	if(!isset($item->field)){
		foreach($item->errors as $tmp){
			$output[] = [
                'send_id' => $data->id,
                'course' => $course->fullname,
                'user' => $user->username,
                'field' => $tmp->field,
                'message' => $tmp->message,
                'error_code' => $tmp->error_code,
                'time' => date('d/m/Y h:i',$data->timecreated)
            	];
		}
	}else{
            $output[] = [
                'send_id' => $data->id,
                'course' => $course->fullname,
                'user' => $user->username,
                'field' => $item->field,
                'message' => $item->message,
                'error_code' => $item->error_code,
                'time' => date('d/m/Y h:i',$data->timecreated)
            ];}
        }
    }
    return $output;
}
function local_sync_cert_cron(){
    global $CFG,$DB;

    $fails = $DB->get_records_sql('SELECT * FROM {local_sync_cert_send} WHERE status = 0');
    mtrace(count($fails));
    $data = [];
    foreach($fails as $fail){
        $course = get_course($fail->course_id);
        $user = $DB->get_record('user', ['id' => $fail->user_id]);
        $code_course = local_sync_cert_get_customfield('doc_code',$course->id);
        $date_to = local_sync_cert_get_customfield('cert_duration', $course->id);
        $obs = local_sync_cert_get_obs($course,$user);
        if($date_to){
            $date_to = $date_to->value;
        }else{
            $date_to = 0;
        }
        $data[] = local_sync_cert_build_post_body(
            local_sync_cert_get_contractor($course->id),
            $user->username,
            ($code_course) ? $code_course->value : '' ,
            date('Y-m-d',$fail->timecreated),
            date('Y-m-d',strtotime('+' . $date_to . ' days',$fail->timecreated)),
            $obs);
        //mtrace(json_encode($data));
    }
	mtrace(json_encode($data));
    $response = local_sync_cert_send_data($data);
//var_dump($response);die();
    foreach ($response->response->errors as $item) {
        $status = 0;
        local_sync_response_update($item->employee_identifier,$item->document_cod,$status,$item);
        mtrace(json_encode($item));
        mtrace('---ERROR---');
    }

    foreach ($response->response->success as $item) {
        $status = 1;
        local_sync_response_update($item->employee_identifier,$item->document_cod,$status,$item);
        mtrace(json_encode($item));
        mtrace('---SUCCESS---');
    }

}

function date_to_unix($date) {
    // Validar que la fecha coincida con el formato Y-m-d
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        //return false;
    }
    
    // Convertir a timestamp usando strtotime
    $timestamp = strtotime($date);
    
    // Verificar que la conversión fue exitosa
    if ($timestamp === false) {
        return false;
    }
    
    return $timestamp;
}

function local_sync_response_update($employee_identifier,$document_doc,$status,$response){
    global $DB;
    $user = $DB->get_record('user', ['username' => $employee_identifier]);
    $course = $DB->get_records_sql("
        SELECT cd.id,cf.shortname,cd.value,cd.instanceid FROM {customfield_field} cf
        INNER JOIN {customfield_data} cd ON cd.fieldid = cf.id
        WHERE cf.shortname = 'doc_code' AND cd.value = '" . $document_doc . "' ");
   $tmp_courses = 0;
//var_dump(json_decode($response));die();   
foreach($course as $k => $c){
	$tmp = $DB->get_record('local_sync_cert_send',['course_id' => $c->instanceid,'user_id' => $user->id, 'status' => 0]);
	if($tmp && date('Y-m-d',$tmp->timecreated) == $response->date_from){
		var_dump(date_to_unix($response->date_from),$response);
		$tmp_courses = $c->instanceid;
	}
    }
    //var_dump($tmp_courses);die();
    $fail = $DB->get_record('local_sync_cert_send', ['course_id' => $tmp_courses,'user_id' => $user->id]);

    local_sync_cert_update($fail->id,1,$fail->course_id,$user->id,$status);
    local_sync_cert_insert_log($fail->id,$status,json_encode($response));
}

function local_sync_cert_init_procedure($issueid,$courseid,$userid){
    global $DB;
    $course = get_course($courseid);
    $user = $DB->get_record('user', ['id' => $userid]);

    $code_course = local_sync_cert_get_customfield('doc_code',$course->id);
    $date_to = local_sync_cert_get_customfield('cert_duration', $course->id);
    $obs = local_sync_cert_get_obs($course,$user);
    if($date_to){
        $date_to = $date_to->value;
    }else{
        $date_to = 0;
    }
    $data = local_sync_cert_build_post_body(
        local_sync_cert_get_contractor($course->id),
        $user->username,
        ($code_course) ? $code_course->value : '' ,
        date('Y-m-d',time()),
        date('Y-m-d',strtotime('+' . $date_to . ' days',time())),
        $obs);

    //$response = local_sync_cert_send_data($data);
    //$status = 1;
    //if($response->response->errors != []){
        $status = 0;
    //}
    $tmp_id = local_sync_cert_insert($issueid,$courseid,$userid,$status);
    //local_sync_cert_insert_log($tmp_id,$status,json_encode($response->response));
}
function local_sync_cert_get_contractor($courseid){
    global $DB;
    $sql_users = 	"SELECT {user}.id, {user}.email,{user}.username FROM {course}
						INNER JOIN {context} ON {context}.instanceid = {course}.id
						INNER JOIN {role_assignments} ON {context}.id = {role_assignments}.contextid
						INNER JOIN {role} ON {role}.id = {role_assignments}.roleid
						INNER JOIN {user} ON {user}.id = {role_assignments}.userid
						WHERE {role}.id = 3 AND 
						{course}.id = " . $courseid;
    $teachers = array_values($DB->get_records_sql($sql_users));
    if($teachers == []){
        return '';
    }
    return $teachers[0]->username;
}

function local_sync_cert_send_data($data){
    /*$curl = curl_init();

    curl_setopt_array($curl, array(
        CURLOPT_URL => get_config('local_sync_cert', 'url_endpiont'),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERPWD => get_config('local_sync_cert', 'username') . ':' . get_config('local_sync_cert', 'password'),
        CURLOPT_POSTFIELDS => json_encode([$data]),
        CURLOPT_CUSTOMREQUEST => 'POST',
    ));

    $response = curl_exec($curl);
    curl_close($curl);
	mtrace(json_decode($response));

    return json_decode($response);*/
$curl = curl_init();

// Configurar archivo temporal para capturar salida verbose
$verbose = fopen('php://temp', 'w+');

curl_setopt_array($curl, array(
    CURLOPT_URL => get_config('local_sync_cert', 'url_endpiont'),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 0,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_USERPWD => get_config('local_sync_cert', 'username') . ':' . get_config('local_sync_cert', 'password'),
    CURLOPT_POSTFIELDS => json_encode($data),
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_HTTPHEADER => array('Content-Type: application/json'),
    // Opciones de debug
    CURLOPT_VERBOSE => true,
    CURLOPT_STDERR => $verbose,
));

$response = curl_exec($curl);
//var_dump($response);die();
// Capturar información de debug
if ($response === false) {
    mtrace('cURL Error: ' . curl_error($curl));
    mtrace('cURL Error Number: ' . curl_errno($curl));
}

// Obtener información detallada de la petición
$info = curl_getinfo($curl);
mtrace('HTTP Code: ' . $info['http_code']);
mtrace('Total Time: ' . $info['total_time'] . ' seconds');
mtrace('Content Type: ' . $info['content_type']);

// Capturar el log verbose
rewind($verbose);
$verboseLog = stream_get_contents($verbose);
mtrace('Verbose Debug Log:');
mtrace($verboseLog);

curl_close($curl);
fclose($verbose);

mtrace('Response: ' . $response);
//mtrace($response);

return json_decode($response);
}

function local_sync_cert_insert_log($id,$status,$response){
    global $DB;
    $data = [
        'log_id' => $id,
        'status' => $status,
        'response' => $response,
        'timecreated' => time(),
        'timemodified' => time()
    ];
    $DB->insert_record('local_sync_cert_send_log',$data);
}
function local_sync_cert_insert($issueid,$courseid,$userid,$status){
    global $DB;
    $data = [
        'cert_id' => $issueid,
        'course_id' => $courseid,
        'user_id' => $userid,
        'status' => $status,
        'timecreated' => time(),
        'timemodified' => time()
    ];
    return $DB->insert_record('local_sync_cert_send',$data);
}

function local_sync_cert_update($id,$issueid,$courseid,$userid,$status){
    global $DB;
    $data = [
        'id' => $id,
        'cert_id' => $issueid,
        'course_id' => $courseid,
        'user_id' => $userid,
        'status' => $status,
        'timemodified' => time()
    ];
    $DB->update_record('local_sync_cert_send',$data);
}


function local_sync_cert_build_post_body($contractor,$employee,$document_code,$date_from,$date_to,$observations){
    $data = [
        'contractor_identifier' => $contractor,
        'employee_identifier' => $employee,
        'document_cod' => $document_code,
        'date_from' => $date_from,
        'date_to' => $date_to,
        'observations' => $observations
    ];
    return $data;
}


function local_sync_cert_get_obs($course,$user){
    global $DB;
    $grade = $DB->get_record_sql("
    SELECT ROUND(gg.finalgrade) as 'grade' FROM {grade_grades} gg
    INNER JOIN {grade_items} gi ON gg.itemid = gi.id
    WHERE gi.itemtype = 'course' AND gg.userid = " . $user->id . ' AND gi.courseid = ' . $course->id);

    $nota = ($grade && isset($grade->grade)) ? $grade->grade . '/20' : 'Sin nota';
    return $course->fullname . ' ' . $nota;
}



function local_sync_cert_get_customfield($name,$courseid){
    global $DB;
    return $DB->get_record_sql("
        SELECT cf.shortname,cd.value FROM {customfield_field} cf
        INNER JOIN {customfield_data} cd ON cd.fieldid = cf.id
        WHERE cf.shortname = '" . $name . "' AND cd.instanceid = " . $courseid);
}

