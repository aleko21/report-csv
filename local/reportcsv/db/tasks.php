<?php
defined('MOODLE_INTERNAL') || die();

// Definisce i task pianificati di default.
// L'admin può modificare schedule e abilitazione da
// Amministrazione sito → Server → Attività pianificate.
$tasks = [
    [
        'classname' => '\local_reportcsv\task\generate_reports',
        'blocking'  => 0,
        'minute'    => '0',
        'hour'      => '2',
        'day'       => '*',
        'month'     => '*',
        'dayofweek' => '*',
        'disabled'  => 1, // disabilitato di default, l'admin lo attiva
    ],
];
