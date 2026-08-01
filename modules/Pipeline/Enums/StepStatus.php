<?php

declare(strict_types=1);

namespace Modules\Pipeline\Enums;

enum StepStatus: string
{
    case Pending = 'pending';
    case Queued = 'queued';
    case Running = 'running';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Skipped = 'skipped';
    case Canceled = 'canceled';
    case AwaitingManual = 'awaiting_manual';
    case Expired = 'expired';

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Succeeded, self::Failed, self::Skipped, self::Canceled, self::Expired => true,
            self::Pending, self::Queued, self::Running, self::AwaitingManual => false,
        };
    }

    public function isSuccessful(): bool
    {
        return $this === self::Succeeded;
    }
}
