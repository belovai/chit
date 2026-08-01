<?php

declare(strict_types=1);

namespace Modules\Pipeline\ValueObjects;

use Modules\Pipeline\Enums\RunStatus;

final readonly class RunPlan
{
    /**
     * @param  list<int>  $readyStepIds  steps to queue now
     * @param  list<int>  $skipStepIds  steps to mark skipped now
     */
    public function __construct(
        public array $readyStepIds,
        public array $skipStepIds,
        public bool $isComplete,
        public ?RunStatus $finalStatus,
        public bool $isAwaitingManual,
    ) {}
}
