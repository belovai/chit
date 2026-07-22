<?php

declare(strict_types=1);

namespace Modules\Merchant\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Merchant\Models\Merchant;
use Modules\User\Models\User;

/**
 * @extends Factory<Merchant>
 */
final class MerchantFactory extends Factory
{
    protected $model = Merchant::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'owner_id' => User::factory(),
            'name' => fake()->unique()->company(),
        ];
    }
}
