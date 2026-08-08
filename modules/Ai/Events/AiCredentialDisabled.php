<?php

declare(strict_types=1);

namespace Modules\Ai\Events;

use Illuminate\Foundation\Events\Dispatchable;

final class AiCredentialDisabled
{
    use Dispatchable;

    public function __construct(
        public readonly int $credentialId,
        public readonly int $userId,
        public readonly string $reason,
    ) {}
}
