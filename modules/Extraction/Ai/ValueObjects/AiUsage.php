<?php

declare(strict_types=1);

namespace Modules\Extraction\Ai\ValueObjects;

final readonly class AiUsage
{
    public function __construct(
        public int $inputTokens = 0,
        public int $outputTokens = 0,
        public int $cachedInputTokens = 0,
        public int $costUsdMicros = 0,
    ) {}

    public static function none(): self
    {
        return new self;
    }

    public function plus(self $other): self
    {
        return new self(
            inputTokens: $this->inputTokens + $other->inputTokens,
            outputTokens: $this->outputTokens + $other->outputTokens,
            cachedInputTokens: $this->cachedInputTokens + $other->cachedInputTokens,
            costUsdMicros: $this->costUsdMicros + $other->costUsdMicros,
        );
    }
}
