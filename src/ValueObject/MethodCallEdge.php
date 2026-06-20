<?php

declare(strict_types=1);

namespace SavinMikhail\AnnotateThrowsRector\ValueObject;

final readonly class MethodCallEdge
{
    /**
     * @param string[] $caughtTypes
     */
    public function __construct(
        public ?string $className,
        public string $methodName,
        public array $caughtTypes,
    ) {
    }
}
