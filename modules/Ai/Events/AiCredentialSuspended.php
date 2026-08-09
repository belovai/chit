<?php

declare(strict_types=1);

namespace Modules\Ai\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * The credential is intact but the provider account cannot serve requests.
 * Nothing here is wrong with the key, so this is a nudge to the user rather
 * than a disable notice - the counterpart to AiCredentialDisabled.
 */
final class AiCredentialSuspended
{
    use Dispatchable;

    public function __construct(
        public readonly int $credentialId,
        public readonly int $userId,
        public readonly string $reason,
    ) {}
}
