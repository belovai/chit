<?php

declare(strict_types=1);

namespace Modules\Pipeline\Tests\Support\Steps;

use Modules\Pipeline\Contracts\PipelineStep;
use Modules\Pipeline\ValueObjects\StepContext;
use Modules\Pipeline\ValueObjects\StepResult;

final class FakeSkippingStep implements PipelineStep
{
    public static function key(): string
    {
        return 'fake_skipping';
    }

    public static function queue(): string
    {
        return 'sync';
    }

    public function handle(StepContext $context): StepResult
    {
        return StepResult::skipped('fake skip');
    }
}
