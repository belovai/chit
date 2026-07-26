<?php

declare(strict_types=1);

namespace Modules\Merchant\Actions;

use Modules\Merchant\Models\Merchant;
use Modules\Merchant\Models\MerchantLocation;

final class CreateMerchantLocation
{
    /**
     * @param  array{is_online: bool, address: string|null, latitude: float|null, longitude: float|null}  $validated
     */
    public function handle(Merchant $merchant, array $validated): MerchantLocation
    {
        return MerchantLocation::query()->create([
            'merchant_id' => $merchant->id,
            'is_online' => $validated['is_online'],
            'address' => $validated['address'] ?? null,
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
        ]);
    }
}
