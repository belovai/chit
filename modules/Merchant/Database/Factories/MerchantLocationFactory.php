<?php

declare(strict_types=1);

namespace Modules\Merchant\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Merchant\Models\Merchant;
use Modules\Merchant\Models\MerchantLocation;

/**
 * @extends Factory<MerchantLocation>
 */
final class MerchantLocationFactory extends Factory
{
    protected $model = MerchantLocation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'merchant_id' => Merchant::factory(),
            'is_online' => false,
            'address' => fake()->address(),
            'latitude' => null,
            'longitude' => null,
        ];
    }

    /**
     * @return static
     */
    public function online(): self
    {
        return $this->state(fn (array $attributes): array => [
            'is_online' => true,
            'address' => null,
        ]);
    }
}
