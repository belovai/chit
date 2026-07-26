<?php

declare(strict_types=1);

namespace Modules\Transaction\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Transaction\Models\Transaction;

final class UpdateTransaction
{
    /**
     * @param  array{
     *     merchant_id: int,
     *     location_id: int|null,
     *     currency: string,
     *     source: string,
     *     payment_method: string,
     *     discount_amount: float|null,
     *     total_amount: float,
     *     occurred_at: string,
     *     items: list<array{
     *         product_id: int|null,
     *         description: string,
     *         quantity: float,
     *         unit: string|null,
     *         unit_price: float,
     *     }>,
     * }  $validated
     */
    public function handle(Transaction $transaction, array $validated): Transaction
    {
        return DB::transaction(function () use ($transaction, $validated): Transaction {
            $transaction->update([
                'merchant_id' => $validated['merchant_id'],
                'location_id' => $validated['location_id'],
                'currency' => $validated['currency'],
                'source' => $validated['source'],
                'payment_method' => $validated['payment_method'],
                'discount_amount' => $validated['discount_amount'],
                'total_amount' => $validated['total_amount'],
                'occurred_at' => $validated['occurred_at'],
            ]);

            $transaction->items()->delete();
            $transaction->items()->createMany($validated['items']);

            return $transaction->refresh()->load(['merchant', 'location', 'items.product']);
        });
    }
}
