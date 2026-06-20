<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use SavinMikhail\AnnotateThrowsRector\AnnotateThrowsRector;

return RectorConfig::configure()
    ->withConfiguredRule(AnnotateThrowsRector::class, [
        AnnotateThrowsRector::INCLUDE_UNCHECKED => true,
    ]);
