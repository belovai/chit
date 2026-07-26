<?php

declare(strict_types=1);

namespace Modules\Merchant\Controllers;

use App\Traits\ApiResponses;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Merchant\Actions\CreateMerchant;
use Modules\Merchant\Actions\DestroyMerchant;
use Modules\Merchant\Actions\UpdateMerchant;
use Modules\Merchant\Models\Merchant;
use Modules\Merchant\Requests\CreateMerchantRequest;
use Modules\Merchant\Requests\UpdateMerchantRequest;
use Modules\Merchant\Resources\MerchantResource;
use Modules\User\Models\User;

final class MerchantController
{
    use ApiResponses;

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return $this->ok(
            data: MerchantResource::collection(
                Merchant::query()
                    ->where('owner_id', $user->id)
                    ->withCount('locations')
                    ->paginate(),
            ),
        );
    }

    public function show(Merchant $merchant): JsonResponse
    {
        return $this->ok(
            data: MerchantResource::make($merchant),
        );
    }

    public function store(
        CreateMerchantRequest $request,
        CreateMerchant $createMerchant,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        /** @var array{name: string} $validated */
        $validated = $request->validated();

        $merchant = $createMerchant->handle(
            ownerId: $user->id,
            validated: $validated,
        );

        return $this->created(
            data: MerchantResource::make($merchant),
        );
    }

    public function update(
        Merchant $merchant,
        UpdateMerchantRequest $request,
        UpdateMerchant $updateMerchant,
    ): JsonResponse {
        /** @var array{name: string} $validated */
        $validated = $request->validated();

        $merchant = $updateMerchant->handle($merchant, $validated);

        return $this->ok(
            data: MerchantResource::make($merchant),
        );
    }

    public function destroy(
        Merchant $merchant,
        DestroyMerchant $destroyMerchant,
    ): JsonResponse {
        $destroyMerchant->handle($merchant);

        return $this->ok();
    }
}
