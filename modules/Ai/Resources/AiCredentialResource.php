<?php

declare(strict_types=1);

namespace Modules\Ai\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Ai\Models\AiCredential;

/**
 * @mixin AiCredential
 */
final class AiCredentialResource extends JsonResource
{
    /**
     * The key itself is never present here — only its last four characters.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->hash_id,
            'provider' => $this->provider,
            'label' => $this->label,
            'model' => $this->model,
            'settings' => $this->settings,
            'is_active' => $this->is_active,
            'status' => $this->status->value,
            'masked_key' => $this->maskedKey(),
            'last_verified_at' => $this->last_verified_at?->toIso8601String(),
            'last_used_at' => $this->last_used_at?->toIso8601String(),
            'last_error' => $this->last_error,
        ];
    }
}
