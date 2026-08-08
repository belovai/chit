<?php

declare(strict_types=1);

namespace Modules\Ai\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Ai\Models\AiUsageLog;
use Modules\User\Models\User;

/**
 * @extends Factory<AiUsageLog>
 */
final class AiUsageLogFactory extends Factory
{
    protected $model = AiUsageLog::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'owner_id' => User::factory(),
            'provider' => 'fake',
            'model' => 'fake-model',
            'purpose' => 'extraction.classify',
            'input_tokens' => 1_000,
            'output_tokens' => 200,
            'cached_input_tokens' => 0,
            'cost_usd_micros' => 10_000,
        ];
    }
}
