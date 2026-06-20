<?php

declare(strict_types=1);

namespace SavinMikhail\AnnotateThrowsRector\ValueObject;

final readonly class MethodAnalysis
{
    /**
     * @param string[] $existingThrows
     * @param string[] $directThrows
     * @param MethodCallEdge[] $methodCallEdges
     */
    public function __construct(
        public array $existingThrows,
        public array $directThrows,
        public array $methodCallEdges,
    ) {
    }
}
