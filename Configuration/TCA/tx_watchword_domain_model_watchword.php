<?php

return [
    'ctrl' => [
        'title' => 'LLL:EXT:watchword/Resources/Private/Language/locallang_db.xlf:tx_watchword_domain_model_watchword',
        'label' => 'date',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'cruser_id' => 'cruser_id',
        'delete' => 'deleted',
        'enablecolumns' => [
            'disabled' => 'hidden',
        ],
        'rootLevel' => 0,
        'hideTable' => 1,
        'searchFields' => 'watchword_text,teaching_text,sunday_name',
        'iconfile' => 'EXT:watchword/Resources/Public/Icons/Extension.svg',
    ],
    'types' => [
        '0' => [
            'showitem' => 'date, weekday, sunday_name, watchword_verse, watchword_text, teaching_verse, teaching_text, year, --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:access, hidden',
        ],
    ],
    'columns' => [
        'hidden' => [
            'exclude' => true,
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.visible',
            'config' => [
                'type' => 'check',
                'renderType' => 'checkboxToggle',
                'items' => [
                    [
                        0 => '',
                        1 => '',
                        'invertStateDisplay' => true,
                    ],
                ],
            ],
        ],
        'date' => [
            'exclude' => true,
            'label' => 'LLL:EXT:watchword/Resources/Private/Language/locallang_db.xlf:tx_watchword_domain_model_watchword.date',
            'config' => [
                'type' => 'datetime',
                'format' => 'date',
                'default' => 0,
            ],
        ],
        'weekday' => [
            'exclude' => true,
            'label' => 'LLL:EXT:watchword/Resources/Private/Language/locallang_db.xlf:tx_watchword_domain_model_watchword.weekday',
            'config' => [
                'type' => 'input',
                'size' => 20,
                'eval' => 'trim',
            ],
        ],
        'sunday_name' => [
            'exclude' => true,
            'label' => 'LLL:EXT:watchword/Resources/Private/Language/locallang_db.xlf:tx_watchword_domain_model_watchword.sunday_name',
            'config' => [
                'type' => 'input',
                'size' => 40,
                'eval' => 'trim',
            ],
        ],
        'watchword_verse' => [
            'exclude' => true,
            'label' => 'LLL:EXT:watchword/Resources/Private/Language/locallang_db.xlf:tx_watchword_domain_model_watchword.watchword_verse',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim',
            ],
        ],
        'watchword_text' => [
            'exclude' => true,
            'label' => 'LLL:EXT:watchword/Resources/Private/Language/locallang_db.xlf:tx_watchword_domain_model_watchword.watchword_text',
            'config' => [
                'type' => 'text',
                'cols' => 40,
                'rows' => 4,
            ],
        ],
        'teaching_verse' => [
            'exclude' => true,
            'label' => 'LLL:EXT:watchword/Resources/Private/Language/locallang_db.xlf:tx_watchword_domain_model_watchword.teaching_verse',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim',
            ],
        ],
        'teaching_text' => [
            'exclude' => true,
            'label' => 'LLL:EXT:watchword/Resources/Private/Language/locallang_db.xlf:tx_watchword_domain_model_watchword.teaching_text',
            'config' => [
                'type' => 'text',
                'cols' => 40,
                'rows' => 4,
            ],
        ],
        'year' => [
            'exclude' => true,
            'label' => 'LLL:EXT:watchword/Resources/Private/Language/locallang_db.xlf:tx_watchword_domain_model_watchword.year',
            'config' => [
                'type' => 'number',
                'size' => 10,
                'default' => 0,
            ],
        ],
    ],
];
