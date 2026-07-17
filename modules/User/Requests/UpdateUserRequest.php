<?php

declare(strict_types=1);

namespace Modules\User\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateUserRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'username' => ['string', 'max:255'],
            'email' => ['string', 'email', 'max:255', Rule::unique('users', 'email')->ignoreModel($this->user)],
            'password' => ['string', 'min:6'],
        ];
    }
}
