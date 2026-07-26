<?php

declare(strict_types=1);

namespace Modules\Merchant\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Modules\Merchant\Models\Merchant;

final readonly class UniqueMerchantNameForOwnerRule implements ValidationRule
{
    public function __construct(private int $ownerId, private ?int $excludeMerchantId = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $exists = Merchant::query()
            ->where('owner_id', $this->ownerId)
            ->when($this->excludeMerchantId, fn ($query, int $excludeMerchantId) => $query->where('id', '!=', $excludeMerchantId))
            ->whereRaw('lower(name) = lower(?)', [$value])
            ->exists();

        if ($exists) {
            $fail('merchant.duplicate_name');
        }
    }
}
