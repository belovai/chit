<?php

declare(strict_types=1);

namespace Modules\Ai\ValueObjects;

final readonly class ImagePart implements ContentPart
{
    public function __construct(public string $bytes, public string $mimeType) {}
}
