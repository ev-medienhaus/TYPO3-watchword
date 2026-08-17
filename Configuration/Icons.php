<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider;

return [
    'module-watchword' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:watchword/Resources/Public/Icons/Extension.svg',
    ],
    'tx-watchword-domain-model-watchword' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:watchword/Resources/Public/Icons/Extension.svg',
    ],
];
