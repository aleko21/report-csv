<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_block_reportcsv_install() {
    global $DB;
    // Rimuove record orfani da installazioni precedenti fallite
    if ($DB->record_exists('block', ['name' => 'reportcsv'])) {
        $DB->delete_records('block', ['name' => 'reportcsv']);
    }
    return true;
}
