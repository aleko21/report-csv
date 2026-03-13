<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/reportcsv/lib.php');

require_login();
require_capability('local/reportcsv:managereports', context_system::instance());

// Legge sesskey e lo verifica (Moodle standard)
$sesskey = optional_param('sesskey', '', PARAM_RAW);
if (!confirm_sesskey($sesskey)) {
    header('Content-Type: application/json');
    die(json_encode(['ok' => false, 'msg' => 'Sessione scaduta. Ricarica la pagina.']));
}

header('Content-Type: application/json');

// Legge action sia da POST che da GET
$action = isset($_POST['action']) ? clean_param($_POST['action'], PARAM_ALPHANUMEXT)
        : (isset($_GET['action'])  ? clean_param($_GET['action'],  PARAM_ALPHANUMEXT) : '');

if (!$action) {
    die(json_encode(['ok' => false, 'msg' => 'Azione mancante']));
}

// Helper: legge parametro da POST o GET
function rcsv_param($name, $default = '') {
    if (isset($_POST[$name])) return $_POST[$name];
    if (isset($_GET[$name]))  return $_GET[$name];
    return $default;
}

// ---------- Salva query ----------
if ($action === 'save_query') {
    $name    = clean_param(rcsv_param('filename'), PARAM_FILE);
    $content = rcsv_param('query');
    if (!$name) die(json_encode(['ok' => false, 'msg' => 'Nome file mancante']));
    if (!str_ends_with($name, '.sql')) $name .= '.sql';
    file_put_contents(local_reportcsv_get_sql_dir() . '/' . $name, $content);
    die(json_encode(['ok' => true, 'msg' => "Salvato: $name"]));
}

// ---------- Carica query ----------
if ($action === 'load_query') {
    $name = clean_param(rcsv_param('filename'), PARAM_FILE);
    $path = local_reportcsv_get_sql_dir() . '/' . $name;
    die(file_exists($path)
        ? json_encode(['ok' => true, 'content' => file_get_contents($path)])
        : json_encode(['ok' => false, 'msg' => 'File non trovato']));
}

// ---------- Elimina query ----------
if ($action === 'delete_query') {
    $name = clean_param(rcsv_param('filename'), PARAM_FILE);
    $path = local_reportcsv_get_sql_dir() . '/' . $name;
    if (file_exists($path) && str_ends_with($name, '.sql')) {
        unlink($path);
        die(json_encode(['ok' => true]));
    }
    die(json_encode(['ok' => false, 'msg' => 'File non trovato']));
}

// ---------- Elimina CSV ----------
if ($action === 'delete_csv') {
    $name = clean_param(rcsv_param('filename'), PARAM_FILE);
    $path = local_reportcsv_get_output_dir() . '/' . $name;
    if (file_exists($path) && str_ends_with($name, '.csv')) {
        unlink($path);
        die(json_encode(['ok' => true]));
    }
    die(json_encode(['ok' => false, 'msg' => 'File non trovato']));
}

// ---------- Test query ----------
if ($action === 'test_query') {
    $raw   = rcsv_param('query');
    $query = local_reportcsv_prepare_query($raw);
    $query = preg_replace('/\bLIMIT\s+\d+(\s*,\s*\d+)?\b/i', '', $query);
    [$rows, $error] = local_reportcsv_run_query($query, 20);
    if ($error) die(json_encode(['ok' => false, 'msg' => $error]));
    if (empty($rows)) die(json_encode(['ok' => true, 'csv' => '', 'rows' => 0]));
    die(json_encode(['ok' => true, 'csv' => local_reportcsv_rows_to_csv($rows), 'rows' => count($rows)]));
}

// ---------- Query completa → CSV ----------
if ($action === 'run_full_query') {
    $raw   = rcsv_param('query');
    $query = local_reportcsv_prepare_query($raw);
    $query = preg_replace('/\bLIMIT\s+\d+(\s*,\s*\d+)?\b/i', '', $query);
    [$rows, $error] = local_reportcsv_run_query($query);
    if ($error) die(json_encode(['ok' => false, 'msg' => $error]));
    die(json_encode(['ok' => true, 'csv' => local_reportcsv_rows_to_csv($rows), 'rows' => count($rows)]));
}

// ---------- Aggiungi cronjob ----------
if ($action === 'add_cron') {
    $sql_file  = clean_param(rcsv_param('sql_file'), PARAM_FILE);
    $frequency = clean_param(rcsv_param('frequency'), PARAM_ALPHA);
    $hour      = (int) rcsv_param('hour', 2);
    $minute    = (int) rcsv_param('minute', 0);
    $weekday   = (int) rcsv_param('weekday', 1);
    $monthday  = (int) rcsv_param('monthday', 1);

    $sql_path = local_reportcsv_get_sql_dir() . '/' . $sql_file;
    if (!file_exists($sql_path)) die(json_encode(['ok' => false, 'msg' => 'File SQL non trovato']));

    $script = get_config('local_reportcsv', 'export_script');
    $env_path = local_reportcsv_get_env_path();
    $cmd    = 'REPORTCSV_ENV_FILE=' . escapeshellarg($env_path) .
              ' ' . escapeshellcmd($script) .
              ' ' . escapeshellarg($sql_path);

    switch ($frequency) {
        case 'hourly':  $expr = "$minute * * * *"; break;
        case 'daily':   $expr = "$minute $hour * * *"; break;
        case 'weekly':  $expr = "$minute $hour * * $weekday"; break;
        case 'monthly': $expr = "$minute $hour $monthday * *"; break;
        default: die(json_encode(['ok' => false, 'msg' => 'Frequenza non valida']));
    }

    $current = shell_exec('crontab -l 2>/dev/null') ?? '';
    if (str_contains($current, 'moodle-report:' . $sql_file)) {
        die(json_encode(['ok' => false, 'msg' => 'Esiste gia un cronjob per ' . $sql_file]));
    }

    $tag     = '# moodle-report:' . $sql_file;
    $new_job = $expr . ' ' . $cmd . ' ' . $tag;
    $tmp = tempnam(sys_get_temp_dir(), 'cron');
    file_put_contents($tmp, rtrim($current) . "\n" . $new_job . "\n");
    shell_exec('crontab ' . escapeshellarg($tmp));
    unlink($tmp);
    die(json_encode(['ok' => true, 'msg' => 'Cronjob aggiunto']));
}

// ---------- Elimina cronjob ----------
if ($action === 'delete_cron') {
    $sql_file = clean_param(rcsv_param('sql_file'), PARAM_FILE);
    $current  = shell_exec('crontab -l 2>/dev/null') ?? '';
    $lines    = explode("\n", $current);
    $filtered = array_filter($lines, fn($l) => !str_contains($l, 'moodle-report:' . $sql_file));
    $tmp = tempnam(sys_get_temp_dir(), 'cron');
    file_put_contents($tmp, implode("\n", $filtered) . "\n");
    shell_exec('crontab ' . escapeshellarg($tmp));
    unlink($tmp);
    die(json_encode(['ok' => true]));
}

die(json_encode(['ok' => false, 'msg' => 'Azione sconosciuta: ' . $action]));
