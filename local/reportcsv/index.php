<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/reportcsv/lib.php');
require_once($CFG->dirroot . '/local/reportcsv/locallib.php');

require_login();
$context = context_system::instance();
require_capability('local/reportcsv:managereports', $context);

$PAGE->set_url(new moodle_url('/local/reportcsv/index.php'));
$PAGE->set_context($context);
$PAGE->set_title(get_string('manage', 'local_reportcsv'));
$PAGE->set_heading(get_string('manage', 'local_reportcsv'));
$PAGE->set_pagelayout('admin');

// ---------- Dati ----------
$sql_dir   = local_reportcsv_get_sql_dir();
$file_list = local_reportcsv_get_csv_files();
$sql_files = glob($sql_dir . '/*.sql') ?: [];
usort($sql_files, fn($a, $b) => filemtime($b) - filemtime($a));

$crontab      = shell_exec('crontab -l 2>/dev/null') ?? '';
$all_lines    = array_map('trim', explode("\n", $crontab));
$managed_jobs = array_filter($all_lines, fn($l) => str_contains($l, '# moodle-report:'));
$other_jobs   = array_filter($all_lines, fn($l) =>
    $l && $l[0] !== '#' &&
    !preg_match('/^[A-Z_]+=/', $l) &&
    !str_contains($l, '# moodle-report:')
);

$total_rows  = array_sum(array_column($file_list, 'rows'));
$last_update = !empty($file_list) ? date('d/m/Y', $file_list[0]['modified']) : '--';

$ajax_url     = (new moodle_url('/local/reportcsv/ajax.php'))->out(false);
$download_url = new moodle_url('/local/reportcsv/download.php');
$settings_url = new moodle_url('/admin/settings.php', ['section' => 'local_reportcsv_settings']);

$script_path = get_config('local_reportcsv', 'export_script') ?: '';
$script_ok   = $script_path && file_exists($script_path) && is_executable($script_path);

// ---------- Output ----------
echo $OUTPUT->header();

// Banner script bash
if (!$script_ok) {
    echo $OUTPUT->notification(
        get_string('script_missing', 'local_reportcsv', s($script_path ?: '(non configurato)')) .
        ' ' . html_writer::link($settings_url, get_string('go_to_settings', 'local_reportcsv')),
        \core\output\notification::NOTIFY_WARNING
    );
} else {
    echo $OUTPUT->notification(
        get_string('script_found', 'local_reportcsv', s($script_path)),
        \core\output\notification::NOTIFY_SUCCESS
    );
}

// Aggiorna silenziosamente il .env ad ogni caricamento
try { local_reportcsv_update_env(); } catch (\Throwable $e) { /* silenzioso */ }

// ================================================================
// SEZIONE 1 — Report CSV disponibili
// ================================================================
echo $OUTPUT->heading(get_string('tab_reports', 'local_reportcsv'), 3);

// Statistiche
$stat_table = new html_table();
$stat_table->attributes = ['class' => 'generaltable w-auto mb-3'];
$stat_table->head = [
    get_string('stat_files',   'local_reportcsv'),
    get_string('stat_rows',    'local_reportcsv'),
    get_string('stat_updated', 'local_reportcsv'),
    get_string('stat_queries', 'local_reportcsv'),
];
$stat_table->data = [[
    html_writer::tag('strong', count($file_list)),
    html_writer::tag('strong', number_format($total_rows, 0, ',', '.')),
    html_writer::tag('strong', $last_update),
    html_writer::tag('strong', count($sql_files)),
]];
echo html_writer::table($stat_table);

if (empty($file_list)) {
    echo $OUTPUT->notification(get_string('no_reports', 'local_reportcsv'), \core\output\notification::NOTIFY_INFO);
} else {
    $table = new html_table();
    $table->attributes = ['class' => 'generaltable'];
    $table->head = [
        get_string('col_date',     'local_reportcsv'),
        get_string('col_filename', 'local_reportcsv'),
        get_string('col_rows',     'local_reportcsv'),
        get_string('col_size',     'local_reportcsv'),
        get_string('actions',      'moodle'),
    ];
    $table->data = [];
    foreach ($file_list as $f) {
        $dl  = html_writer::link(
            $download_url->out(false, ['file' => $f['name']]),
            $OUTPUT->pix_icon('t/download', '') . ' ' . get_string('download', 'local_reportcsv'),
            ['class' => 'btn btn-sm btn-secondary mr-1']
        );
        $del = html_writer::tag('button',
            $OUTPUT->pix_icon('t/delete', '') . ' ' . get_string('delete', 'local_reportcsv'),
            ['class' => 'btn btn-sm btn-danger rcsv-del-csv', 'data-fn' => s($f['name'])]
        );
        $table->data[] = [
            date('d/m/Y H:i', $f['modified']),
            html_writer::tag('code', s($f['name'])),
            $f['rows'] == 0
                ? html_writer::tag('em', get_string('empty', 'local_reportcsv'), ['class' => 'text-muted'])
                : number_format($f['rows'], 0, ',', '.'),
            $f['size'],
            $dl . $del,
        ];
    }
    echo html_writer::table($table);
}

// ================================================================
// SEZIONE 2 — Editor SQL
// ================================================================
echo html_writer::empty_tag('hr');
echo $OUTPUT->heading(get_string('tab_editor', 'local_reportcsv'), 3);

echo html_writer::tag('p',
    get_string('editor_hint', 'local_reportcsv') .
    ' ' . html_writer::tag('code', '{user}') . ', ' .
    html_writer::tag('code', '{course}') . ', ' .
    html_writer::tag('code', '{course_modules}') . '…',
    ['class' => 'text-muted']
);

echo $OUTPUT->box_start('generalbox p-3');

echo html_writer::tag('textarea', '',
    ['id'          => 'rcsv_sql',
     'class'       => 'form-control mb-2',
     'rows'        => 10,
     'style'       => 'font-family:monospace;font-size:13px',
     'placeholder' => get_string('editor_placeholder', 'local_reportcsv')]
);

echo html_writer::start_div('form-inline mt-2');
echo html_writer::empty_tag('input', [
    'type'        => 'text',
    'id'          => 'rcsv_fn',
    'class'       => 'form-control form-control-sm mr-2',
    'placeholder' => get_string('filename_placeholder', 'local_reportcsv'),
    'style'       => 'max-width:220px',
]);
echo html_writer::tag('button',
    $OUTPUT->pix_icon('i/preview', '') . ' ' . get_string('test_query', 'local_reportcsv'),
    ['class' => 'btn btn-warning btn-sm mr-1', 'id' => 'btn_test']
);
echo html_writer::tag('button',
    $OUTPUT->pix_icon('t/download', '') . ' ' . get_string('download_test_csv', 'local_reportcsv'),
    ['class' => 'btn btn-secondary btn-sm mr-1', 'id' => 'btn_dl_test', 'style' => 'display:none']
);
echo html_writer::tag('button',
    $OUTPUT->pix_icon('t/download', '') . ' ' . get_string('download_full_csv', 'local_reportcsv'),
    ['class' => 'btn btn-secondary btn-sm mr-1', 'id' => 'btn_dl_full']
);
echo html_writer::tag('button',
    $OUTPUT->pix_icon('t/add', '') . ' ' . get_string('save_query', 'local_reportcsv'),
    ['class' => 'btn btn-primary btn-sm', 'id' => 'btn_save']
);
echo html_writer::end_div();

echo $OUTPUT->box_end();

// Area risultati test
echo html_writer::start_div('', ['id' => 'rcsv_result_wrap', 'style' => 'display:none']);
echo $OUTPUT->box_start('generalbox p-0');
echo html_writer::start_div('p-2 border-bottom d-flex justify-content-between align-items-center');
echo html_writer::tag('strong', '', ['id' => 'rcsv_result_label']);
echo html_writer::end_div();
echo html_writer::div('', '', ['id' => 'rcsv_result_body', 'style' => 'overflow-x:auto;max-height:320px;overflow-y:auto']);
echo $OUTPUT->box_end();
echo html_writer::end_div();

// ================================================================
// SEZIONE 3 — Query salvate
// ================================================================
echo html_writer::empty_tag('hr');
echo $OUTPUT->heading(get_string('tab_queries', 'local_reportcsv'), 3);

if (empty($sql_files)) {
    echo $OUTPUT->notification(get_string('no_queries', 'local_reportcsv'), \core\output\notification::NOTIFY_INFO);
} else {
    $table = new html_table();
    $table->attributes = ['class' => 'generaltable'];
    $table->head = [
        get_string('col_filename', 'local_reportcsv'),
        get_string('col_date',     'local_reportcsv'),
        get_string('actions',      'moodle'),
    ];
    $table->data = [];
    foreach ($sql_files as $sf) {
        $sn   = basename($sf);
        $load = html_writer::tag('button',
            $OUTPUT->pix_icon('t/edit', '') . ' ' . get_string('load', 'local_reportcsv'),
            ['class' => 'btn btn-sm btn-secondary mr-1 rcsv-load-query', 'data-fn' => s($sn)]
        );
        $dl   = html_writer::link(
            $download_url->out(false, ['file' => $sn, 'type' => 'sql']),
            $OUTPUT->pix_icon('t/download', '') . ' ' . get_string('download', 'local_reportcsv'),
            ['class' => 'btn btn-sm btn-secondary mr-1']
        );
        $del  = html_writer::tag('button',
            $OUTPUT->pix_icon('t/delete', '') . ' ' . get_string('delete', 'local_reportcsv'),
            ['class' => 'btn btn-sm btn-danger rcsv-del-query', 'data-fn' => s($sn)]
        );
        $table->data[] = [
            html_writer::tag('code', s($sn)),
            date('d/m/Y H:i', filemtime($sf)),
            $load . $dl . $del,
        ];
    }
    echo html_writer::table($table);
}

// ================================================================
// SEZIONE 4 — Pianificazione
// ================================================================
echo html_writer::empty_tag('hr');
echo $OUTPUT->heading(get_string('tab_schedule', 'local_reportcsv'), 3);

// Form aggiunta job
echo $OUTPUT->box_start('generalbox p-3 mb-3');
echo html_writer::start_div('form-row align-items-end');

// Query SQL
$sql_options = '';
if (empty($sql_files)) {
    $sql_options = html_writer::tag('option', get_string('no_queries', 'local_reportcsv'), ['value' => '']);
} else {
    foreach ($sql_files as $sf) {
        $sn = basename($sf);
        $sql_options .= html_writer::tag('option', s($sn), ['value' => s($sn)]);
    }
}
echo html_writer::start_div('form-group col-auto');
echo html_writer::tag('label', get_string('sql_query', 'local_reportcsv'), ['class' => 'col-form-label-sm d-block']);
echo html_writer::tag('select', $sql_options, ['id' => 'cron_sql', 'class' => 'form-control form-control-sm']);
echo html_writer::end_div();

// Frequenza
$freq_opts =
    html_writer::tag('option', get_string('freq_daily',   'local_reportcsv'), ['value' => 'daily']) .
    html_writer::tag('option', get_string('freq_weekly',  'local_reportcsv'), ['value' => 'weekly']) .
    html_writer::tag('option', get_string('freq_monthly', 'local_reportcsv'), ['value' => 'monthly']) .
    html_writer::tag('option', get_string('freq_hourly',  'local_reportcsv'), ['value' => 'hourly']);
echo html_writer::start_div('form-group col-auto');
echo html_writer::tag('label', get_string('frequency', 'local_reportcsv'), ['class' => 'col-form-label-sm d-block']);
echo html_writer::tag('select', $freq_opts, ['id' => 'cron_freq', 'class' => 'form-control form-control-sm', 'onchange' => 'rcsv_cron_hint()']);
echo html_writer::end_div();

// Giorno settimana
$wd_opts = '';
foreach (['1'=>'Lunedì','2'=>'Martedì','3'=>'Mercoledì','4'=>'Giovedì','5'=>'Venerdì','6'=>'Sabato','0'=>'Domenica'] as $v => $l) {
    $wd_opts .= html_writer::tag('option', $l, ['value' => $v]);
}
echo html_writer::start_div('form-group col-auto', ['id' => 'field_weekday', 'style' => 'display:none']);
echo html_writer::tag('label', get_string('weekday', 'local_reportcsv'), ['class' => 'col-form-label-sm d-block']);
echo html_writer::tag('select', $wd_opts, ['id' => 'cron_weekday', 'class' => 'form-control form-control-sm', 'onchange' => 'rcsv_cron_hint()']);
echo html_writer::end_div();

// Giorno mese
echo html_writer::start_div('form-group col-auto', ['id' => 'field_monthday', 'style' => 'display:none']);
echo html_writer::tag('label', get_string('monthday', 'local_reportcsv'), ['class' => 'col-form-label-sm d-block']);
echo html_writer::empty_tag('input', ['type' => 'number', 'id' => 'cron_monthday', 'class' => 'form-control form-control-sm',
    'min' => 1, 'max' => 28, 'value' => 1, 'style' => 'width:75px', 'onchange' => 'rcsv_cron_hint()']);
echo html_writer::end_div();

// Ora
echo html_writer::start_div('form-group col-auto', ['id' => 'field_hour']);
echo html_writer::tag('label', get_string('hour', 'local_reportcsv'), ['class' => 'col-form-label-sm d-block']);
echo html_writer::empty_tag('input', ['type' => 'number', 'id' => 'cron_hour', 'class' => 'form-control form-control-sm',
    'min' => 0, 'max' => 23, 'value' => 2, 'style' => 'width:70px', 'onchange' => 'rcsv_cron_hint()']);
echo html_writer::end_div();

// Minuto
echo html_writer::start_div('form-group col-auto');
echo html_writer::tag('label', get_string('minute', 'local_reportcsv'), ['class' => 'col-form-label-sm d-block']);
echo html_writer::empty_tag('input', ['type' => 'number', 'id' => 'cron_minute', 'class' => 'form-control form-control-sm',
    'min' => 0, 'max' => 59, 'value' => 0, 'style' => 'width:70px', 'onchange' => 'rcsv_cron_hint()']);
echo html_writer::end_div();

// Bottone
echo html_writer::start_div('form-group col-auto');
echo html_writer::tag('label', '&nbsp;', ['class' => 'col-form-label-sm d-block']);
echo html_writer::tag('button',
    $OUTPUT->pix_icon('t/add', '') . ' ' . get_string('add_cronjob', 'local_reportcsv'),
    ['class' => 'btn btn-primary btn-sm', 'id' => 'btn_add_cron']
);
echo html_writer::end_div();

echo html_writer::end_div(); // form-row
echo html_writer::tag('p', '', ['id' => 'cron_hint', 'class' => 'text-muted small mt-1']);
echo $OUTPUT->box_end();

// Tabella job gestiti
if (empty($managed_jobs)) {
    echo $OUTPUT->notification(get_string('no_cronjobs', 'local_reportcsv'), \core\output\notification::NOTIFY_INFO);
} else {
    echo $OUTPUT->heading(get_string('managed_jobs', 'local_reportcsv'), 4);
    $table = new html_table();
    $table->attributes = ['class' => 'generaltable'];
    $table->head = [
        get_string('col_schedule',  'local_reportcsv'),
        get_string('col_query',     'local_reportcsv'),
        get_string('col_cron_expr', 'local_reportcsv'),
        get_string('actions',       'moodle'),
    ];
    $table->data = [];
    foreach ($managed_jobs as $line) {
        $parts    = preg_split('/\s+/', trim($line), 6);
        $sch      = implode(' ', array_slice($parts, 0, 5));
        $rest     = $parts[5] ?? '';
        preg_match('/# moodle-report:(\S+)/', $rest, $tm);
        $tag_file = $tm[1] ?? '';
        $del      = html_writer::tag('button',
            $OUTPUT->pix_icon('t/delete', '') . ' ' . get_string('delete', 'local_reportcsv'),
            ['class' => 'btn btn-sm btn-danger rcsv-del-cron', 'data-fn' => s($tag_file)]
        );
        $table->data[] = [
            local_reportcsv_cron_desc($sch),
            html_writer::tag('code', s($tag_file)),
            html_writer::tag('code', s($sch)),
            $del,
        ];
    }
    echo html_writer::table($table);
}

// Altri job crontab
if (!empty($other_jobs)) {
    echo $OUTPUT->heading(get_string('other_jobs', 'local_reportcsv'), 4);
    $table = new html_table();
    $table->attributes = ['class' => 'generaltable'];
    $table->head = [get_string('col_cron_expr', 'local_reportcsv'), get_string('col_command', 'local_reportcsv')];
    $table->data = [];
    foreach ($other_jobs as $line) {
        $p = preg_split('/\s+/', trim($line), 6);
        $table->data[] = [
            html_writer::tag('code', s(implode(' ', array_slice($p, 0, 5)))),
            html_writer::tag('code', s($p[5] ?? '')),
        ];
    }
    echo html_writer::table($table);
}

// ================================================================
// JavaScript
// ================================================================
?>
<div id="rcsv-config"
     data-ajax="<?= $CFG->wwwroot ?>/local/reportcsv/ajax.php"
     style="display:none"></div>

<script>
(function() {

var ajaxBase = document.getElementById('rcsv-config').dataset.ajax;
var csvBuf   = '';

function post(data) {
    var sesskey = (window.M && M.cfg && M.cfg.sesskey) ? M.cfg.sesskey : '';
    var params  = new URLSearchParams(data);
    params.append('sesskey', sesskey);
    return fetch(ajaxBase, {
        method:  'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body:    params
    })
    .then(function(r) {
        if (!r.ok) throw new Error('HTTP ' + r.status);
        return r.json();
    })
    .catch(function(e) { return { ok: false, msg: e.message }; });
}

function notify(msg, type, anchorEl) {
    // Rimuovi notifiche precedenti dello stesso tipo
    document.querySelectorAll('.rcsv-notify').forEach(function(el) { el.remove(); });

    var map = { success: 'alert-success', error: 'alert-danger', warning: 'alert-warning', info: 'alert-info' };
    var div = document.createElement('div');
    div.className = 'alert ' + (map[type] || 'alert-info') + ' alert-dismissible fade show mt-2 rcsv-notify';
    div.setAttribute('role', 'alert');
    div.innerHTML = msg + '<button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>';

    // Inserisce vicino all'elemento ancora se fornito, altrimenti in cima
    var anchor = anchorEl || null;
    if (anchor) {
        anchor.parentNode.insertBefore(div, anchor.nextSibling);
        div.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    } else {
        var main = document.querySelector('[role="main"]') || document.body;
        main.insertBefore(div, main.firstChild);
        div.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
    setTimeout(function() { if (div.parentNode) div.remove(); }, 6000);
}

function testQuery() {
    var q = document.getElementById('rcsv_sql').value.trim();
    var btnTest = document.getElementById('btn_test');
    if (!q) { notify('Scrivi una query prima di procedere.', 'warning', btnTest); return; }
    var wrap  = document.getElementById('rcsv_result_wrap');
    var body  = document.getElementById('rcsv_result_body');
    var label = document.getElementById('rcsv_result_label');
    wrap.style.display = '';
    body.innerHTML = '<p class="text-muted p-3">Esecuzione in corso...</p>';
    wrap.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    post({ action: 'test_query', query: q }).then(function(d) {
        if (!d.ok) { body.innerHTML = '<div class="alert alert-danger m-2">' + d.msg + '</div>'; return; }
        if (!d.csv) { body.innerHTML = '<p class="text-muted p-3">Nessun risultato.</p>'; return; }
        csvBuf = d.csv;
        label.textContent = 'Anteprima - ' + d.rows + ' righe';
        var dl = document.getElementById('btn_dl_test');
        if (dl) dl.style.display = '';
        var rows = d.csv.trim().split('\n').map(function(r) {
            return r.split(';').map(function(c) {
                return c.replace(/^"|"$/g, '').replace(/""/g, '"');
            });
        });
        var h = '<table class="generaltable table table-sm mb-0"><thead><tr>';
        h += rows[0].map(function(c) { return '<th>' + c + '</th>'; }).join('');
        h += '</tr></thead><tbody>';
        rows.slice(1).forEach(function(r) {
            h += '<tr>' + r.map(function(c) { return '<td>' + c + '</td>'; }).join('') + '</tr>';
        });
        body.innerHTML = h + '</tbody></table>';
    });
}

function dlTestCsv() {
    if (!csvBuf) return;
    var a = document.createElement('a');
    a.href = URL.createObjectURL(new Blob([csvBuf], { type: 'text/csv' }));
    a.download = 'test_preview.csv';
    a.click();
}

function dlFullCsv() {
    var q = document.getElementById('rcsv_sql').value.trim();
    var btnFull = document.getElementById('btn_dl_full');
    if (!q) { notify('Scrivi una query prima di procedere.', 'warning', btnFull); return; }
    notify('Esecuzione in corso...', 'info', document.getElementById('btn_dl_full'));
    post({ action: 'run_full_query', query: q }).then(function(d) {
        if (!d.ok) { notify(d.msg, 'error', btnFull); return; }
        if (!d.csv) { notify('Nessun risultato.', 'warning', btnFull); return; }
        var fn  = (document.getElementById('rcsv_fn').value.trim()) || 'query';
        var now = new Date();
        var dd  = String(now.getDate()).padStart(2, '0');
        var mm  = String(now.getMonth() + 1).padStart(2, '0');
        var a   = document.createElement('a');
        a.href     = URL.createObjectURL(new Blob([d.csv], { type: 'text/csv' }));
        a.download = 'report_' + fn + '_' + dd + '_' + mm + '_' + now.getFullYear() + '.csv';
        a.click();
        notify('File scaricato (' + d.rows + ' righe)', 'success', btnFull);
    });
}

function saveQuery() {
    var q = document.getElementById('rcsv_sql').value.trim();
    var n = document.getElementById('rcsv_fn').value.trim();
    var btnSave = document.getElementById('btn_save');
    if (!q) { notify('Scrivi una query prima di procedere.', 'warning', btnSave); return; }
    if (!n) { notify('Specifica un nome per il file.', 'warning', btnSave); return; }
    post({ action: 'save_query', query: q, filename: n }).then(function(d) {
        notify(d.msg, d.ok ? 'success' : 'error', btnSave);
        if (d.ok) setTimeout(function() { location.reload(); }, 1000);
    });
}

function loadQuery(fn) {
    post({ action: 'load_query', filename: fn }).then(function(d) {
        if (d.ok) {
            document.getElementById('rcsv_sql').value = d.content;
            document.getElementById('rcsv_fn').value  = fn.replace(/\.sql$/, '');
            notify('Query caricata in editor.', 'success', document.getElementById('rcsv_sql'));
            document.getElementById('rcsv_sql').scrollIntoView({ behavior: 'smooth', block: 'center' });
            document.getElementById('rcsv_sql').focus();
        } else {
            notify(d.msg, 'error', document.getElementById('rcsv_sql'));
        }
    });
}

function cronHint() {
    var sel  = document.getElementById('cron_freq');
    if (!sel) return;
    var freq = sel.value;
    var h    = document.getElementById('cron_hour')    ? document.getElementById('cron_hour').value    : 2;
    var min  = document.getElementById('cron_minute')  ? document.getElementById('cron_minute').value  : 0;
    var m    = String(min).padStart(2, '0');
    var hh   = String(h).padStart(2, '0');
    var wd   = document.getElementById('cron_weekday');
    var md   = document.getElementById('cron_monthday');
    var fw   = document.getElementById('field_weekday');
    var fm   = document.getElementById('field_monthday');
    var fh   = document.getElementById('field_hour');
    if (fw) fw.style.display = freq === 'weekly'  ? '' : 'none';
    if (fm) fm.style.display = freq === 'monthly' ? '' : 'none';
    if (fh) fh.style.display = freq === 'hourly'  ? 'none' : '';
    var days = { 0:'Dom', 1:'Lun', 2:'Mar', 3:'Mer', 4:'Gio', 5:'Ven', 6:'Sab' };
    var txt  = '';
    if (freq === 'daily')   txt = 'Ogni giorno alle ' + hh + ':' + m;
    if (freq === 'weekly')  txt = 'Ogni settimana (' + days[wd ? wd.value : 1] + ') alle ' + hh + ':' + m;
    if (freq === 'monthly') txt = 'Ogni mese il giorno ' + (md ? md.value : 1) + ' alle ' + hh + ':' + m;
    if (freq === 'hourly')  txt = 'Ogni ora al minuto ' + m;
    var hint = document.getElementById('cron_hint');
    if (hint) hint.textContent = txt;
}

function addCron() {
    var sql = document.getElementById('cron_sql') ? document.getElementById('cron_sql').value : '';
    var btnCron = document.getElementById('btn_add_cron');
    if (!sql) { notify('Seleziona una query SQL.', 'warning', btnCron); return; }
    post({
        action:    'add_cron',
        sql_file:  sql,
        frequency: document.getElementById('cron_freq').value,
        hour:      document.getElementById('cron_hour')     ? document.getElementById('cron_hour').value     : 2,
        minute:    document.getElementById('cron_minute')   ? document.getElementById('cron_minute').value   : 0,
        weekday:   document.getElementById('cron_weekday')  ? document.getElementById('cron_weekday').value  : 1,
        monthday:  document.getElementById('cron_monthday') ? document.getElementById('cron_monthday').value : 1,
    }).then(function(d) {
        notify(d.msg, d.ok ? 'success' : 'error', btnCron);
        if (d.ok) setTimeout(function() { location.reload(); }, 1500);
    });
}

// Attacca i listener dopo che il DOM e pronto
document.addEventListener('DOMContentLoaded', function() {

    var el;

    // Bottoni editor
    el = document.getElementById('btn_test');
    if (el) el.addEventListener('click', testQuery);

    el = document.getElementById('btn_dl_test');
    if (el) el.addEventListener('click', dlTestCsv);

    el = document.getElementById('btn_dl_full');
    if (el) el.addEventListener('click', dlFullCsv);

    el = document.getElementById('btn_save');
    if (el) el.addEventListener('click', saveQuery);

    // Bottone add cron
    el = document.getElementById('btn_add_cron');
    if (el) el.addEventListener('click', addCron);

    // Cron selects
    ['cron_freq','cron_hour','cron_minute','cron_weekday','cron_monthday'].forEach(function(id) {
        el = document.getElementById(id);
        if (el) el.addEventListener('change', cronHint);
    });
    cronHint();

    // Delegazione per bottoni dinamici (delete csv, load/delete query, delete cron)
    document.addEventListener('click', function(e) {
        var btn = e.target.closest('button');
        if (!btn) return;
        var fn = btn.dataset.fn;

        if (btn.classList.contains('rcsv-del-csv')) {
            if (!confirm('Conferma eliminazione?')) return;
            post({ action: 'delete_csv', filename: fn }).then(function(d) {
                if (d.ok) location.reload(); else notify(d.msg, 'error', btn);
            });
        }
        if (btn.classList.contains('rcsv-load-query')) {
            loadQuery(fn);
        }
        if (btn.classList.contains('rcsv-del-query')) {
            if (!confirm('Conferma eliminazione?')) return;
            post({ action: 'delete_query', filename: fn }).then(function(d) {
                if (d.ok) location.reload(); else notify(d.msg, 'error', btn);
            });
        }
        if (btn.classList.contains('rcsv-del-cron')) {
            if (!confirm('Conferma eliminazione?')) return;
            post({ action: 'delete_cron', sql_file: fn }).then(function(d) {
                if (d.ok) location.reload(); else notify(d.msg, 'error', btn);
            });
        }
    });

}); // DOMContentLoaded

})(); // IIFE
</script>

<?php echo $OUTPUT->footer(); ?>
