<?php

declare(strict_types=1);

namespace Modules\Product\Actions;

use Modules\Product\Models\Product;

final class UpdateProduct
{
    /**
     * @param  array{name: string}  $validated
     */
    public function handle(Product $product, array $validated): Product
    {
        $product->update([
            'name' => $validated['name'],
        ]);

        return $product->refresh();
    }
}
