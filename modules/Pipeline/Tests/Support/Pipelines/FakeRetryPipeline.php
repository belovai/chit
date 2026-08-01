<?php

declare(strict_types=1);

namespace Modules\Pipeline\Tests\Support\Pipelines;

use Modules\Pipeline\Definitions\PipelineDefinition;
use Modules\Pipeline\Tests\Support\Steps\FakeFailingStep;
use Modules\Pipeline\Tests\Support\Steps\FakeSuccessStep;
use Modules\Pipeline\ValueObjects\StepDefinition;

/** alpha:fake_failing (3 attempts, allow_failure) -> beta:fake_success */
final class FakeRetryPipeline extends PipelineDefinition
{
    public function key(): string
    {
        return 'fake_retry';
    }

    public function version(): int
    {
        return 1;
    }

    public function stages(): array
    {
        return ['alpha', 'beta'];
    }

    public function steps(): array
    {
        return [
            StepDefinition::make(FakeFailingStep::class)->inStage('alpha')->maxAttempts(3)->allowFailure(),
            StepDefinition::make(FakeSuccessStep::class)->inStage('beta')->dependsOn('fake_failing'),
        ];
    }
}
