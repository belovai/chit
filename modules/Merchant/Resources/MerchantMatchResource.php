<?php

declare(strict_types=1);

namespace Modules\Merchant\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Merchant\DataTransferObjects\MerchantMatchDTO;

/**
 * @mixin MerchantMatchDTO
 */
final class MerchantMatchResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function toArray(Request $request): array
    {
        return [
            'merchant' => MerchantResource::make($this->merchant),
            'score' => $this->score,
        ];
    }
}
