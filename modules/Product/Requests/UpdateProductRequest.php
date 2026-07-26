<?php

declare(strict_types=1);

namespace Modules\Product\Requests;

use App\Traits\HasCodedValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Modules\Product\Models\Product;
use Modules\Product\Rules\UniqueProductNameForOwnerRule;
use Modules\User\Models\User;

final class UpdateProductRequest extends FormRequest
{
    use HasCodedValidationMessages;

    /**
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        /** @var Product $product */
        $product = $this->route('product');
        /** @var User $user */
        $user = $this->user();

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                new UniqueProductNameForOwnerRule($user->id, $product->id),
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
