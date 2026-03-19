<?php
defined('MOODLE_INTERNAL') || die();

class block_reportcsv extends block_base {

    public function init(): void {
        $this->title = get_string('pluginname', 'block_reportcsv');
    }

    public function has_config(): bool {
        return false;
    }

    public function instance_allow_config(): bool {
        // Solo chi può gestire i blocchi vede il form di configurazione
        return true;
    }

    public function applicable_formats(): array {
        return [
            'my'          => true,   // Dashboard utente
            'site'        => true,   // Home del sito
            'course-view' => false,
        ];
    }

    /**
     * Il blocco non richiede che l'utente sia loggato per mostrare il contenuto,
     * ma in pratica la dashboard è sempre autenticata.
     */
    public function get_content(): stdClass {
        global $CFG, $USER;

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content         = new stdClass();
        $this->content->text   = '';
        $this->content->footer = '';

        // Carica lib del plugin principale
        $lib = $CFG->dirroot . '/local/reportcsv/lib.php';
        if (!file_exists($lib)) {
            $this->content->text = html_writer::tag('p',
                'Plugin local_reportcsv non installato.',
                ['class' => 'text-muted small']
            );
            return $this->content;
        }
        require_once($lib);

        // Qualsiasi utente loggato può vedere i report nel blocco.
        // Se vuoi restringere, assegna la capability viewreports al ruolo student.
        if (!isloggedin() || isguestuser()) {
            return $this->content;
        }

        // Legge config dell'istanza (impostata dall'admin)
        $filter   = !empty($this->config->filter)   ? $this->config->filter   : '';
        $maxfiles = !empty($this->config->maxfiles)  ? (int)$this->config->maxfiles : 10;

        $files = local_reportcsv_get_csv_files($filter);
        if ($maxfiles > 0) {
            $files = array_slice($files, 0, $maxfiles);
        }

        if (empty($files)) {
            $this->content->text = html_writer::tag('p',
                get_string('no_reports', 'block_reportcsv'),
                ['class' => 'text-muted small']
            );
            return $this->content;
        }

        $download_url = new moodle_url('/local/reportcsv/download.php');

        $style = '<style>
.rcsv-block-table{width:100%;border-collapse:collapse;font-size:12px}
.rcsv-block-table th{text-align:left;padding:6px 8px;border-bottom:2px solid #dee2e6;color:#6c757d;font-size:11px;text-transform:uppercase;letter-spacing:.05em}
.rcsv-block-table td{padding:7px 8px;border-bottom:1px solid #f0f0f0;vertical-align:middle}
.rcsv-block-table tr:last-child td{border-bottom:none}
.rcsv-block-table tr:hover td{background:#f8f9fa}
.rcsv-dl-btn{display:inline-flex;align-items:center;gap:4px;padding:3px 8px;border-radius:4px;background:transparent;border:1px solid #dee2e6;color:#495057;font-size:11px;text-decoration:none;transition:all .12s}
.rcsv-dl-btn:hover{border-color:#4fffb0;color:#20c997}
.rcsv-fname{font-family:monospace;font-size:11px;color:#6c757d;word-break:break-all}
</style>';

        $icon = '<svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 4v11"/></svg>';

        $html  = $style;
        $html .= html_writer::start_tag('table', ['class' => 'rcsv-block-table']);
        $html .= '<thead><tr>';
        $html .= html_writer::tag('th', get_string('col_date',    'block_reportcsv'));
        $html .= html_writer::tag('th', get_string('col_filename', 'block_reportcsv'));
        $html .= html_writer::tag('th', '');
        $html .= '</tr></thead><tbody>';

        foreach ($files as $f) {
            $dl_url = $download_url->out(false, ['file' => $f['name']]);
            $html .= '<tr>';
            $html .= html_writer::tag('td', date('d/m/Y', $f['modified']));
            $html .= html_writer::tag('td',
                html_writer::tag('span', s($f['name']), ['class' => 'rcsv-fname'])
            );
            $html .= html_writer::tag('td',
                html_writer::tag('a', $icon . ' ' . get_string('download', 'block_reportcsv'),
                    ['href' => $dl_url, 'class' => 'rcsv-dl-btn'])
            );
            $html .= '</tr>';
        }

        $html .= '</tbody></table>';

        // Footer: link gestione solo per chi ha capability manage
        $context = context_system::instance();
        if (has_capability('local/reportcsv:managereports', $context)) {
            $admin_url = new moodle_url('/local/reportcsv/index.php');
            $this->content->footer = html_writer::tag('a',
                '⚙ ' . get_string('manage_link', 'block_reportcsv'),
                ['href' => $admin_url->out(false), 'class' => 'small text-muted']
            );
        }

        $this->content->text = $html;
        return $this->content;
    }
}
