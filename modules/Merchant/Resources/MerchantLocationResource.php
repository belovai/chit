<?php

declare(strict_types=1);

namespace Modules\Merchant\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Merchant\Models\MerchantLocation;

/**
 * @mixin MerchantLocation
 */
final class MerchantLocationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function toArray(Request $request): array
    {
        return [
            'hash_id' => $this->hash_id,
            'is_online' => $this->is_online,
            'address' => $this->address,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
        ];
    }
}
