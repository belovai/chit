<?php

declare(strict_types=1);

namespace Modules\Transaction\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Product\Resources\ProductResource;
use Modules\Transaction\Models\TransactionItem;

/**
 * @mixin TransactionItem
 */
final class TransactionItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function toArray(Request $request): array
    {
        return [
            'product' => $this->product ? ProductResource::make($this->product) : null,
            'description' => $this->description,
            'quantity' => $this->quantity,
            'unit' => $this->unit,
            'unit_price' => $this->unit_price,
        ];
    }
}
