<?php

declare(strict_types=1);

namespace Modules\Merchant\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Merchant\Models\Merchant;

/**
 * @mixin Merchant
 */
final class MerchantResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function toArray(Request $request): array
    {
        return [
            'hash_id' => $this->hash_id,
            'name' => $this->name,
        ];
    }
}
