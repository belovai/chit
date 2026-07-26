<?php

declare(strict_types=1);

namespace Modules\Merchant\Actions;

use Modules\Merchant\Models\MerchantLocation;

final class UpdateMerchantLocation
{
    /**
     * @param  array{is_online: bool, address: string|null, latitude: float|null, longitude: float|null}  $validated
     */
    public function handle(MerchantLocation $merchantLocation, array $validated): MerchantLocation
    {
        $merchantLocation->update([
            'is_online' => $validated['is_online'],
            'address' => $validated['address'] ?? null,
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
        ]);

        return $merchantLocation->refresh();
    }
}
