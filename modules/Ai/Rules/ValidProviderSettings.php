<?php

declare(strict_types=1);

namespace Modules\Ai\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Modules\Ai\Registries\ProviderRegistry;

/**
 * Validates the submitted settings map against the provider's own
 * settingsSchema(). The same declaration drives the client's form, so a field
 * the client can render is a field this rule accepts.
 */
final class ValidProviderSettings implements ValidationRule
{
    public function __construct(
        private readonly ProviderRegistry $providers,
        private readonly ?string $providerId,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($this->providerId === null || !$this->providers->has($this->providerId)) {
            return;
        }

        if (!is_array($value)) {
            $fail('The settings must be an object.');

            return;
        }

        $schema = $this->providers->get($this->providerId)->settingsSchema();
        $known = [];

        foreach ($schema as $field) {
            $known[] = $field->key;

            if (!array_key_exists($field->key, $value)) {
                if ($field->required) {
                    $fail('The setting ['.$field->key.'] is required.');
                }

                continue;
            }

            $error = $field->validate($value[$field->key]);

            if ($error !== null) {
                $fail($error);
            }
        }

        foreach (array_keys($value) as $key) {
            if (!in_array($key, $known, true)) {
                $fail('Unknown setting ['.$key.'] for this provider.');
            }
        }
    }
}
