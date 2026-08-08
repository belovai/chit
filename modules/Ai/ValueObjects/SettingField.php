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
     * @return string|null an error message, or null when the value is acceptable
     */
    public function validate(mixed $value): ?string
    {
        return match ($this->type) {
            SettingType::Int_ => $this->validateInt($value),
            SettingType::Enum_ => in_array($value, $this->options, true)
                ? null
                : $this->key.' must be one of: '.implode(', ', $this->options).'.',
            SettingType::Bool_ => is_bool($value) ? null : $this->key.' must be a boolean.',
        };
    }

    private function validateInt(mixed $value): ?string
    {
        if (!is_int($value)) {
            return $this->key.' must be an integer.';
        }

        if ($this->min !== null && $value < $this->min) {
            return $this->key.' must be at least '.$this->min.'.';
        }

        if ($this->max !== null && $value > $this->max) {
            return $this->key.' must be at most '.$this->max.'.';
        }

        return null;
    }
}
