<?php

declare(strict_types=1);

namespace Modules\Pipeline\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Pipeline\Enums\RunStatus;
use Modules\Pipeline\Enums\TriggerSource;
use Modules\Pipeline\Models\PipelineRun;
use Modules\User\Models\User;

/**
 * @extends Factory<PipelineRun>
 */
final class PipelineRunFactory extends Factory
{
    protected $model = PipelineRun::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'owner_id' => User::factory(),
            'definition_key' => 'fake_linear',
            'definition_version' => 1,
            'stages' => ['alpha', 'beta', 'gamma'],
            'status' => RunStatus::Queued,
            'trigger_source' => TriggerSource::ManualUpload,
            'queued_at' => now(),
            'cost_usd_micros' => 0,
        ];
    }
}
