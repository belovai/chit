<?php

declare(strict_types=1);

namespace Modules\Pipeline\Controllers;

use App\Traits\ApiResponses;
use Illuminate\Http\JsonResponse;
use Modules\Pipeline\Actions\RetryRun;
use Modules\Pipeline\Enums\RetryMode;
use Modules\Pipeline\Models\PipelineRun;
use Modules\Pipeline\Requests\RetryPipelineRunRequest;
use Modules\Pipeline\Resources\PipelineRunDetailResource;

final class RetryPipelineRunController
{
    use ApiResponses;

    public function __invoke(
        PipelineRun $pipelineRun,
        RetryPipelineRunRequest $request,
        RetryRun $retryRun,
    ): JsonResponse {
        /** @var array{mode: string, step_key?: string} $validated */
        $validated = $request->validated();

        $result = $retryRun->handle(
            run: $pipelineRun,
            mode: RetryMode::from($validated['mode']),
            stepKey: $validated['step_key'] ?? null,
        );

        $result->load(['steps.artifacts']);

        return $this->ok(data: PipelineRunDetailResource::make($result));
    }
}
