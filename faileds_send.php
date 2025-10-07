<?php
/**
 * Failed sends management page
 *
 * @package    local_sync_cert
 * @copyright  2025 Jair Revilla
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

admin_externalpage_setup('local_sync_cert_failed');

$PAGE->set_url(new moodle_url('/local/sync_cert/failed_sends.php'));
$PAGE->set_title(get_string('failed_sends', 'local_sync_cert'));
$PAGE->set_heading(get_string('failed_sends', 'local_sync_cert'));

require_capability('local/sync_cert:manage', context_system::instance());

// Handle delete action
$delete = optional_param('delete', 0, PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_INT);

if ($delete && $confirm && confirm_sesskey()) {
    $transaction = $DB->start_delegated_transaction();

    try {
        // Delete logs first
        $DB->delete_records('local_sync_cert_send_log', ['log_id' => $delete]);

        // Delete send record
        $DB->delete_records('local_sync_cert_send', ['id' => $delete]);

        $transaction->allow_commit();

        redirect(
            new moodle_url('/local/sync_cert/failed_sends.php'),
            get_string('delete_success', 'local_sync_cert'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    } catch (Exception $e) {
        $transaction->rollback($e);
        redirect(
            new moodle_url('/local/sync_cert/failed_sends.php'),
            get_string('delete_error', 'local_sync_cert'),
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('failed_sends_title', 'local_sync_cert'));

// Show delete confirmation dialog
if ($delete && !$confirm) {
    $send = $DB->get_record('local_sync_cert_send', ['id' => $delete]);

    if ($send) {
        $user = $DB->get_record('user', ['id' => $send->user_id]);
        $course = $DB->get_record('course', ['id' => $send->course_id]);

        echo $OUTPUT->confirm(
            get_string('confirm_delete_msg', 'local_sync_cert', [
                'user' => fullname($user),
                'course' => $course->fullname
            ]),
            new moodle_url('/local/sync_cert/failed_sends.php', [
                'delete' => $delete,
                'confirm' => 1,
                'sesskey' => sesskey()
            ]),
            new moodle_url('/local/sync_cert/failed_sends.php')
        );

        echo $OUTPUT->footer();
        exit;
    }
}

// Get failed sends
$sql = "SELECT s.id, s.cert_id, s.course_id, s.user_id, s.status,
               s.timecreated, s.timemodified,
               u.firstname, u.lastname, u.email, u.username,
               c.fullname as coursename, c.shortname
        FROM {local_sync_cert_send} s
        LEFT JOIN {user} u ON u.id = s.user_id
        LEFT JOIN {course} c ON c.id = s.course_id
        WHERE s.status = 0
        ORDER BY s.timemodified DESC";

$failedsends = $DB->get_records_sql($sql);

if (empty($failedsends)) {
    echo $OUTPUT->notification(get_string('no_failed_sends', 'local_sync_cert'), 'success');
} else {
    // Build table
    $table = new html_table();
    $table->head = [
        get_string('id', 'local_sync_cert'),
        get_string('user'),
        get_string('course'),
        get_string('cert_id', 'local_sync_cert'),
        get_string('last_modified', 'local_sync_cert'),
        get_string('attempts', 'local_sync_cert'),
        get_string('actions')
    ];

    $table->attributes['class'] = 'generaltable table-striped';

    foreach ($failedsends as $send) {
        // Count attempts
        $attempts = $DB->count_records('local_sync_cert_send_log', ['log_id' => $send->id]);

        // User name
        $username = fullname($send);
        if ($send->username) {
            $username .= ' (' . $send->username . ')';
        }

        // Course name
        $coursename = $send->coursename;
        if ($send->shortname) {
            $coursename .= ' (' . $send->shortname . ')';
        }

        // Actions buttons
        $actions = [];

        // View logs button
        $actions[] = html_writer::link(
            new moodle_url('/local/sync_cert/view_logs.php', ['id' => $send->id]),
            get_string('view_logs', 'local_sync_cert'),
            ['class' => 'btn btn-sm btn-info']
        );

        // Delete button
        $actions[] = html_writer::link(
            new moodle_url('/local/sync_cert/failed_sends.php', [
                'delete' => $send->id,
                'sesskey' => sesskey()
            ]),
            get_string('delete'),
            ['class' => 'btn btn-sm btn-danger']
        );

        $table->data[] = [
            $send->id,
            $username,
            $coursename,
            $send->cert_id,
            userdate($send->timemodified, get_string('strftimedatetime')),
            html_writer::tag('span', $attempts, ['class' => 'badge badge-warning']),
            implode(' ', $actions)
        ];
    }

    echo html_writer::table($table);
}

echo $OUTPUT->footer();
