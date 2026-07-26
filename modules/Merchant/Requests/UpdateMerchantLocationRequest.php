<?php

declare(strict_types=1);

namespace Modules\Merchant\Requests;

use App\Traits\HasCodedValidationMessages;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Merchant\Models\Merchant;
use Modules\Merchant\Models\MerchantLocation;

final class UpdateMerchantLocationRequest extends FormRequest
{
    use HasCodedValidationMessages;

    /**
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'is_online' => [
                'required',
                'boolean',
                function (string $attribute, mixed $value, Closure $fail): void {
                    /** @var MerchantLocation $merchantLocation */
                    $merchantLocation = $this->route('merchantLocation');
                    /** @var Merchant $merchant */
                    $merchant = $merchantLocation->merchant;
                    $hasOtherOnlineLocation = $merchant
                        ->locations()
                        ->where('is_online', true)
                        ->where('id', '!=', $merchantLocation->id)
                        ->exists();
                    if ($value && $hasOtherOnlineLocation) {
                        $fail('merchants.onlineLocationExists');
                    }
                },
            ],
            'address' => [
                'nullable',
                'string',
                'max:255',
                Rule::requiredIf(fn (): bool => !$this->boolean('is_online')),
                Rule::prohibitedIf(fn (): bool => $this->boolean('is_online')),
            ],
            'latitude' => [
                'nullable',
                'numeric',
                'between:-90,90',
                Rule::prohibitedIf(fn (): bool => $this->boolean('is_online')),
            ],
            'longitude' => [
                'nullable',
                'numeric',
                'between:-180,180',
                Rule::prohibitedIf(fn (): bool => $this->boolean('is_online')),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...$this->codedValidationMessages(),
            'is_online.boolean' => 'boolean',
            'address.prohibited' => 'prohibited',
            'latitude.numeric' => 'numeric',
            'latitude.between' => 'between',
            'latitude.prohibited' => 'prohibited',
            'longitude.numeric' => 'numeric',
            'longitude.between' => 'between',
            'longitude.prohibited' => 'prohibited',
        ];
    }
}
