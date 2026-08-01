<?php

declare(strict_types=1);

namespace Modules\Pipeline\Enums;

enum FindingSeverity: string
{
    case Info = 'info';
    case Warning = 'warning';
    case Blocker = 'blocker';
}
