<?php

declare(strict_types=1);

namespace Modules\Ai\Exceptions;

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

    /**
     * Distinguishes "this key is wrong" from "the vendor is having a bad day".
     * Only the former should count against a credential's health.
     */
    public function isAuthFailure(): bool
    {
        if ($this->isRetryable()) {
            return false;
        }

        return str_starts_with($this->getMessage(), 'authentication_error')
            || str_starts_with($this->getMessage(), 'permission_error');
    }
}
