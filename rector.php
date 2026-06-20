<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use SavinMikhail\AnnotateThrowsRector\AnnotateThrowsRector;

return RectorConfig::configure()
    ->withPaths(paths: [
        __DIR__ . '/src',
        __DIR__ . '/tests',
        __FILE__,
    ])
    ->withParallel()
    ->withCache(cacheDirectory: __DIR__ . '/var/rector')
    ->withPhpSets(php82: true)
    ->withRules(rules: [
        AnnotateThrowsRector::class,
    ]);
