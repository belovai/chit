<?php

declare(strict_types=1);

namespace Modules\Pipeline\Controllers;

use App\Traits\ApiResponses;
use Illuminate\Http\JsonResponse;
use Modules\Pipeline\Actions\ListPipelineRuns;
use Modules\Pipeline\Models\PipelineRun;
use Modules\Pipeline\Requests\IndexPipelineRunsRequest;
use Modules\Pipeline\Resources\PipelineRunDetailResource;
use Modules\Pipeline\Resources\PipelineRunResource;
use Modules\User\Models\User;

final class PipelineRunController
{
    use ApiResponses;

    public function index(IndexPipelineRunsRequest $request, ListPipelineRuns $listRuns): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        return $this->ok(
            data: PipelineRunResource::collection(
                $listRuns->handle(
                    ownerId: $user->id,
                    filters: $validated,
                    perPage: (int) ($validated['per_page'] ?? 20),
                ),
            ),
        );
    }

    public function show(PipelineRun $pipelineRun): JsonResponse
    {
        $pipelineRun->load(['steps.artifacts']);

        return $this->ok(
            data: PipelineRunDetailResource::make($pipelineRun),
        );
    }
}
