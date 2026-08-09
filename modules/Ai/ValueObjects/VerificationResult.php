<?php

declare(strict_types=1);

namespace Modules\Ai\ValueObjects;

use Modules\Ai\Enums\FailureKind;

final readonly class VerificationResult
{
    private function __construct(
        public bool $ok,
        public ?string $message = null,
        public ?FailureKind $kind = null,
    ) {}

    public static function ok(): self
    {
        return new self(true);
    }

    /** The key cannot be used: wrong, revoked, or without access to the model. */
    public static function failed(string $message): self
    {
        return new self(false, $message);
    }

    /**
     * The key is good; the account behind it cannot serve requests right now.
     * Reported separately so verifying an out-of-credit key does not disable a
     * credential that has nothing wrong with it.
     */
    public static function unusable(string $message): self
    {
        return new self(false, $message, FailureKind::Unusable);
    }
}
