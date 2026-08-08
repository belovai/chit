<?php

declare(strict_types=1);

namespace Modules\Ai\Enums;

enum CredentialStatus: string
{
    /** Stored but never successfully verified. */
    case Pending = 'pending';

    /** Verified against the provider and safe to use. */
    case Verified = 'verified';

    /** Recent authentication failures, below the disable threshold. */
    case Failing = 'failing';

    /** Too many authentication failures. Needs re-verification by the user. */
    case Disabled = 'disabled';

    public function isUsable(): bool
    {
        return $this === self::Verified;
    }
}
