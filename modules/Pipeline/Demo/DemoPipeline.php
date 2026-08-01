<?php

declare(strict_types=1);

namespace Modules\Pipeline\Demo;

use Modules\Pipeline\Definitions\PipelineDefinition;
use Modules\Pipeline\Demo\Steps\DemoClassifyStep;
use Modules\Pipeline\Demo\Steps\DemoCommitStep;
use Modules\Pipeline\Demo\Steps\DemoGateStep;
use Modules\Pipeline\Demo\Steps\DemoIngestStep;
use Modules\Pipeline\Demo\Steps\DemoReadStep;
use Modules\Pipeline\ValueObjects\StepDefinition;

/**
 * Mirrors the real receipt pipeline's SHAPE without any domain knowledge, so the
 * run list and detail UI can be built and reviewed before the Receipt module exists.
 */
final class DemoPipeline extends PipelineDefinition
{
    public function key(): string
    {
        return 'demo';
    }

    public function version(): int
    {
        return 1;
    }

    public function stages(): array
    {
        return ['ingest', 'read', 'classify', 'extract', 'review', 'commit'];
    }

    public function steps(): array
    {
        return [
            StepDefinition::make(DemoIngestStep::class)->inStage('ingest'),
            StepDefinition::make(DemoReadStep::class)->inStage('read')
                ->dependsOn('demo_ingest')->allowFailure(),
            StepDefinition::make(DemoClassifyStep::class)->inStage('classify')
                ->dependsOn('demo_read'),
            // `extract` starts empty — demo_classify fills it.
            StepDefinition::make(DemoGateStep::class)->inStage('review')->asGate(),
            StepDefinition::make(DemoCommitStep::class)->inStage('commit')
                ->dependsOn('demo_gate'),
        ];
    }
}
