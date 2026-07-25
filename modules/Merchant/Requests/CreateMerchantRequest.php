<?php

declare(strict_types=1);

namespace Modules\Merchant\Requests;

use App\Traits\HasCodedValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Modules\Merchant\Models\Merchant;
use Modules\User\Models\User;

final class CreateMerchantRequest extends FormRequest
{
    use HasCodedValidationMessages;

    /**
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    /** @var User $user */
                    $user = $this->user();

                    $exists = Merchant::query()
                        ->where('owner_id', $user->id)
                        ->whereRaw('lower(name) = lower(?)', [$value])
                        ->exists();

                    if ($exists) {
                        $fail('merchant.duplicate_name');
                    }
                },
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
