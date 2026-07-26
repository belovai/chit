<?php

declare(strict_types=1);

namespace Modules\Product\Requests;

use App\Traits\HasCodedValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Modules\Product\Rules\UniqueProductNameForOwnerRule;
use Modules\User\Models\User;

final class CreateProductRequest extends FormRequest
{
    use HasCodedValidationMessages;

    /**
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        /** @var User $user */
        $user = $this->user();

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                new UniqueProductNameForOwnerRule($user->id),
            ],
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
