<?php

declare(strict_types=1);

namespace App\Traits;

trait EnumCompares
{
    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function notEquals(self $other): bool
    {
        return !$this->equals($other);
    }
}
