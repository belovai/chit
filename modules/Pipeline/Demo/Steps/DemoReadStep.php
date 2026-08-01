<?php

declare(strict_types=1);

namespace Modules\Pipeline\Demo\Steps;

use Modules\Pipeline\Contracts\PipelineStep;
use Modules\Pipeline\Exceptions\StepException;
use Modules\Pipeline\ValueObjects\StepContext;
use Modules\Pipeline\ValueObjects\StepResult;

final class DemoReadStep implements PipelineStep
{
    public static function key(): string
    {
        return 'demo_read';
    }

    public static function queue(): string
    {
        return (string) config('pipeline.queues.cpu');
    }

    public function handle(StepContext $context): StepResult
    {
        return StepResult::failure(StepException::permanent('demo: OCR engine unavailable'));
    }
}
