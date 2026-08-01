<?php

declare(strict_types=1);

namespace Modules\Pipeline\Controllers;

use App\Traits\ApiResponses;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Pipeline\Models\PipelineRun;
use Modules\Pipeline\Models\PipelineRunStep;
use Modules\Pipeline\Resources\PipelineRunStepResource;

final class PipelineRunStepAttemptsController
{
    use ApiResponses;

    public function __invoke(Request $request, PipelineRun $pipelineRun, string $stepKey): JsonResponse
    {
        $attempts = $pipelineRun->steps()
            ->where('step_key', $stepKey)
            ->with('artifacts')
            ->reorder()
            ->orderBy('attempt')
            ->get();

        return $this->ok(
            data: $attempts
                ->map(fn (PipelineRunStep $step): array => (new PipelineRunStepResource($step, detailed: true))->toArray($request))
                ->all(),
        );
    }
}
