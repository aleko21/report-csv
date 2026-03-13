<?php
defined('MOODLE_INTERNAL') || die();

$capabilities = [

    // Può vedere i report CSV e scaricarli
    // Assegnata di default a tutti gli utenti autenticati
    'local/reportcsv:viewreports' => [
        'captype'      => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'user'              => CAP_ALLOW,   // utente autenticato base
            'student'           => CAP_ALLOW,
            'teacher'           => CAP_ALLOW,
            'editingteacher'    => CAP_ALLOW,
            'manager'           => CAP_ALLOW,
        ],
    ],

    // Può gestire query SQL, cronjob, impostazioni — solo admin/manager
    'local/reportcsv:managereports' => [
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'manager' => CAP_ALLOW,
        ],
    ],
];
