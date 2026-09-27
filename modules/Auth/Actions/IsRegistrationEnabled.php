<?php

declare(strict_types=1);

namespace Modules\Auth\Actions;

final readonly class IsRegistrationEnabled
{
    public function handle(): bool
    {
        return (bool) config('auth.registration_enabled');
    }
}
