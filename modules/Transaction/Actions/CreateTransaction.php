<?php

declare(strict_types=1);

namespace Modules\Transaction\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Transaction\Models\Transaction;

final class CreateTransaction
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
    public function handle(int $ownerId, array $validated): Transaction
    {
        return DB::transaction(function () use ($ownerId, $validated): Transaction {
            $transaction = Transaction::query()->create([
                'owner_id' => $ownerId,
                'merchant_id' => $validated['merchant_id'],
                'location_id' => $validated['location_id'],
                'currency' => $validated['currency'],
                'source' => $validated['source'],
                'payment_method' => $validated['payment_method'],
                'discount_amount' => $validated['discount_amount'],
                'total_amount' => $validated['total_amount'],
                'occurred_at' => $validated['occurred_at'],
            ]);

            $transaction->items()->createMany($validated['items']);

            return $transaction->load(['merchant', 'location', 'items.product']);
        });
    }
}
