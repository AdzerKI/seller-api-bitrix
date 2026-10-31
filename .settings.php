<?php

use Masstech\Opotopo\Platform;

return [
    'controllers' => [
        'value' => [
            'defaultNamespace' => '\\Masstech\\Opotopo\\Controller',
        ],
        'readonly' => true,
    ],
    'services' => [
        'value' => [
            Platform::TRANSPORT => ['constructor' => static fn () => Platform::transport()],
        ],
        'readonly' => true,
    ],
];
