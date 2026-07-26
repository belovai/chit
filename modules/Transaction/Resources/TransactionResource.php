<?php

declare(strict_types=1);

namespace Modules\Transaction\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Merchant\Resources\MerchantLocationResource;
use Modules\Merchant\Resources\MerchantResource;
use Modules\Transaction\Models\Transaction;

/**
 * @mixin Transaction
 */
final class TransactionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function toArray(Request $request): array
    {
        return [
            'hash_id' => $this->hash_id,
            'merchant' => MerchantResource::make($this->merchant),
            'location' => $this->location ? MerchantLocationResource::make($this->location) : null,
            'currency' => $this->currency,
            'source' => $this->source->value,
            'payment_method' => $this->payment_method->value,
            'discount_amount' => $this->discount_amount,
            'total_amount' => $this->total_amount,
            'occurred_at' => $this->occurred_at->toDateString(),
            'items' => TransactionItemResource::collection($this->items),
        ];
    }
}
