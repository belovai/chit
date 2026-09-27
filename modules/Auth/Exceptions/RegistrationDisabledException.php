<?php

declare(strict_types=1);

namespace Modules\Auth\Exceptions;

use RuntimeException;

final class RegistrationDisabledException extends RuntimeException
{
    public static function make(): self
    {
        return new self('Registration is disabled on this instance.');
    }
}
