<?php

declare(strict_types=1);

namespace Modules\Merchant\Controllers;

use App\Traits\ApiResponses;
use Illuminate\Http\JsonResponse;
use Modules\Merchant\Actions\SuggestMerchantCandidates;
use Modules\Merchant\Requests\SuggestMerchantCandidatesRequest;
use Modules\Merchant\Resources\MerchantMatchResource;
use Modules\User\Models\User;

final class SuggestMerchantCandidatesController
{
    use ApiResponses;

    public function __invoke(
        SuggestMerchantCandidatesRequest $request,
        SuggestMerchantCandidates $suggestMerchantCandidates,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        $candidates = $suggestMerchantCandidates->handle(
            ownerId: $user->id,
            rawName: (string) $request->validated('query'),
        );

        return $this->ok(
            data: MerchantMatchResource::collection($candidates),
        );
    }
}
