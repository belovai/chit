<?php

declare(strict_types=1);

namespace Modules\Pipeline\ValueObjects;

use Modules\Pipeline\Enums\FindingSeverity;

/**
 * A structured observation a step makes about its input or output.
 * A finding is NOT a failure — a step may succeed while emitting blockers.
 * The gate step, not the emitting step, decides what a finding means.
 */
final readonly class Finding
{
    /**
     * @param  array<string, mixed>  $context
     */
    private function __construct(
        public string $code,
        public FindingSeverity $severity,
        public ?string $message = null,
        public array $context = [],
    ) {}

    /**
     * @param  array<string, mixed>  $context
     */
    public static function info(string $code, ?string $message = null, array $context = []): self
    {
        return new self($code, FindingSeverity::Info, $message, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function warning(string $code, ?string $message = null, array $context = []): self
    {
        return new self($code, FindingSeverity::Warning, $message, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function blocker(string $code, ?string $message = null, array $context = []): self
    {
        return new self($code, FindingSeverity::Blocker, $message, $context);
    }

    /**
     * @return array{code: string, severity: string, message: string|null, context: array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'severity' => $this->severity->value,
            'message' => $this->message,
            'context' => $this->context,
        ];
    }
}
