<?php

declare(strict_types=1);

namespace Modules\Pipeline\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Pipeline\Models\PipelineArtifact;
use Modules\Pipeline\Models\PipelineRunStep;

/**
 * @mixin PipelineRunStep
 */
final class PipelineRunStepResource extends JsonResource
{
    public function __construct(PipelineRunStep $resource, private readonly bool $detailed = false)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function toArray(Request $request): array
    {
        $compact = [
            'step_key' => $this->step_key,
            'stage' => $this->stage,
            'stage_position' => $this->stage_position,
            'position' => $this->position,
            'status' => $this->status->value,
            'is_gate' => $this->is_gate,
            'is_dynamic' => $this->added_by_step_id !== null,
        ];

        if (!$this->detailed) {
            return $compact;
        }

        return [
            ...$compact,
            'attempt' => $this->attempt,
            'max_attempts' => $this->max_attempts,
            'allow_failure' => $this->allow_failure,
            'depends_on' => $this->depends_on ?? [],
            'started_at' => $this->started_at?->toIso8601String(),
            'finished_at' => $this->finished_at?->toIso8601String(),
            'duration_ms' => $this->duration_ms,
            'confidence' => $this->confidence,
            'findings' => $this->findings ?? [],
            'input_tokens' => $this->input_tokens,
            'output_tokens' => $this->output_tokens,
            'cost_usd_micros' => $this->cost_usd_micros,
            'error' => $this->error,
            'artifacts' => PipelineArtifactSummaryResource::collection(
                $this->artifacts->filter(
                    fn (PipelineArtifact $artifact): bool => $artifact->superseded_at === null,
                )->values(),
            ),
        ];
    }
}
