<?php

declare(strict_types=1);

namespace Modules\Product\Controllers;

use App\Traits\ApiResponses;
use Illuminate\Http\JsonResponse;
use Modules\Product\Actions\SuggestProductCandidates;
use Modules\Product\Requests\SuggestProductCandidatesRequest;
use Modules\Product\Resources\ProductMatchResource;
use Modules\User\Models\User;

final class SuggestProductCandidatesController
{
    use ApiResponses;

    public function __invoke(
        SuggestProductCandidatesRequest $request,
        SuggestProductCandidates $suggestProductCandidates,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        $candidates = $suggestProductCandidates->handle(
            ownerId: $user->id,
            rawName: (string) $request->validated('query'),
        );

        return $this->ok(
            data: ProductMatchResource::collection($candidates),
        );
    }
}
