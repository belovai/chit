<?php

declare(strict_types=1);

namespace Modules\Product\Actions;

use Illuminate\Support\Collection;
use Modules\Product\DataTransferObjects\ProductMatchDTO;
use Modules\Product\Models\Product;

final class SuggestProductCandidates
{
    /**
     * @return Collection<int, ProductMatchDTO>
     */
    public function handle(int $ownerId, string $rawName, ?float $threshold = null, ?int $limit = null): Collection
    {
        $threshold ??= (float) config('product.matching.threshold');
        $limit ??= (int) config('product.matching.limit');

        return Product::query()
            ->where('owner_id', $ownerId)
            ->selectRaw('products.*, similarity(name, ?) as score', [$rawName])
            ->whereRaw('similarity(name, ?) > ?', [$rawName, $threshold])
            ->orderByDesc('score')
            ->limit($limit)
            ->get()
            ->map(fn (Product $product): ProductMatchDTO => new ProductMatchDTO(
                product: $product,
                score: (float) $product->getAttribute('score'),
            ));
    }
}
