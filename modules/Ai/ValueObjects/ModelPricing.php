<?php

declare(strict_types=1);

namespace Modules\Ai\ValueObjects;

final readonly class ModelPricing
{
    public function __construct(
        public float $inputPerMillion,
        public float $outputPerMillion,
        public float $cachedInputPerMillion,
    ) {}
}
