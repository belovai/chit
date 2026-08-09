<?php

declare(strict_types=1);

namespace Modules\Ai\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Modules\Ai\Registries\ProviderRegistry;

/**
 * The model must exist in the catalogue of the provider being submitted, so
 * the rule needs that provider id - it cannot be expressed as a static `in:`.
 */
final readonly class ValidProviderModel implements ValidationRule
{
    public function __construct(
        private ProviderRegistry $providers,
        private ?string $providerId,
    ) {}

    #[\Override]
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($this->providerId === null || !$this->providers->has($this->providerId)) {
            // The provider field has its own rule; do not report the same fault twice.
            return;
        }

        if (!is_string($value) || $this->providers->get($this->providerId)->model($value) === null) {
            $fail('ai.model_not_available');
        }
    }
}
