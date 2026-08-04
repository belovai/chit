<?php

declare(strict_types=1);

namespace Modules\Receipt\Pipelines;

use Modules\Pipeline\Definitions\PipelineDefinition;
use Modules\Pipeline\ValueObjects\StepDefinition;
use Modules\Receipt\Steps\ClassifyDocumentStep;
use Modules\Receipt\Steps\CreateTransactionStep;
use Modules\Receipt\Steps\DedupeFileHashStep;
use Modules\Receipt\Steps\OcrStep;
use Modules\Receipt\Steps\PreprocessImageStep;
use Modules\Receipt\Steps\ReviewGateStep;
use Modules\Receipt\Steps\StoreFileStep;

final class ReceiptIngestPipeline extends PipelineDefinition
{
    public function key(): string
    {
        return 'receipt_ingest';
    }

    public function version(): int
    {
        return 1;
    }

    /**
     * Every stage the run may ever hold, including the three that start empty.
     * Declaring them up front means the UI shows the whole shape from the first
     * render, and the gate's implicit stage ordering holds without naming the
     * steps the classifier will inject.
     */
    public function stages(): array
    {
        return ['ingest', 'prepare', 'read', 'classify', 'extract', 'resolve', 'validate', 'review', 'commit'];
        //        return ['ingest', 'prepare', 'read', 'classify', 'extract', 'resolve', 'validate', 'review', 'commit'];
    }

    public function steps(): array
    {
        return [
            StepDefinition::make(StoreFileStep::class)->inStage('ingest'),
            StepDefinition::make(DedupeFileHashStep::class)->inStage('ingest')->dependsOn('store_file'),
            StepDefinition::make(PreprocessImageStep::class)->inStage('prepare')->dependsOn('store_file'),
            StepDefinition::make(OcrStep::class)->inStage('read')->dependsOn('preprocess_image'),
            StepDefinition::make(ClassifyDocumentStep::class)->inStage('classify')
                ->dependsOn('ocr')->maxAttempts(3),
            //            // extract / resolve / validate are filled by classify_document.
            StepDefinition::make(ReviewGateStep::class)->inStage('review')->asGate(),
            StepDefinition::make(CreateTransactionStep::class)->inStage('commit')->dependsOn('review_gate'),
        ];
    }
}
