<?php

declare(strict_types=1);

namespace Modules\Ai\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Ai\ValueObjects\ModelDescriptor;

/**
 * @mixin ModelDescriptor
 */
final class ModelDescriptorResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'capabilities' => array_map(
                fn ($capability): string => $capability->value,
                $this->capabilities,
            ),
            'pricing' => [
                'input' => $this->pricing->inputPerMillion,
                'output' => $this->pricing->outputPerMillion,
                'cached_input' => $this->pricing->cachedInputPerMillion,
            ],
        ];
    }
}
