<?php

declare(strict_types=1);

namespace Modules\Ai\ValueObjects;

use Modules\Ai\Enums\SettingType;

/**
 * One provider setting, in the single form used by both consumers: the API
 * exposes it so the client can render a form field, and the validator checks
 * submitted values against it. One declaration, so the two cannot drift.
 */
final readonly class SettingField
{
    /**
     * @param  list<string>  $options
     */
    private function __construct(
        public string $key,
        public SettingType $type,
        public int|string|bool $default,
        public bool $required = true,
        public ?int $min = null,
        public ?int $max = null,
        public array $options = [],
    ) {}

    public static function int(string $key, int $default, int $min, int $max): self
    {
        return new self($key, SettingType::Int_, $default, min: $min, max: $max);
    }

    /**
     * @param  list<string>  $options
     */
    public static function enum(string $key, string $default, array $options): self
    {
        return new self($key, SettingType::Enum_, $default, options: $options);
    }

    public static function bool(string $key, bool $default): self
    {
        return new self($key, SettingType::Bool_, $default);
    }

    /**
     * The bounds and options themselves are not in the code: the client already
     * has them from settingsSchema(), which is what renders the input.
     *
     * @return string|null a machine error code, or null when the value is acceptable
     */
    public function validate(mixed $value): ?string
    {
        return match ($this->type) {
            SettingType::Int_ => $this->validateInt($value),
            SettingType::Enum_ => in_array($value, $this->options, true) ? null : 'ai.setting_not_an_option',
            SettingType::Bool_ => is_bool($value) ? null : 'ai.setting_not_a_boolean',
        };
    }

    private function validateInt(mixed $value): ?string
    {
        if (!is_int($value)) {
            return 'ai.setting_not_an_integer';
        }

        if ($this->min !== null && $value < $this->min) {
            return 'ai.setting_below_min';
        }

        if ($this->max !== null && $value > $this->max) {
            return 'ai.setting_above_max';
        }

        return null;
    }
}
