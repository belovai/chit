<?php

declare(strict_types=1);

namespace Modules\Pipeline\Tests\Support\Pipelines;

use Modules\Pipeline\Definitions\PipelineDefinition;
use Modules\Pipeline\Tests\Support\Steps\FakeExpandingStep;
use Modules\Pipeline\Tests\Support\Steps\FakeGateStep;
use Modules\Pipeline\Tests\Support\Steps\FakeSuccessStep;
use Modules\Pipeline\ValueObjects\StepDefinition;

/**
 * alpha:fake_expanding -> (beta filled by expansion) -> review:fake_gate -> gamma:fake_success
 * `beta` starts empty on purpose: it is the stage the expansion targets.
 */
final class FakeExpandablePipeline extends PipelineDefinition
{
    public function key(): string
    {
        return 'fake_expandable';
    }

    public function version(): int
    {
        return 1;
    }

    public function stages(): array
    {
        return ['alpha', 'beta', 'review', 'gamma'];
    }

    public function steps(): array
    {
        return [
            StepDefinition::make(FakeExpandingStep::class)->inStage('alpha'),
            StepDefinition::make(FakeGateStep::class)->inStage('review')->asGate(),
            StepDefinition::make(FakeSuccessStep::class)->inStage('gamma')->dependsOn('fake_gate'),
        ];
    }
}
