<?php

declare(strict_types=1);

use SpoonerWeb\TcaBuilder\TcaBuilder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

defined('TYPO3') or die();

(static function (): void {
    $pluginSignature = ExtensionUtility::registerPlugin(
        'Watchword',
        'List',
        'LLL:EXT:watchword/Resources/Private/Language/locallang.xlf:plugin.title',
        'module-watchword',
        'default',
        'LLL:EXT:watchword/Resources/Private/Language/locallang.xlf:plugin.description'
    );

    $tcaBuilder = GeneralUtility::makeInstance(TcaBuilder::class);
    $tcaBuilder->setTable('tt_content')
        ->setType($pluginSignature)
        ->addDiv('Allgemein')
        ->addPalette('general')
        ->addPalette('ekd_header')
        ->addDiv('Erscheinungsbild')
        ->addPalette('ekd_access')
        ->saveToTca();
})();
