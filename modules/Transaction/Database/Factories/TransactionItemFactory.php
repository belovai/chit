<?php

declare(strict_types=1);

namespace Modules\Transaction\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Transaction\Models\Transaction;
use Modules\Transaction\Models\TransactionItem;

/**
 * @extends Factory<TransactionItem>
 */
final class TransactionItemFactory extends Factory
{
    protected $model = TransactionItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'transaction_id' => Transaction::factory(),
            'product_id' => null,
            'description' => ucfirst(fake()->word().' '.fake()->word()),
            'quantity' => fake()->randomFloat(3, 1, 5),
            'unit' => 'db',
            'unit_price' => fake()->randomFloat(2, 100, 5000),
        ];
    }
}
