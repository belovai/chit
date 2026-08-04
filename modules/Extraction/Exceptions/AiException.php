<?php

declare(strict_types=1);

namespace Modules\Extraction\Exceptions;

use RuntimeException;
use Throwable;

final class AiException extends RuntimeException
{
    private function __construct(string $message, private readonly bool $retryable, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    /** Rate limits, overloads, 5xx, connection errors — worth another attempt. */
    public static function retryable(string $message, ?Throwable $previous = null): self
    {
        return new self($message, true, $previous);
    }

    /** Bad request, auth failure, refusal, unparseable output — retrying cannot help. */
    public static function permanent(string $message, ?Throwable $previous = null): self
    {
        return new self($message, false, $previous);
    }

    public function isRetryable(): bool
    {
        return $this->retryable;
    }
}
