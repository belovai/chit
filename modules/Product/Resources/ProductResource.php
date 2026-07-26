<?php

declare(strict_types=1);

namespace Modules\Product\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Product\Models\Product;

/**
 * @mixin Product
 */
final class ProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function toArray(Request $request): array
    {
        return [
            'hash_id' => $this->hash_id,
            'name' => $this->name,
        ];
    }
}
