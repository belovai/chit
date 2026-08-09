<?php

declare(strict_types=1);

namespace Modules\Ai\Enums;

use App\Traits\EnumCompares;

enum SettingType: string
{
    use EnumCompares;

    case Int_ = 'int';
    case Enum_ = 'enum';
    case Bool_ = 'bool';
}
