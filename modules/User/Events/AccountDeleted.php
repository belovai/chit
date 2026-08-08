<?php

declare(strict_types=1);

namespace Modules\User\Events;

use Illuminate\Foundation\Events\Dispatchable;

/** The user asked to be deleted. The row is soft-deleted; cleanup follows. */
final class AccountDeleted
{
    use Dispatchable;

    public function __construct(public readonly int $userId) {}
}
