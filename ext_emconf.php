<?php

$EM_CONF[$_EXTKEY] = [
    'title'            => 'Gedankenfolger Powermail Redirect',
    'description'      => 'Powermail finisher to redirect to a target page and transfer field values via GET parameters.',
    'category'         => 'plugin',
    'author'           => 'Gedankenfolger',
    'state'            => 'stable',
    'version'          => '13.2.0',
    'constraints'      => [
        'depends' => [
            'typo3'     => '13.0.0-13.99.99',
            'powermail'  => '13.0.0-13.99.99',
        ],
    ],
];
