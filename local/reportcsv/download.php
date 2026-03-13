<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/reportcsv/lib.php');

require_login();
require_capability('local/reportcsv:viewreports', context_system::instance());

$filename = required_param('file', PARAM_FILE);
$type     = optional_param('type', 'csv', PARAM_ALPHA); // csv | sql

if ($type === 'sql') {
    $path = local_reportcsv_get_sql_dir() . '/' . $filename;
} else {
    $path = local_reportcsv_get_output_dir() . '/' . $filename;
}

// Sicurezza: il file deve esistere e stare dentro le directory attese
if (!file_exists($path) || !str_starts_with(realpath($path), realpath(local_reportcsv_get_output_dir()))) {
    throw new moodle_exception('filenotfound', 'error');
}

$mime = $type === 'sql' ? 'application/sql' : 'text/csv';

header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($path));
readfile($path);
exit;
