<?php

declare(strict_types=1);

namespace Modules\Pipeline\Tests\Support\Pipelines;

use Modules\Pipeline\Definitions\PipelineDefinition;
use Modules\Pipeline\Tests\Support\Steps\FakeFailingStep;
use Modules\Pipeline\Tests\Support\Steps\FakeSkippingStep;
use Modules\Pipeline\Tests\Support\Steps\FakeSuccessStep;
use Modules\Pipeline\ValueObjects\StepDefinition;

/** alpha:fake_success -> beta:fake_failing -> gamma:fake_skipping */
final class FakeLinearPipeline extends PipelineDefinition
{
    public function key(): string
    {
        return 'fake_linear';
    }

    public function version(): int
    {
        return 1;
    }

    public function stages(): array
    {
        return ['alpha', 'beta', 'gamma'];
    }

    public function steps(): array
    {
        return [
            StepDefinition::make(FakeSuccessStep::class)->inStage('alpha'),
            StepDefinition::make(FakeFailingStep::class)->inStage('beta')->dependsOn('fake_success'),
            StepDefinition::make(FakeSkippingStep::class)->inStage('gamma')->dependsOn('fake_failing'),
        ];
    }
}
