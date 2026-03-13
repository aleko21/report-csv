<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Restituisce il percorso assoluto della directory output in moodledata.
 * La crea se non esiste.
 */
function local_reportcsv_get_output_dir(): string {
    global $CFG;
    $subdir = get_config('local_reportcsv', 'output_subdir') ?: 'reportcsv';
    $path   = $CFG->dataroot . '/' . trim($subdir, '/');
    if (!is_dir($path)) {
        make_writable_directory($path);
    }
    return $path;
}

/**
 * Restituisce il percorso della directory SQL (dentro output_dir).
 */
function local_reportcsv_get_sql_dir(): string {
    $path = local_reportcsv_get_output_dir() . '/sql';
    if (!is_dir($path)) {
        make_writable_directory($path);
    }
    return $path;
}

/**
 * Restituisce il percorso del file di log.
 */
function local_reportcsv_get_log_file(): string {
    $path = local_reportcsv_get_output_dir() . '/log';
    if (!is_dir($path)) {
        make_writable_directory($path);
    }
    return $path . '/moodle_export.log';
}

/**
 * Restituisce la lista dei file CSV nella output_dir, ordinati per data desc.
 */
function local_reportcsv_get_csv_files(string $filter = ''): array {
    $dir   = local_reportcsv_get_output_dir();
    $files = glob($dir . '/report_*.csv') ?: [];
    $list  = [];
    foreach ($files as $fp) {
        $fn = basename($fp);
        if ($filter && stripos($fn, $filter) === false) {
            continue;
        }
        $size = filesize($fp);
        $list[] = [
            'name'     => $fn,
            'size'     => $size < 1024 ? $size . ' B' : round($size / 1024, 1) . ' KB',
            'rows'     => max(0, count(file($fp)) - 1),
            'modified' => filemtime($fp),
            'path'     => $fp,
        ];
    }
    usort($list, fn($a, $b) => $b['modified'] - $a['modified']);
    return $list;
}

/**
 * Sostituisce {table} con il prefisso reale e i placeholder temporali.
 */
function local_reportcsv_prepare_query(string $raw): string {
    $prefix = get_config('local_reportcsv', 'db_prefix') ?: 'mdl_';

    // Sostituisce {table} → prefisso reale
    $q = preg_replace_callback('/\{(\w+)\}/', fn($m) => $prefix . $m[1], $raw);

    // Sostituisce %%STARTTIME%% / %%ENDTIME%% con ieri se presenti
    if (str_contains($q, '%%STARTTIME%%') || str_contains($q, '%%ENDTIME%%')) {
        $start = mktime(0, 0, 0, date('n'), date('j') - 1, date('Y'));
        $end   = mktime(23, 59, 59, date('n'), date('j') - 1, date('Y'));
        $q     = str_replace('%%STARTTIME%%', $start, $q);
        $q     = str_replace('%%ENDTIME%%', $end, $q);
    }

    return rtrim(trim($q), ';');
}

/**
 * Esegue una query e restituisce [$rows, $error].
 */
function local_reportcsv_run_query(string $query, int $limit = 0): array {
    $host = get_config('local_reportcsv', 'db_host') ?: 'localhost';
    $name = get_config('local_reportcsv', 'db_name');
    $user = get_config('local_reportcsv', 'db_user');
    $pass = get_config('local_reportcsv', 'db_pass');

    try {
        $pdo = new PDO(
            "mysql:host=$host;dbname=$name;charset=utf8",
            $user, $pass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $q = $limit > 0
            ? "SELECT * FROM (\n$query\n) AS _preview LIMIT $limit"
            : $query;
        $rows = $pdo->query($q)->fetchAll(PDO::FETCH_ASSOC);
        return [$rows, null];
    } catch (Exception $e) {
        return [[], $e->getMessage()];
    }
}

/**
 * Converte array di righe in stringa CSV (separatore ;).
 */
function local_reportcsv_rows_to_csv(array $rows): string {
    if (empty($rows)) return '';
    $csv = implode(';', array_keys($rows[0])) . "\n";
    foreach ($rows as $r) {
        $csv .= implode(';', array_map(
            fn($v) => '"' . str_replace('"', '""', $v ?? '') . '"',
            $r
        )) . "\n";
    }
    return $csv;
}

/**
 * Descrizione leggibile di una schedule cron (5 campi).
 */
function local_reportcsv_cron_desc(string $expr): string {
    $p    = preg_split('/\s+/', trim($expr));
    $days = ['0' => 'Dom', '1' => 'Lun', '2' => 'Mar', '3' => 'Mer',
             '4' => 'Gio', '5' => 'Ven', '6' => 'Sab'];
    $hh   = str_pad($p[1] ?? '0', 2, '0', STR_PAD_LEFT);
    $mm   = str_pad($p[0] ?? '0', 2, '0', STR_PAD_LEFT);
    if (($p[1] ?? '') === '*') return "Ogni ora al minuto $mm";
    if (($p[4] ?? '') !== '*') return "Ogni settimana ({$days[$p[4]]}) alle $hh:$mm";
    if (($p[2] ?? '') !== '*') return "Ogni mese il giorno {$p[2]} alle $hh:$mm";
    return "Ogni giorno alle $hh:$mm";
}

/**
 * Restituisce il path del file .env in moodledata.
 */
function local_reportcsv_get_env_path(): string {
    global $CFG;
    $dir = $CFG->dataroot . '/reportcsv';
    make_writable_directory($dir);
    return $dir . '/.env';
}

/**
 * Genera/aggiorna il file .env leggibile dallo script bash.
 * Chiamata da settings.php via updatedcallback e da index.php.
 */
function local_reportcsv_update_env(): void {
    global $CFG;

    $output_dir = local_reportcsv_get_output_dir();
    $log_file   = local_reportcsv_get_log_file();

    $lines = [
        '# Generato automaticamente da local_reportcsv',
        '# Aggiornato: ' . date('Y-m-d H:i:s'),
        '',
        'REPORTCSV_DB_HOST="'   . addslashes(get_config('local_reportcsv', 'db_host')   ?: 'localhost')  . '"',
        'REPORTCSV_DB_PORT="3306"',
        'REPORTCSV_DB_NAME="'   . addslashes(get_config('local_reportcsv', 'db_name')   ?: $CFG->dbname) . '"',
        'REPORTCSV_DB_USER="'   . addslashes(get_config('local_reportcsv', 'db_user')   ?: $CFG->dbuser) . '"',
        'REPORTCSV_DB_PASS="'   . addslashes(get_config('local_reportcsv', 'db_pass')   ?: '')            . '"',
        'REPORTCSV_DB_PREFIX="' . addslashes(get_config('local_reportcsv', 'db_prefix') ?: $CFG->prefix)  . '"',
        'REPORTCSV_OUTPUT_DIR="' . $output_dir . '"',
        'REPORTCSV_LOG_FILE="'   . $log_file   . '"',
    ];

    $env_path = local_reportcsv_get_env_path();
    file_put_contents($env_path, implode("\n", $lines) . "\n");
    @chmod($env_path, 0600);
}

