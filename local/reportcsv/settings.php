<?php
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {

    // Aggiunge una categoria "Report CSV" sotto Amministrazione sito → Report
    $ADMIN->add('reports', new admin_category(
        'local_reportcsv_cat',
        get_string('pluginname', 'local_reportcsv')
    ));

    // Pagina settings
    $settings = new admin_settingpage(
        'local_reportcsv_settings',
        get_string('settings', 'local_reportcsv')
    );

    // --- Sezione Database ---
    $settings->add(new admin_setting_heading(
        'local_reportcsv/db_heading',
        'Connessione Database',
        ''
    ));

    $_rcsv_s = new admin_setting_configtext(
        'local_reportcsv/db_host',
        get_string('setting_db_host', 'local_reportcsv'),
        get_string('setting_db_host_desc', 'local_reportcsv'),
        'localhost'
    );
    $_rcsv_s->set_updatedcallback('local_reportcsv_update_env');
    $settings->add($_rcsv_s);

    $_rcsv_s = new admin_setting_configtext(
        'local_reportcsv/db_name',
        get_string('setting_db_name', 'local_reportcsv'),
        get_string('setting_db_name_desc', 'local_reportcsv'),
        $CFG->dbname ?? ''
    );
    $_rcsv_s->set_updatedcallback('local_reportcsv_update_env');
    $settings->add($_rcsv_s);

    $_rcsv_s = new admin_setting_configtext(
        'local_reportcsv/db_user',
        get_string('setting_db_user', 'local_reportcsv'),
        get_string('setting_db_user_desc', 'local_reportcsv'),
        $CFG->dbuser ?? ''
    );
    $_rcsv_s->set_updatedcallback('local_reportcsv_update_env');
    $settings->add($_rcsv_s);

    $_rcsv_s = new admin_setting_configpasswordunmask(
        'local_reportcsv/db_pass',
        get_string('setting_db_pass', 'local_reportcsv'),
        get_string('setting_db_pass_desc', 'local_reportcsv'),
        ''
    );
    $_rcsv_s->set_updatedcallback('local_reportcsv_update_env');
    $settings->add($_rcsv_s);

    $_rcsv_s = new admin_setting_configtext(
        'local_reportcsv/db_prefix',
        get_string('setting_db_prefix', 'local_reportcsv'),
        get_string('setting_db_prefix_desc', 'local_reportcsv'),
        $CFG->prefix ?? 'mdl_'
    );
    $_rcsv_s->set_updatedcallback('local_reportcsv_update_env');
    $settings->add($_rcsv_s);

    // --- Sezione Script ---
    // Controlla se lo script bash è presente e mostra un avviso nell'heading
    $script_current = get_config('local_reportcsv', 'export_script') ?: '';
    if (!file_exists($script_current) || !is_executable($script_current)) {
        $script_warning = html_writer::tag('div',
            '⚠️ <strong>Script bash non trovato o non eseguibile:</strong> ' .
            html_writer::tag('code', $script_current) .
            '<br>Copia manualmente <code>admin/cli/moodle_export.sh</code> nella root di Moodle ' .
            'e rendilo eseguibile con <code>chmod +x</code>. ' .
            'Il plugin funzionerà comunque con il fallback PHP nativo.',
            ['style' => 'background:#fff3cd;border:1px solid #ffc107;border-radius:6px;padding:12px 16px;margin:8px 0;font-size:13px;color:#856404']
        );
    } else {
        $script_warning = html_writer::tag('div',
            '✅ Script bash trovato e eseguibile: ' . html_writer::tag('code', $script_current),
            ['style' => 'background:#d1e7dd;border:1px solid #badbcc;border-radius:6px;padding:10px 16px;margin:8px 0;font-size:13px;color:#0f5132']
        );
    }

    $settings->add(new admin_setting_heading(
        'local_reportcsv/script_heading',
        'Script e percorsi',
        $script_warning
    ));

    $_rcsv_s = new admin_setting_configtext(
        'local_reportcsv/export_script',
        get_string('setting_export_script', 'local_reportcsv'),
        get_string('setting_export_script_desc', 'local_reportcsv'),
        ''
    );
    $_rcsv_s->set_updatedcallback('local_reportcsv_update_env');
    $settings->add($_rcsv_s);

    $_rcsv_s = new admin_setting_configtext(
        'local_reportcsv/output_subdir',
        get_string('setting_output_subdir', 'local_reportcsv'),
        get_string('setting_output_subdir_desc', 'local_reportcsv'),
        'reportcsv'
    );
    $_rcsv_s->set_updatedcallback('local_reportcsv_update_env');
    $settings->add($_rcsv_s);

    $ADMIN->add('local_reportcsv_cat', $settings);

    // Link alla pagina di gestione principale
    $ADMIN->add('local_reportcsv_cat', new admin_externalpage(
        'local_reportcsv_manage',
        get_string('manage', 'local_reportcsv'),
        new moodle_url('/local/reportcsv/index.php')
    ));
}
