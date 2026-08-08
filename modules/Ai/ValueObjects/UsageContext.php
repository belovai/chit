<?php

declare(strict_types=1);

namespace Modules\Ai\ValueObjects;

/**
 * Why a call was made, for the usage log. Carries no domain types — the
 * subject arrives already reduced to a morph pair.
 */
final readonly class UsageContext
{
    public function __construct(
        public string $purpose,
        public ?string $subjectType = null,
        public ?int $subjectId = null,
    ) {}

    public static function none(): self
    {
        return new self('unspecified');
    }
}
