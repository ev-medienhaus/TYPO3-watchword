<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Die Losungen',
    'description' => 'Import and display of the daily "Losungen" (watchwords)',
    'category' => 'plugin',
    'state' => 'stable',
    'clearCacheOnLoad' => true,
    'author' => 'Medienhaus EKHN',
    'author_email' => 'technik@ev-medienhaus.de',
    'author_company' => 'Medienhaus EKHN',
    'version' => '1.0.0',
    'autoload' => [
        'psr-4' => [
            'Emh\\Watchword\\' => 'Classes/',
        ],
    ],
    'constraints' => [
        'depends' => [
            'typo3' => '12.4.0-14.3.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
