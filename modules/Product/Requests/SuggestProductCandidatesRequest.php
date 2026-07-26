<?php

declare(strict_types=1);

namespace Modules\Product\Requests;

use App\Traits\HasCodedValidationMessages;
use Illuminate\Foundation\Http\FormRequest;

final class SuggestProductCandidatesRequest extends FormRequest
{
    use HasCodedValidationMessages;

    /**
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'query' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->codedValidationMessages();
    }
}
