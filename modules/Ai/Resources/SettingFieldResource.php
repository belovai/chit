<?php

declare(strict_types=1);

namespace Modules\Ai\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Ai\Enums\SettingType;
use Modules\Ai\ValueObjects\SettingField;

/**
 * @mixin SettingField
 */
final class SettingFieldResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'key' => $this->key,
            'type' => $this->type->value,
            'default' => $this->default,
            'required' => $this->required,
            ...($this->type === SettingType::Int_
                ? ['min' => $this->min, 'max' => $this->max]
                : []),
            ...($this->type === SettingType::Enum_
                ? ['options' => $this->options]
                : []),
        ];
    }
}
