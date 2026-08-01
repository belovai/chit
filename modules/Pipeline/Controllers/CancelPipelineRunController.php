<?php

declare(strict_types=1);

namespace Modules\Pipeline\Controllers;

use App\Traits\ApiResponses;
use Illuminate\Http\JsonResponse;
use Modules\Pipeline\Actions\CancelRun;
use Modules\Pipeline\Models\PipelineRun;
use Modules\Pipeline\Resources\PipelineRunDetailResource;

final class CancelPipelineRunController
{
    use ApiResponses;

    public function __invoke(PipelineRun $pipelineRun, CancelRun $cancelRun): JsonResponse
    {
        $run = $cancelRun->handle($pipelineRun);
        $run->load(['steps.artifacts']);

        return $this->ok(data: PipelineRunDetailResource::make($run));
    }
}
