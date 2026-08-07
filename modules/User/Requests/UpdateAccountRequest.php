<?php

declare(strict_types=1);

namespace Modules\User\Requests;

use App\Traits\HasCodedValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\User\Models\User;

final class UpdateAccountRequest extends FormRequest
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
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignoreModel($user),
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
