<?php

declare(strict_types=1);

namespace Modules\Transaction\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;

final readonly class ExistsForOwnerRule implements ValidationRule
{
    /**
     * @param  class-string<Model>  $modelClass
     */
    public function __construct(
        private string $modelClass,
        private int $ownerId,
        private string $ownerColumn = 'owner_id',
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $exists = $this->modelClass::query()
            ->where($this->ownerColumn, $this->ownerId)
            ->where('hash_id', $value)
            ->exists();

        if (!$exists) {
            $fail('transaction.not_found');
        }
    }
}
