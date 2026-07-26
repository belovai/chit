<?php

declare(strict_types=1);

namespace Modules\Product\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Modules\Product\Models\Product;

final readonly class UniqueProductNameForOwnerRule implements ValidationRule
{
    public function __construct(private int $ownerId, private ?int $excludeProductId = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $exists = Product::query()
            ->where('owner_id', $this->ownerId)
            ->when($this->excludeProductId, fn ($query, int $excludeProductId) => $query->where('id', '!=', $excludeProductId))
            ->whereRaw('lower(name) = lower(?)', [$value])
            ->exists();

        if ($exists) {
            $fail('product.duplicate_name');
        }
    }
}
