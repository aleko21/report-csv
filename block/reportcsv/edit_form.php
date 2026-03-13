<?php
defined('MOODLE_INTERNAL') || die();

class block_reportcsv_edit_form extends block_edit_form {

    protected function specific_definition($mform): void {

        $mform->addElement('header', 'config_header', get_string('blocksettings', 'block'));

        // Filtro e max file: visibili SOLO all'admin (chi ha managereports)
        // Lo studente non vede mai questo form — può solo visualizzare il blocco.
        $context = context_system::instance();
        if (has_capability('local/reportcsv:managereports', $context)) {

            $mform->addElement(
                'text',
                'config_filter',
                'Filtro nome file'
            );
            $mform->setType('config_filter', PARAM_TEXT);

            $mform->addElement(
                'text',
                'config_maxfiles',
                'Numero massimo di file'
            );
            $mform->setType('config_maxfiles', PARAM_INT);
            $mform->setDefault('config_maxfiles', 10);

        } else {
            // Utente non admin: mostra solo un messaggio informativo
            $mform->addElement('static', 'config_info', '',
                html_writer::tag('p',
                    'Le impostazioni di questo blocco sono gestite dall\'amministratore.',
                    ['class' => 'text-muted small']
                )
            );
        }
    }
}
