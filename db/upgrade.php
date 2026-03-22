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
 * Upgrade script for local_sync_cert
 *
 * @package    local_sync_cert
 * @copyright  2025 Jair Revilla
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

function xmldb_local_sync_cert_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2025101000) {

        // Define table local_sync_cert_api_calls to be created.
        $table = new xmldb_table('local_sync_cert_api_calls');

        // Adding fields to table local_sync_cert_api_calls.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('total_processed', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('success_count', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('error_count', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        // Adding keys to table local_sync_cert_api_calls.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Adding indexes to table local_sync_cert_api_calls.
        $table->add_index('timecreated_idx', XMLDB_INDEX_NOTUNIQUE, array('timecreated'));

        // Conditionally launch create table for local_sync_cert_api_calls.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Sync_cert savepoint reached.
        upgrade_plugin_savepoint(true, 2025101000, 'local', 'sync_cert');
    }

    return true;
}
