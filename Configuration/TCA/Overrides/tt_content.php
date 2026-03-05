<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') || die();

// Register the custom FlexForm column on tt_content
$GLOBALS['TCA']['tt_content']['columns']['tx_gedankenfolger_powermailredirect_flexform'] = [
    'exclude' => true,
    'label'   => 'LLL:EXT:gedankenfolger_powermail_redirect/Resources/Private/Language/locallang.xlf:tt_content.tx_gedankenfolger_powermailredirect_flexform',
    'config'  => [
        'type' => 'flex',
        'ds'   => [
            'default' => 'FILE:EXT:gedankenfolger_powermail_redirect/Configuration/FlexForms/RedirectFinisher.xml',
        ],
    ],
];

// Add the FlexForm field as a new tab on Powermail CEs only
ExtensionManagementUtility::addToAllTCAtypes(
    'tt_content',
    '--div--;LLL:EXT:gedankenfolger_powermail_redirect/Resources/Private/Language/locallang.xlf:tab.redirectFinisher,tx_gedankenfolger_powermailredirect_flexform',
    'powermail_pi1',
    'after:pi_flexform'
);
