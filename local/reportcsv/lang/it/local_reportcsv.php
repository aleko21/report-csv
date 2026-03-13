<?php
defined('MOODLE_INTERNAL') || die();

// Plugin
$string['pluginname']            = 'Report CSV';
$string['pluginname_desc']       = 'Genera report CSV da query SQL personalizzate e li rende disponibili per il download.';

// Settings
$string['settings']              = 'Impostazioni Report CSV';
$string['setting_db_host']       = 'Host database';
$string['setting_db_host_desc']  = 'Hostname del server MySQL/MariaDB (di solito localhost).';
$string['setting_db_name']       = 'Nome database';
$string['setting_db_name_desc']  = 'Nome del database Moodle.';
$string['setting_db_user']       = 'Utente database';
$string['setting_db_user_desc']  = 'Username per la connessione al database.';
$string['setting_db_pass']       = 'Password database';
$string['setting_db_pass_desc']  = 'Password per la connessione al database.';
$string['setting_db_prefix']     = 'Prefisso tabelle';
$string['setting_db_prefix_desc']= 'Prefisso delle tabelle Moodle (es. mdl_).';
$string['setting_export_script'] = 'Percorso script export';
$string['setting_export_script_desc'] = 'Percorso assoluto dello script bash. Esempio: <code>/var/www/html/moodle/admin/cli/moodle_export.sh</code>. Lascia vuoto per usare solo il fallback PHP.';
$string['setting_output_subdir'] = 'Sottocartella output in moodledata';
$string['setting_output_subdir_desc'] = 'Nome della sottocartella in moodledata dove salvare i CSV (es. reportcsv).';

// UI
$string['reports']               = 'Report CSV';
$string['manage']                = 'Gestione Report CSV';
$string['sql_editor']            = 'Editor Query SQL';
$string['saved_queries']         = 'Query salvate';
$string['cronjobs']              = 'Pianificazione';
$string['no_reports']            = 'Nessun report ancora generato.';
$string['no_queries']            = 'Nessuna query salvata.';
$string['no_cronjobs']           = 'Nessun job pianificato.';
$string['download']              = 'Scarica';
$string['delete']                = 'Elimina';
$string['save_query']            = 'Salva query';
$string['test_query']            = 'Test (prime 20 righe)';
$string['download_test_csv']     = 'Scarica CSV test';
$string['download_full_csv']     = 'Scarica CSV';
$string['add_cronjob']           = 'Aggiungi';
$string['frequency']             = 'Frequenza';
$string['freq_hourly']           = 'Ogni ora';
$string['freq_daily']            = 'Ogni giorno';
$string['freq_weekly']           = 'Ogni settimana';
$string['freq_monthly']          = 'Ogni mese';
$string['confirm_delete']        = 'Conferma eliminazione?';

// Block
$string['block_reportcsv']           = 'Report CSV';
$string['block_reportcsv_filter']    = 'Filtro nome file (opzionale)';
$string['block_reportcsv_filter_desc'] = 'Mostra solo i file il cui nome contiene questa stringa. Lascia vuoto per mostrare tutti.';
$string['block_no_reports']          = 'Nessun report disponibile.';
$string['block_reportcsv:addinstance']  = 'Aggiunge il blocco Report CSV';
$string['block_reportcsv:myaddinstance'] = 'Aggiunge il blocco Report CSV alla dashboard';

// Tabs
$string['tab_reports']  = 'Report CSV';
$string['tab_editor']   = 'Editor SQL';
$string['tab_queries']  = 'Query salvate';
$string['tab_schedule'] = 'Pianificazione';

// Stats
$string['stat_files']   = 'File CSV';
$string['stat_rows']    = 'Righe totali';
$string['stat_updated'] = 'Ultimo export';
$string['stat_queries'] = 'Query salvate';

// Colonne tabelle
$string['col_date']      = 'Data';
$string['col_filename']  = 'File';
$string['col_rows']      = 'Righe';
$string['col_size']      = 'Dim.';
$string['col_schedule']  = 'Frequenza';
$string['col_query']     = 'Query';
$string['col_cron_expr'] = 'Espressione cron';
$string['col_command']   = 'Comando';

// Editor
$string['editor_hint']        = 'Usa la notazione Moodle per le tabelle:';
$string['editor_placeholder'] = 'Scrivi o incolla qui la tua query SQL...';
$string['editor_empty']       = 'Scrivi una query prima di procedere.';
$string['filename_placeholder']= 'nome-file (senza .sql)';
$string['filename_required']  = 'Specifica un nome per il file.';
$string['running']            = 'Esecuzione in corso';
$string['no_results']         = 'Nessun risultato.';
$string['preview']            = 'Anteprima';
$string['downloaded']         = 'File scaricato';
$string['query_loaded']       = 'Query caricata in editor.';
$string['empty']              = 'vuoto';
$string['load']               = 'Carica';

// Cron
$string['managed_jobs'] = 'Job pianificati da questo pannello';
$string['other_jobs']   = 'Altri job nel crontab';
$string['sql_query']    = 'Query SQL';
$string['weekday']      = 'Giorno';
$string['monthday']     = 'Giorno del mese';
$string['hour']         = 'Ora';
$string['minute']       = 'Minuto';
$string['select_query'] = 'Seleziona una query SQL.';

// Script bash
$string['script_missing']    = 'Script bash non trovato o non eseguibile: {$a}. Il plugin userà il fallback PHP nativo.';
$string['script_found']      = 'Script bash attivo: {$a}';
$string['go_to_settings']    = 'Vai alle impostazioni';
