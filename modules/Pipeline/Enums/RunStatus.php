<?php

declare(strict_types=1);

namespace Modules\Pipeline\Enums;

enum RunStatus: string
{
    case Queued = 'queued';
    case Running = 'running';
    case AwaitingManual = 'awaiting_manual';
    case Succeeded = 'succeeded';
    case Warning = 'warning';
    case Failed = 'failed';
    case Canceled = 'canceled';
    case Expired = 'expired';

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Succeeded, self::Warning, self::Failed, self::Canceled, self::Expired => true,
            self::Queued, self::Running, self::AwaitingManual => false,
        };
    }
}
