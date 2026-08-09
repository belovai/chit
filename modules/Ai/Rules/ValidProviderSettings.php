<?php

declare(strict_types=1);

namespace Modules\Ai\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\ValidatorAwareRule;
use Illuminate\Validation\Validator;
use Modules\Ai\Registries\ProviderRegistry;

/**
 * Validates the submitted settings map against the provider's own
 * settingsSchema(). The same declaration drives the client's form, so a field
 * the client can render is a field this rule accepts.
 *
 * Per-setting problems are reported on `settings.<key>` rather than on
 * `settings`, so the client can put the message under the input that caused
 * it. That needs the validator itself, since `$fail` can only ever add to the
 * attribute the rule was declared on.
 */
final class ValidProviderSettings implements ValidationRule, ValidatorAwareRule
{
    private Validator $validator;

    public function __construct(
        private readonly ProviderRegistry $providers,
        private readonly ?string $providerId,
    ) {}

    #[\Override]
    public function setValidator(Validator $validator): static
    {
        $this->validator = $validator;

        return $this;
    }

    #[\Override]
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($this->providerId === null || !$this->providers->has($this->providerId)) {
            return;
        }

        if (!is_array($value)) {
            $fail('ai.settings_not_an_object');

            return;
        }

        $schema = $this->providers->get($this->providerId)->settingsSchema();
        $known = [];

        foreach ($schema as $field) {
            $known[] = $field->key;

            if (!array_key_exists($field->key, $value)) {
                if ($field->required) {
                    $this->failSetting($attribute, $field->key, 'ai.setting_required');
                }

                continue;
            }

            $error = $field->validate($value[$field->key]);

            if ($error !== null) {
                $this->failSetting($attribute, $field->key, $error);
            }
        }

        foreach (array_keys($value) as $key) {
            if (!in_array($key, $known, true)) {
                $this->failSetting($attribute, (string) $key, 'ai.setting_unknown');
            }
        }
    }

    private function failSetting(string $attribute, string $key, string $code): void
    {
        $this->validator->errors()->add($attribute.'.'.$key, $code);
    }
}
