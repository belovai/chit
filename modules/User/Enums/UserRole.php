<?php

declare(strict_types=1);

namespace Modules\User\Enums;

use App\Traits\EnumCompares;
use Illuminate\Support\Str;

enum UserRole: string
{
    use EnumCompares;

    case Admin = 'admin';
    case User = 'user';

    public function label(): string
    {
        return Str::ucfirst($this->value);
    }

    /**
     * @return array{label: string, value: string}
     */
    public function resource(): array
    {
        return [
            'label' => $this->label(),
            'value' => $this->value,
        ];
    }
}
