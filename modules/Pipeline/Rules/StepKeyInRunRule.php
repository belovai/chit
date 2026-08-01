<?php

declare(strict_types=1);

namespace Modules\Pipeline\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Modules\Pipeline\Models\PipelineRun;

final readonly class StepKeyInRunRule implements ValidationRule
{
    public function __construct(private PipelineRun $run) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $exists = $this->run->steps()
            ->where('step_key', $value)
            ->exists();

        if (!$exists) {
            $fail('pipeline.step_not_in_run');
        }
    }
}
