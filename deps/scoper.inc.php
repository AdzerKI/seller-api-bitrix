<?php

use Isolated\Symfony\Component\Finder\Finder;

return [
    'prefix' => 'Masstech\\Opotopo\\Vendor',
    'exclude-namespaces' => ['~^Psr(\\\\|$)~'],
    'finders' => [
        Finder::create()->files()->in(__DIR__.'/vendor')->notPath('#^opotopo/seller-api/(vendor|tests)/#')->name(['*.php', '*.json', 'LICENSE']),
        Finder::create()->files()->in(__DIR__)->depth(0)->name('composer.json'),
    ],
];
