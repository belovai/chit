<?php

declare(strict_types=1);

namespace Modules\Ai\Controllers;

use App\Traits\ApiResponses;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Modules\Ai\Actions\SummariseAiUsage;
use Modules\Ai\Requests\AiUsageRequest;

final class AiUsageController
{
    use ApiResponses;

    public function index(AiUsageRequest $request, SummariseAiUsage $summarise): JsonResponse
    {
        $from = $request->filled('from')
            ? Carbon::parse($request->string('from')->toString())->startOfDay()
            : now()->startOfMonth();

        $to = $request->filled('to')
            ? Carbon::parse($request->string('to')->toString())->endOfDay()
            : now()->endOfDay();

        return $this->ok(
            data: $summarise->handle((int) $request->user()?->getAuthIdentifier(), $from, $to),
        );
    }
}
