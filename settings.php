<?php

defined('MOODLE_INTERNAL') || die();
/** @var CFG $CFG */

    $ADMIN->add('localplugins', new admin_category('local_sync_cert_api', 'Sync Certificate'));


    $settings = new admin_settingpage('local_sync_cert_api_page', 'Endpoint configuration');




    $settings->add(new admin_setting_heading('local_sync_cert_api_head', 'Endpoint', ''));

    $name = 'local_sync_cert/url_endpiont';
    $visiblename = 'Endpoint';
    $description = 'Se agrega el Endpoint donde se va a comunicar';
    $paramtype = PARAM_RAW;
    $settings->add(new admin_setting_configtext($name, $visiblename, $description, null, $paramtype));

    $settings->add(new admin_setting_heading('local_sync_cert_credentials_head', 'Credenciales', ''));

    $clientid = new admin_setting_configtext(
        'local_sync_cert/username',
        'Username',
        'Username del Basic Auth para llamar al endpoint',
        '',
        PARAM_ALPHANUMEXT
    );
    $settings->add($clientid);

    $clientsecret = new admin_setting_configpasswordunmask(
        'local_sync_cert/password',
        'Password',
        'Password del Basic Auth para llamar al endpoint',
        ''
    );
    $settings->add($clientsecret);

    $ADMIN->add('local_sync_cert_api', $settings);

    $ADMIN->add('local_sync_cert_api',new admin_externalpage('local_sync_cert_report', 'Reporte de envíos fallidos',
        "$CFG->wwwroot/local/sync_cert/index.php"));


