<?php

declare(strict_types=1);

namespace Modules\Product\Actions;

use Modules\Product\Models\Product;

final class DestroyProduct
{
    public function handle(Product $product): void
    {
        $product->delete();
    }
}
