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
final class PipelineRunDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function toArray(Request $request): array
    {
        return [
            ...(new PipelineRunResource($this->resource))->toArray($request),
            'error_summary' => $this->error_summary,
            'retried_from_hash_id' => $this->retried_from_run_id === null
                ? null
                : PipelineRun::query()->whereKey($this->retried_from_run_id)->value('hash_id'),
            'steps' => $this->currentSteps()
                ->sortBy(['stage_position', 'position'])
                ->values()
                ->map(fn (PipelineRunStep $step): array => (new PipelineRunStepResource($step, detailed: true))->toArray($request))
                ->all(),
        ];
    }
}
