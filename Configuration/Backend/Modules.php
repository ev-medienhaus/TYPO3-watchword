<?php

declare(strict_types=1);

return [
    'web_watchword' => [
        'parent' => 'web',
        'position' => ['after' => 'web_list'],
        'access' => 'user',
        'iconIdentifier' => 'module-watchword',
        'path' => '/module/web/watchword',
        'labels' => 'LLL:EXT:watchword/Resources/Private/Language/locallang.xlf',
        'extensionName' => 'Watchword',
        'controllerActions' => [
            \Emh\Watchword\Controller\Backend\WatchwordController::class => [
                'index',
                'delete',
                'import',
                'importPreview',
                'importConfirm',
            ],
        ],
    ],
];
