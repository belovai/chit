<?php

declare(strict_types=1);

namespace Modules\Ai\ValueObjects;

final readonly class TextPart implements ContentPart
{
    public function __construct(public string $text) {}
}
