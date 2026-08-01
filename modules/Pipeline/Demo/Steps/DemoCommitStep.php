<?php

declare(strict_types=1);

namespace Modules\Pipeline\Demo\Steps;

use Modules\Pipeline\Contracts\PipelineStep;
use Modules\Pipeline\ValueObjects\StepContext;
use Modules\Pipeline\ValueObjects\StepResult;

final class DemoCommitStep implements PipelineStep
{
    public static function key(): string
    {
        return 'demo_commit';
    }

    public static function queue(): string
    {
        return (string) config('pipeline.queues.default');
    }

    public function handle(StepContext $context): StepResult
    {
        return StepResult::success()->artifact('demo_committed', ['ok' => true]);
    }
}
