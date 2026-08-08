<?php

declare(strict_types=1);

namespace Modules\Ai\Exceptions;

use RuntimeException;

final class NoActiveAiCredentialException extends RuntimeException
{
    public static function forUser(int $userId): self
    {
        // The user id stays out of the message: this message is user-facing.
        return new self('No usable AI credential is configured.');
    }

    /** The credential a run was started with is gone or no longer usable. */
    public static function missing(): self
    {
        return new self('The AI credential this run started with is no longer available.');
    }
}
