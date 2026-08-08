<?php

declare(strict_types=1);

namespace Modules\Ai\Enums;

enum SettingType: string
{
    case Int_ = 'int';
    case Enum_ = 'enum';
    case Bool_ = 'bool';
}
