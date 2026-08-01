<?php

declare(strict_types=1);

namespace Modules\Pipeline\Demo\Steps;

use Modules\Pipeline\Contracts\PipelineStep;
use Modules\Pipeline\ValueObjects\Finding;
use Modules\Pipeline\ValueObjects\StepContext;
use Modules\Pipeline\ValueObjects\StepResult;

final class DemoExtractStep implements PipelineStep
{
    public static function key(): string
    {
        return 'demo_extract';
    }

    public static function queue(): string
    {
        return (string) config('pipeline.queues.ai');
    }

    public function handle(StepContext $context): StepResult
    {
        return StepResult::success()
            ->artifact('demo_extracted', [
                'merchant' => 'ALDI',
                'total_amount' => 4210,
                'items' => [
                    ['description' => 'Tej 2.8%', 'quantity' => 2, 'unit_price' => 389],
                    ['description' => 'Kenyer', 'quantity' => 1, 'unit_price' => 549],
                ],
            ])
            ->textArtifact('demo_raw_response', "{\n  \"merchant\": \"ALDI\"\n}")
            ->confidence(0.82)
            ->finding(Finding::warning('low_ocr_confidence', context: ['pages' => [1]]))
            ->cost(inputTokens: 4210, outputTokens: 380, usdMicros: 12400);
    }
}
