<?php

declare(strict_types=1);

namespace Modules\Pipeline\Exceptions;

use RuntimeException;

final class ArtifactNotFoundException extends RuntimeException
{
    public static function forKey(string $key): self
    {
        return new self("No live artifact with key [{$key}] in this run.");
    }
}
