<?php

declare(strict_types=1);

namespace Modules\Pipeline\Tests\Support\Steps;

use Modules\Pipeline\Contracts\PipelineStep;
use Modules\Pipeline\ValueObjects\StepContext;
use Modules\Pipeline\ValueObjects\StepDefinition;
use Modules\Pipeline\ValueObjects\StepResult;

/**
 * Expands the run with FakeExpandedStep. `target_stage` config (default `beta`)
 * lets tests point the expansion at a non-existent stage to assert validation.
 */
final class FakeExpandingStep implements PipelineStep
{
    public static function key(): string
    {
        return 'fake_expanding';
    }

    public static function queue(): string
    {
        return 'sync';
    }

    public function handle(StepContext $context): StepResult
    {
        /** @var string $stage */
        $stage = $context->config('target_stage', 'beta');

        /** @var list<string> $dependsOn */
        $dependsOn = $context->config('expanded_depends_on', ['fake_expanding']);

        return StepResult::success()
            ->artifact('fake_expanding_output', ['ok' => true])
            ->expand([
                StepDefinition::make(FakeExpandedStep::class)
                    ->inStage($stage)
                    ->dependsOn(...$dependsOn),
            ]);
    }
}
