<?php

declare(strict_types=1);

namespace Modules\Ai\Exceptions;

use InvalidArgumentException;

final class UnknownAiProviderException extends InvalidArgumentException
{
    public static function forId(string $id): self
    {
        return new self('Unknown AI provider ['.$id.'].');
    }
}
