<?php

declare(strict_types=1);

namespace Modules\Ai\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Ai\Contracts\AiProvider;

/**
 * @mixin AiProvider
 */
final class AiProviderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id(),
            'label' => $this->label(),
            'models' => ModelDescriptorResource::collection($this->models())->resolve(),
            'settings' => SettingFieldResource::collection($this->settingsSchema())->resolve(),
        ];
    }
}
