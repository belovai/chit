<?php

declare(strict_types=1);

namespace Modules\User\Requests;

use App\Traits\HasCodedValidationMessages;
use Illuminate\Foundation\Http\FormRequest;

final class DeleteAccountRequest extends FormRequest
{
    use HasCodedValidationMessages;

    /**
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            // Irreversible operation, so we confirm with a password — the
            // guard is given explicitly because we come in with a Bearer token.
            'current_password' => ['required', 'string', 'current_password:sanctum'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...$this->codedValidationMessages(),
            'current_password.current_password' => 'auth.invalid_password',
        ];
    }
}
