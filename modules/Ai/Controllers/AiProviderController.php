<?php

declare(strict_types=1);

namespace Modules\Ai\Controllers;

use App\Traits\ApiResponses;
use Illuminate\Http\JsonResponse;
use Modules\Ai\Registries\ProviderRegistry;
use Modules\Ai\Resources\AiProviderResource;

/**
 * The catalogue that drives the client's provider/model form. Identical for
 * every user, but authenticated — it is not public information.
 */
final class AiProviderController
{
    use ApiResponses;

    public function index(ProviderRegistry $providers): JsonResponse
    {
        return $this->ok(
            data: AiProviderResource::collection($providers->all())->resolve(),
        );
    }
}
