<?php

declare(strict_types=1);

namespace Modules\Ai\Exceptions;

use Modules\Ai\Enums\FailureKind;
use RuntimeException;
use Throwable;

final class AiException extends RuntimeException
{
    private function __construct(
        string $message,
        private readonly FailureKind $kind,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    /** Rate limits, overloads, 5xx, connection errors - worth another attempt. */
    public static function retryable(string $message, ?Throwable $previous = null): self
    {
        return new self($message, FailureKind::Transient, $previous);
    }

    /** Bad request, refusal, unparseable output - retrying cannot help. */
    public static function permanent(string $message, ?Throwable $previous = null): self
    {
        return new self($message, FailureKind::Permanent, $previous);
    }

    /**
     * The credential itself was rejected: a wrong, revoked, or under-privileged
     * key. Only the provider adapter can tell this apart from a vendor outage,
     * so it declares it at throw time rather than leaving the caller to sniff
     * the message for one vendor's error vocabulary.
     */
    public static function authFailure(string $message, ?Throwable $previous = null): self
    {
        return new self($message, FailureKind::AuthFailure, $previous);
    }

    /**
     * The key is good, the account behind it cannot serve requests right now.
     * Distinct from an auth failure: further calls are pointless, but nothing
     * is broken and the credential must survive intact.
     */
    public static function unusable(string $message, ?Throwable $previous = null): self
    {
        return new self($message, FailureKind::Unusable, $previous);
    }

    public function kind(): FailureKind
    {
        return $this->kind;
    }

    public function isRetryable(): bool
    {
        return $this->kind->isRetryable();
    }

    /**
     * Distinguishes "this key is wrong" from "the vendor is having a bad day".
     * Only the former should count against a credential's health.
     */
    public function isAuthFailure(): bool
    {
        return $this->kind === FailureKind::AuthFailure;
    }
}
