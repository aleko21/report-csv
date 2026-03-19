<?php
defined('MOODLE_INTERNAL') || die();

$capabilities = [

    // Aggiungere il blocco alla dashboard (solo admin/manager)
    'block/reportcsv:addinstance' => [
        'captype'      => 'write',
        'contextlevel' => CONTEXT_BLOCK,
        'archetypes'   => [
            'manager'       => CAP_ALLOW,
            'editingteacher'=> CAP_PREVENT,
            'student'       => CAP_PREVENT,
        ],
    ],

    // Aggiungere il blocco alla propria dashboard
    'block/reportcsv:myaddinstance' => [
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'manager' => CAP_ALLOW,
        ],
    ],
];
