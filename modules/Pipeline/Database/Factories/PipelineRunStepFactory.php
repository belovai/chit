<?php

declare(strict_types=1);

namespace Modules\Pipeline\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Pipeline\Enums\StepStatus;
use Modules\Pipeline\Models\PipelineRun;
use Modules\Pipeline\Models\PipelineRunStep;

/**
 * @extends Factory<PipelineRunStep>
 */
final class PipelineRunStepFactory extends Factory
{
    protected $model = PipelineRunStep::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'run_id' => PipelineRun::factory(),
            'step_key' => fake()->unique()->slug(2, false),
            'stage' => 'alpha',
            'stage_position' => 0,
            'position' => 0,
            'attempt' => 1,
            'max_attempts' => 1,
            'status' => StepStatus::Pending,
            'depends_on' => [],
            'allow_failure' => false,
            'is_gate' => false,
        ];
    }
}
