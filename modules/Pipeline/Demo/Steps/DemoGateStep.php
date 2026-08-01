<?php

declare(strict_types=1);

namespace Modules\Pipeline\Demo\Steps;

use Modules\Pipeline\Contracts\PipelineStep;
use Modules\Pipeline\ValueObjects\Finding;
use Modules\Pipeline\ValueObjects\StepContext;
use Modules\Pipeline\ValueObjects\StepResult;

final class DemoGateStep implements PipelineStep
{
    public static function key(): string
    {
        return 'demo_gate';
    }

    public static function queue(): string
    {
        return (string) config('pipeline.queues.default');
    }

    public function handle(StepContext $context): StepResult
    {
        if ($context->config('auto_pass', false) === true) {
            return StepResult::success();
        }

        return StepResult::hold()
            ->artifact('review_request', ['fields' => ['merchant', 'total_amount']])
            ->finding(Finding::blocker('merchant_ambiguous', context: ['candidates' => ['ALDI', 'ALDI Sued']]));
    }
}
