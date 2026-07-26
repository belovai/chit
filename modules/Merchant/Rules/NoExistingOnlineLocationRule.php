<?php

declare(strict_types=1);

namespace Modules\Merchant\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Modules\Merchant\Models\Merchant;

final readonly class NoExistingOnlineLocationRule implements ValidationRule
{
    public function __construct(private Merchant $merchant, private ?int $excludeLocationId = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $hasOtherOnlineLocation = $this->merchant
            ->locations()
            ->where('is_online', true)
            ->when($this->excludeLocationId, fn ($query, int $excludeLocationId) => $query->where('id', '!=', $excludeLocationId))
            ->exists();

        if ($value && $hasOtherOnlineLocation) {
            $fail('merchants.onlineLocationExists');
        }
    }
}
