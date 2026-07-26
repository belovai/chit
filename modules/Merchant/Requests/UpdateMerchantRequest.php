<?php

declare(strict_types=1);

namespace Modules\Merchant\Requests;

use App\Traits\HasCodedValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Modules\Merchant\Models\Merchant;
use Modules\Merchant\Rules\UniqueMerchantNameForOwnerRule;
use Modules\User\Models\User;

final class UpdateMerchantRequest extends FormRequest
{
    use HasCodedValidationMessages;

    /**
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        /** @var Merchant $merchant */
        $merchant = $this->route('merchant');
        /** @var User $user */
        $user = $this->user();

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                new UniqueMerchantNameForOwnerRule($user->id, $merchant->id),
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
