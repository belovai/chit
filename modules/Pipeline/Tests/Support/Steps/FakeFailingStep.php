<?php

declare(strict_types=1);

namespace Modules\Pipeline\Tests\Support\Steps;

use Modules\Pipeline\Contracts\PipelineStep;
use Modules\Pipeline\Exceptions\StepException;
use Modules\Pipeline\ValueObjects\StepContext;
use Modules\Pipeline\ValueObjects\StepResult;

/**
 * Fails on every attempt unless `succeed_from_attempt` config says otherwise.
 * `retryable` config (default false) picks which StepException flavour is thrown.
 */
final class FakeFailingStep implements PipelineStep
{
    public static function key(): string
    {
        return 'fake_failing';
    }

    public static function queue(): string
    {
        return 'sync';
    }

    public function handle(StepContext $context): StepResult
    {
        $succeedFrom = $context->config('succeed_from_attempt');

        if (is_int($succeedFrom) && $context->attempt() >= $succeedFrom) {
            return StepResult::success()->artifact('fake_failing_output', ['attempt' => $context->attempt()]);
        }

        return StepResult::failure(
            $context->config('retryable', false) === true
                ? StepException::retryable('fake transient failure')
                : StepException::permanent('fake permanent failure'),
        );
    }
}
