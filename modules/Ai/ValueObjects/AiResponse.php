<?php

declare(strict_types=1);

namespace Modules\Ai\ValueObjects;

final readonly class AiResponse
{
    /**
     * @param  array<string, mixed>  $payload  decoded JSON when a schema was requested, [] otherwise
     */
    public function __construct(
        public array $payload,
        public string $text,
        public AiUsage $usage,
    ) {}
}
