<?php

declare(strict_types=1);

namespace Modules\Product\DataTransferObjects;

use Modules\Product\Models\Product;

final readonly class ProductMatchDTO
{
    public function __construct(
        public Product $product,
        public float $score,
    ) {}
}
