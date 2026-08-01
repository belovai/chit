<?php

declare(strict_types=1);

namespace Modules\Pipeline\Enums;

enum TriggerSource: string
{
    case ManualUpload = 'manual_upload';
    case Email = 'email';
    case WatchFolder = 'watch_folder';
    case Api = 'api';
    case Retry = 'retry';
}
