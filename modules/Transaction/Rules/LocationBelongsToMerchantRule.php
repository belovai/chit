<?php

declare(strict_types=1);

namespace Modules\Transaction\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Modules\Merchant\Models\MerchantLocation;

final readonly class LocationBelongsToMerchantRule implements ValidationRule
{
    public function __construct(private int $ownerId, private string $merchantHashId) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $exists = MerchantLocation::query()
            ->where('hash_id', $value)
            ->whereHas('merchant', fn ($query) => $query
                ->where('owner_id', $this->ownerId)
                ->where('hash_id', $this->merchantHashId))
            ->exists();

        if (!$exists) {
            $fail('transaction.location_not_found');
        }
    }
}
