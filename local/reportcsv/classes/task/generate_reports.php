<?php
namespace local_reportcsv\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Scheduled task: esegue tutte le query SQL salvate e genera i CSV.
 * Funziona come fallback/alternativa ai cronjob di sistema.
 * Si attiva da: Amministrazione → Server → Attività pianificate.
 */
class generate_reports extends \core\task\scheduled_task {

    public function get_name(): string {
        return get_string('pluginname', 'local_reportcsv') . ' — Genera report';
    }

    public function execute(): void {
        global $CFG;
        require_once($CFG->dirroot . '/local/reportcsv/lib.php');

        $script  = get_config('local_reportcsv', 'export_script');
        $sql_dir = local_reportcsv_get_sql_dir();
        $log     = local_reportcsv_get_log_file();

        $sql_files = glob($sql_dir . '/*.sql') ?: [];

        if (empty($sql_files)) {
            mtrace('local_reportcsv: nessun file SQL trovato in ' . $sql_dir);
            return;
        }

        foreach ($sql_files as $sql_file) {
            $basename = basename($sql_file);
            mtrace("local_reportcsv: elaborazione $basename");

            if ($script && file_exists($script) && is_executable($script)) {
                // Usa lo script bash se disponibile
                $cmd    = escapeshellcmd($script) . ' ' . escapeshellarg($sql_file) . ' 2>>' . escapeshellarg($log);
                $output = shell_exec($cmd);
                mtrace("local_reportcsv: script completato per $basename");
            } else {
                // Fallback PHP nativo
                $this->generate_via_php($sql_file);
            }
        }
    }

    /**
     * Fallback: genera il CSV direttamente via PHP senza script bash.
     */
    private function generate_via_php(string $sql_file): void {
        require_once(dirname(__DIR__, 2) . '/lib.php');

        $raw   = file_get_contents($sql_file);
        $query = local_reportcsv_prepare_query($raw);

        [$rows, $error] = local_reportcsv_run_query($query);

        if ($error) {
            mtrace('local_reportcsv: ERRORE query ' . basename($sql_file) . ': ' . $error);
            return;
        }

        $csv      = local_reportcsv_rows_to_csv($rows);
        $basename = pathinfo($sql_file, PATHINFO_FILENAME);
        $date     = date('d_m_Y');
        $outfile  = local_reportcsv_get_output_dir() . '/report_' . $basename . '_' . $date . '.csv';

        file_put_contents($outfile, $csv);
        mtrace('local_reportcsv: generato ' . basename($outfile) . ' (' . count($rows) . ' righe)');
    }
}
