<?php

declare(strict_types=1);

namespace Modules\Product\Actions;

use Modules\Product\Models\Product;

final class CreateProduct
{
    /**
     * @param  array{name: string}  $validated
     */
    public function handle(int $ownerId, array $validated): Product
    {
        return Product::query()->create([
            'owner_id' => $ownerId,
            'name' => $validated['name'],
        ]);
    }
}
