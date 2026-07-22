<?php

declare(strict_types=1);

namespace Modules\Merchant\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class SuggestMerchantCandidatesRequest extends FormRequest
{
    /**
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'query' => ['required', 'string', 'max:255'],
        ];
    }
}
