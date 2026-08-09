<?php

declare(strict_types=1);

namespace Modules\Ai\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Modules\Ai\Models\AiCredential;

/**
 * Duplicate detection cannot be a `Rule::unique` on `api_key`: that column is
 * encrypted, so no two rows holding the same key share a ciphertext. The
 * comparison has to happen on the fingerprint of the submitted key.
 */
final readonly class UniqueApiKeyForProvider implements ValidationRule
{
    public function __construct(
        private int $ownerId,
        private ?string $providerId,
    ) {}

    #[\Override]
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($this->providerId === null || !is_string($value) || $value === '') {
            return;
        }

        $exists = AiCredential::query()
            ->forUser($this->ownerId)
            ->where('provider', $this->providerId)
            ->where('key_fingerprint', AiCredential::fingerprint($value))
            ->exists();

        if ($exists) {
            $fail('ai.duplicate_key');
        }
    }
}
