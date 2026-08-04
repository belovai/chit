<?php

declare(strict_types=1);

namespace Modules\Extraction\Providers;

use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use Modules\Extraction\Ai\Anthropic\AnthropicDocumentAi;
use Modules\Extraction\Ai\Contracts\DocumentClassifier;
use Modules\Extraction\Ai\Contracts\DocumentExtractor;
use Modules\Extraction\Ai\Support\CostCalculator;
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

        $this->app->singleton(CostCalculator::class);

        foreach ([DocumentClassifier::class, DocumentExtractor::class] as $contract) {
            $this->app->bind($contract, function (): object {
                return match ((string) config('extraction.ai.provider')) {
                    'fake' => new FakeDocumentAi,
                    'anthropic' => $this->app->make(AnthropicDocumentAi::class),
                    default => throw new InvalidArgumentException(
                        'Unknown extraction AI provider ['.config('extraction.ai.provider').'].',
                    ),
                };
            });
        }
    }
}
