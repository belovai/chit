<?php

declare(strict_types=1);

namespace Modules\Pipeline\Exceptions;

use RuntimeException;

final class UnknownPipelineException extends RuntimeException
{
    public static function forKey(string $key): self
    {
        return new self("No pipeline definition registered under key [{$key}].");
    }
}
