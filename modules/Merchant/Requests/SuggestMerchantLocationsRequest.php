<?php

declare(strict_types=1);

namespace Modules\Merchant\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class SuggestMerchantLocationsRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'address' => ['nullable', 'string', 'max:500'],
        ];
    }
}
