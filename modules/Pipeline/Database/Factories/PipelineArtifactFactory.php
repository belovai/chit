<?php

declare(strict_types=1);

namespace Modules\Pipeline\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Pipeline\Enums\ArtifactKind;
use Modules\Pipeline\Models\PipelineArtifact;
use Modules\Pipeline\Models\PipelineRun;
use Modules\Pipeline\Models\PipelineRunStep;

/**
 * @extends Factory<PipelineArtifact>
 */
final class PipelineArtifactFactory extends Factory
{
    protected $model = PipelineArtifact::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'run_id' => PipelineRun::factory(),
            'step_id' => PipelineRunStep::factory(),
            'key' => fake()->unique()->slug(2, false),
            'kind' => ArtifactKind::Json,
            'payload' => ['value' => fake()->word()],
        ];
    }
}
