<?php

declare(strict_types=1);

namespace Modules\Pipeline\Exceptions;

use RuntimeException;
use Throwable;

final class StepException extends RuntimeException
{
    private function __construct(string $message, private readonly bool $retryable, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    /** Transient failure (rate limit, timeout, 5xx) — worth another attempt. */
    public static function retryable(string $message, ?Throwable $previous = null): self
    {
        return new self($message, true, $previous);
    }

    /** Deterministic failure (bad input, parse error) — retrying cannot help. */
    public static function permanent(string $message, ?Throwable $previous = null): self
    {
        return new self($message, false, $previous);
    }

    public function isRetryable(): bool
    {
        return $this->retryable;
    }
}
