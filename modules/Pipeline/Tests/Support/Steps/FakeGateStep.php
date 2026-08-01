<?php

declare(strict_types=1);

namespace Modules\Pipeline\Tests\Support\Steps;

use Modules\Pipeline\Contracts\PipelineStep;
use Modules\Pipeline\ValueObjects\Finding;
use Modules\Pipeline\ValueObjects\StepContext;
use Modules\Pipeline\ValueObjects\StepResult;

/** Holds unless `auto_pass` config is true — lets tests drive both gate branches. */
final class FakeGateStep implements PipelineStep
{
    public static function key(): string
    {
        return 'fake_gate';
    }

    public static function queue(): string
    {
        return 'sync';
    }

    public function handle(StepContext $context): StepResult
    {
        if ($context->config('auto_pass', false) === true) {
            return StepResult::success();
        }

        return StepResult::hold()
            ->artifact('review_request', ['fields' => ['total_amount']])
            ->finding(Finding::blocker('fake_blocker'));
    }
}
