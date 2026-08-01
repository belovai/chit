<?php

declare(strict_types=1);

namespace Modules\Pipeline\Demo\Steps;

use Modules\Pipeline\Contracts\PipelineStep;
use Modules\Pipeline\ValueObjects\StepContext;
use Modules\Pipeline\ValueObjects\StepDefinition;
use Modules\Pipeline\ValueObjects\StepResult;

final class DemoClassifyStep implements PipelineStep
{
    public static function key(): string
    {
        return 'demo_classify';
    }

    public static function queue(): string
    {
        return (string) config('pipeline.queues.ai');
    }

    public function handle(StepContext $context): StepResult
    {
        $result = StepResult::success()
            ->artifact('doc_type', ['value' => 'receipt'])
            ->confidence(0.94)
            ->cost(inputTokens: 620, outputTokens: 12, usdMicros: 1900);

        // Only the first attempt expands. A retry's downstream closure already
        // recreates a fresh demo_extract attempt, so expanding again here would
        // collide with that pre-created step key.
        if ($context->attempt() > 1) {
            return $result;
        }

        return $result->expand([
            StepDefinition::make(DemoExtractStep::class)
                ->inStage('extract')
                ->dependsOn('demo_classify'),
        ]);
    }
}
