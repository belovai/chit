<?php

declare(strict_types=1);

namespace Modules\Receipt\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Receipt\Models\Receipt;
use Modules\Transaction\Resources\TransactionResource;

/**
 * @mixin Receipt
 */
final class ReceiptDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function toArray(Request $request): array
    {
        return [
            ...(new ReceiptResource($this->resource))->toArray($request),
            'extracted' => $this->artifact('extracted_receipt')['payload']
                ?? $this->artifact('extracted_bill')['payload']
                ?? null,
            'candidates' => [
                'merchant' => $this->artifact('merchant_candidates'),
                'location' => $this->artifact('location_candidate'),
                'products' => $this->artifact('product_matches'),
                'previous_bill' => $this->artifact('previous_bill'),
            ],
            'review_request' => $this->artifact('review_request'),
            // What the extraction read and what was finally written are two
            // different things the moment a reviewer corrects a field, and the
            // artifacts above only ever hold the first. Null for every receipt
            // that produced no transaction — pending, rejected, failed.
            'transaction' => $this->transaction !== null
                ? TransactionResource::make($this->transaction->load(['merchant', 'location', 'items.product']))
                : null,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function artifact(string $key): ?array
    {
        return $this->currentRun?->artifacts()
            ->where('key', $key)
            ->whereNull('superseded_at')
            ->first()?->payload;
    }
}
