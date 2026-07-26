<?php

declare(strict_types=1);

namespace Modules\Product\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Product\DataTransferObjects\ProductMatchDTO;

/**
 * @mixin ProductMatchDTO
 */
final class ProductMatchResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function toArray(Request $request): array
    {
        return [
            'product' => ProductResource::make($this->product),
            'score' => $this->score,
        ];
    }
}
