<?php

declare(strict_types=1);

namespace Modules\Pipeline\Tests\Support;

use Modules\Pipeline\Registries\PipelineRegistry;
use Modules\Pipeline\Registries\StepRegistry;
use Modules\Pipeline\Tests\Support\Pipelines\FakeExpandablePipeline;
use Modules\Pipeline\Tests\Support\Pipelines\FakeLinearPipeline;
use Modules\Pipeline\Tests\Support\Pipelines\FakeRetryPipeline;
use Modules\Pipeline\Tests\Support\Steps\FakeExpandedStep;
use Modules\Pipeline\Tests\Support\Steps\FakeExpandingStep;
use Modules\Pipeline\Tests\Support\Steps\FakeFailingStep;
use Modules\Pipeline\Tests\Support\Steps\FakeGateStep;
use Modules\Pipeline\Tests\Support\Steps\FakeSkippingStep;
use Modules\Pipeline\Tests\Support\Steps\FakeSuccessStep;

trait RegistersFakePipelines
{
    protected function registerFakePipelines(): void
    {
        $steps = app(StepRegistry::class);

        foreach ([
            FakeSuccessStep::class,
            FakeFailingStep::class,
            FakeSkippingStep::class,
            FakeGateStep::class,
            FakeExpandingStep::class,
            FakeExpandedStep::class,
        ] as $stepClass) {
            $steps->register($stepClass);
        }

        $pipelines = app(PipelineRegistry::class);
        $pipelines->register(new FakeLinearPipeline);
        $pipelines->register(new FakeExpandablePipeline);
        $pipelines->register(new FakeRetryPipeline);
    }
}
