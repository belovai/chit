<?php

declare(strict_types=1);

namespace Modules\Transaction\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Merchant\Models\Merchant;
use Modules\Transaction\Enums\PaymentMethod;
use Modules\Transaction\Enums\TransactionSource;
use Modules\Transaction\Models\Transaction;
use Modules\User\Models\User;

/**
 * @extends Factory<Transaction>
 */
final class TransactionFactory extends Factory
{
    protected $model = Transaction::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'owner_id' => User::factory(),
            'merchant_id' => Merchant::factory(),
            'location_id' => null,
            'currency' => 'HUF',
            'source' => TransactionSource::Manual,
            'payment_method' => PaymentMethod::Card,
            'discount_amount' => null,
            'total_amount' => fake()->randomFloat(2, 1000, 20000),
            'occurred_at' => fake()->date(),
        ];
    }
}
