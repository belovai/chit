<?php

declare(strict_types=1);

namespace Modules\Auth\ValueObjects;

use Modules\User\Models\User;

final readonly class AccessToken
{
    public function __construct(
        public string $plainText,
        public User $user,
    ) {}
}
