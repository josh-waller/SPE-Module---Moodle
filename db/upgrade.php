<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_speval_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2025102700) {

    // Savepoint reached.
        upgrade_mod_savepoint(true, 2025102700, 'speval');
    }

    return true;
}