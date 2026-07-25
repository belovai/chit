<?php

declare(strict_types=1);

namespace Modules\User\Requests;

use App\Traits\HasCodedValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateUserRequest extends FormRequest
{
    use HasCodedValidationMessages;

    /**
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'username' => ['string', 'max:255'],
            'email' => ['string', 'email', 'max:255', Rule::unique('users', 'email')->ignoreModel($this->user)],
            'password' => ['string', 'min:6'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...$this->codedValidationMessages(),
            'password.min' => 'auth.password_too_short',
        ];
    }
}
