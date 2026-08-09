<?php

declare(strict_types=1);

namespace Modules\Ai\Enums;

use App\Traits\EnumCompares;

enum CredentialStatus: string
{
    use EnumCompares;

    /** Stored but never successfully verified. */
    case Pending = 'pending';

    /** Verified against the provider and safe to use. */
    case Verified = 'verified';

    /** Recent authentication failures, below the disable threshold. */
    case Failing = 'failing';

    /** Too many authentication failures. Needs re-verification by the user. */
    case Disabled = 'disabled';

    /**
     * The key is valid, the provider account cannot serve requests right now
     * (out of credit, quota exhausted). Held out of use so jobs stop burning,
     * but still active and untouched: the user fixes it at the provider and
     * verifies again.
     */
    case Suspended = 'suspended';

    public function isUsable(): bool
    {
        return $this === self::Verified;
    }
}
