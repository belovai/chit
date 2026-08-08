<?php

declare(strict_types=1);

namespace Modules\Extraction\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Extraction\Ai\Contracts\DocumentClassifier;
use Modules\Extraction\Ai\Contracts\DocumentExtractor;
use Modules\Extraction\Ai\DocumentAi;
use Modules\Extraction\Ai\Testing\FakeDocumentAi;
use Modules\Extraction\Ocr\Contracts\OcrEngine;
use Modules\Extraction\Ocr\TesseractOcrEngine;
use Modules\Extraction\Ocr\Testing\FakeOcrEngine;

final class ExtractionModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../Config/extraction.php', 'extraction');

        $this->app->bind(OcrEngine::class, function (): OcrEngine {
            return match ((string) config('extraction.ocr.engine')) {
                'fake' => new FakeOcrEngine,
                default => new TesseractOcrEngine,
            };
        });

        foreach ([DocumentClassifier::class, DocumentExtractor::class] as $contract) {
            $this->app->bind($contract, function (): object {
                return (bool) config('extraction.ai.fake_documents')
                    ? new FakeDocumentAi
                    : $this->app->make(DocumentAi::class);
            });
        }
    }
}
