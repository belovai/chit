<?php

declare(strict_types=1);

namespace Modules\Ai\Enums;

use App\Traits\EnumCompares;

/**
 * What a failed provider call says about the credential behind it. Only the
 * provider adapter can tell these apart, so it classifies at throw time and
 * everything downstream reads the answer instead of guessing from a message.
 */
enum FailureKind: string
{
    use EnumCompares;

    /** Rate limits, overloads, 5xx, connection errors. Another attempt may work. */
    case Transient = 'transient';

    /** Bad request, refusal, unparseable output. The credential is not at fault. */
    case Permanent = 'permanent';

    /** The key itself was rejected: wrong, revoked, or lacking access. */
    case AuthFailure = 'auth_failure';

    /**
     * The key is valid but the account behind it cannot serve requests right
     * now - out of credit, no payment method, quota exhausted. Retrying burns
     * jobs, but the credential is not broken and must not be disabled: the
     * user fixes this at the provider, not here.
     */
    case Unusable = 'unusable';

    public function isRetryable(): bool
    {
        return $this === self::Transient;
    }
}
