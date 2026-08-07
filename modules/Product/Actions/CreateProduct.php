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
        $name = $validated['name'];

        // Case-insensitive per owner is a DB constraint (products_owner_id_name_unique),
        // not just a validation rule — callers like the receipt pipeline create
        // products without going through request validation, and two lines on the
        // same receipt can resolve to the same new product name.
        $existing = Product::query()
            ->where('owner_id', $ownerId)
            ->whereRaw('lower(name) = lower(?)', [$name])
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return Product::query()->create([
            'owner_id' => $ownerId,
            'name' => $name,
        ]);
    }
}
