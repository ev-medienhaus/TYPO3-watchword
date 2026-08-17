<?php

declare(strict_types=1);

use Emh\Watchword\Controller\WatchwordController;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

defined('TYPO3') or die();

(static function (): void {
    ExtensionUtility::configurePlugin(
        'Watchword',
        'List',
        [
            WatchwordController::class => 'list',
        ],
        [
            // Non-cacheable: the result depends on the current calendar day, not on page cache lifetime.
            WatchwordController::class => 'list',
        ],
        ExtensionUtility::PLUGIN_TYPE_CONTENT_ELEMENT
    );

    ExtensionUtility::configurePlugin(
        'Watchword',
        'Ajax',
        [
            WatchwordController::class => 'ajax',
        ],
        [
            WatchwordController::class => 'ajax',
        ]
    );

    $GLOBALS['TYPO3_CONF_VARS']['FE']['cacheHash']['excludedParameters'][] = 'date';
    $GLOBALS['TYPO3_CONF_VARS']['FE']['cacheHash']['excludedParameters'][] = 'direction';
})();
