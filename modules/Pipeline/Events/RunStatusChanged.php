<?php

declare(strict_types=1);

namespace Modules\Pipeline\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Modules\Pipeline\Enums\RunStatus;
use Modules\Pipeline\Models\PipelineRun;

/**
 * Emitted whenever the engine changes a run's status. Domain modules project
 * their own state from this instead of writing it themselves, which is what
 * keeps `receipts.status` a cache with exactly one writer.
 */
final class RunStatusChanged
{
    use Dispatchable;

    public function __construct(
        public readonly PipelineRun $run,
        public readonly ?RunStatus $from,
        public readonly RunStatus $to,
    ) {}
}
