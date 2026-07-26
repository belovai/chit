<?php

declare(strict_types=1);

namespace Modules\Transaction\Requests;

use App\Traits\HasCodedValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Merchant\Models\Merchant;
use Modules\Product\Models\Product;
use Modules\Transaction\Enums\PaymentMethod;
use Modules\Transaction\Enums\TransactionSource;
use Modules\Transaction\Rules\ExistsForOwnerRule;
use Modules\Transaction\Rules\LocationBelongsToMerchantRule;
use Modules\User\Models\User;

final class UpdateTransactionRequest extends FormRequest
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
            'merchant_id' => ['required', 'string', new ExistsForOwnerRule(Merchant::class, $user->id)],
            'location_id' => ['nullable', 'string', new LocationBelongsToMerchantRule($user->id, (string) $this->input('merchant_id'))],
            'currency' => ['required', 'string', 'size:3'],
            'source' => ['required', Rule::enum(TransactionSource::class)],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'total_amount' => ['required', 'numeric', 'min:0'],
            'occurred_at' => ['required', 'date'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['nullable', 'string', new ExistsForOwnerRule(Product::class, $user->id)],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001'],
            'items.*.unit' => ['nullable', 'string', 'max:50'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
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
