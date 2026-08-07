<?php

declare(strict_types=1);

namespace Modules\User\Events;

/**
 * Fires before cleaning up data for a user marked for deletion. The User module
 * can't know the modules built on top of it, so each module cleans up its own
 * files in a listener — the DB rows are handled by the `users` cascade.
 */
final class UserPurging
{
    public function __construct(public readonly int $userId) {}
}
