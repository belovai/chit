<?php

declare(strict_types=1);

namespace Modules\Merchant\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Merchant\Models\Merchant;
use Modules\User\Models\User;

final class UpdateMerchantRequest extends FormRequest
{
    /**
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        /** @var Merchant $merchant */
        $merchant = $this->route('merchant');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                function (string $attribute, mixed $value, \Closure $fail) use ($merchant): void {
                    /** @var User $user */
                    $user = $this->user();

                    $exists = Merchant::query()
                        ->where('owner_id', $user->id)
                        ->where('id', '!=', $merchant->id)
                        ->whereRaw('lower(name) = lower(?)', [$value])
                        ->exists();

                    if ($exists) {
                        $fail('A kereskedő ezzel a névvel már létezik.');
                    }
                },
            ],
        ];
    }
}
