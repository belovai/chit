<?php

declare(strict_types=1);

namespace Modules\Pipeline\Enums;

enum RetryMode: string
{
    case Single = 'single';
    case From = 'from';
    case All = 'all';
}
