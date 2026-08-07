<?php

declare(strict_types=1);

namespace Modules\User\Requests;

use App\Traits\HasCodedValidationMessages;
use Illuminate\Foundation\Http\FormRequest;

final class ChangeAccountPasswordRequest extends FormRequest
{
    use HasCodedValidationMessages;

    /**
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            // A `current_password` szabály alapból a web guardot nézi, itt viszont
            // Bearer tokennel jövünk — ezért kell a sanctum guard explicit.
            'current_password' => ['required', 'string', 'current_password:sanctum'],
            'password' => ['required', 'string', 'min:6', 'different:current_password'],
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
            'password.min' => 'auth.password_too_short',
            'password.different' => 'auth.password_must_differ',
        ];
    }
}
