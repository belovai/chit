<?php

declare(strict_types=1);

namespace Modules\Pipeline\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Pipeline\Models\PipelineRun;
use Modules\Pipeline\Models\PipelineRunStep;

/**
 * @mixin PipelineRun
 */
final class PipelineRunResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function toArray(Request $request): array
    {
        return [
            'hash_id' => $this->hash_id,
            'definition_key' => $this->definition_key,
            'definition_version' => $this->definition_version,
            'stages' => $this->stages,
            'status' => $this->status->value,
            'trigger_source' => $this->trigger_source->value,
            'subject_type' => $this->subject_type,
            'subject_id' => $this->subject_id,
            'duration_ms' => $this->duration_ms,
            'cost_usd_micros' => $this->cost_usd_micros,
            'created_at' => $this->created_at?->toIso8601String(),
            'started_at' => $this->started_at?->toIso8601String(),
            'finished_at' => $this->finished_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'steps' => $this->currentSteps()
                ->sortBy(['stage_position', 'position'])
                ->values()
                ->map(fn (PipelineRunStep $step): array => (new PipelineRunStepResource($step))->toArray($request))
                ->all(),
        ];
    }
}
