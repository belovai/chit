<?php

declare(strict_types=1);

namespace Modules\Merchant\Controllers;

use App\Traits\ApiResponses;
use Illuminate\Http\JsonResponse;
use Modules\Merchant\Actions\CreateMerchantLocation;
use Modules\Merchant\Actions\DestroyMerchantLocation;
use Modules\Merchant\Actions\UpdateMerchantLocation;
use Modules\Merchant\Models\Merchant;
use Modules\Merchant\Models\MerchantLocation;
use Modules\Merchant\Requests\CreateMerchantLocationRequest;
use Modules\Merchant\Requests\UpdateMerchantLocationRequest;
use Modules\Merchant\Resources\MerchantLocationResource;

final class MerchantLocationController
{
    use ApiResponses;

    public function index(Merchant $merchant): JsonResponse
    {
        return $this->ok(
            data: MerchantLocationResource::collection($merchant->locations),
        );
    }

    public function store(
        Merchant $merchant,
        CreateMerchantLocationRequest $request,
        CreateMerchantLocation $createMerchantLocation,
    ): JsonResponse {
        /** @var array{is_online: bool, address: string|null, latitude: float|null, longitude: float|null} $validated */
        $validated = $request->validated();

        $location = $createMerchantLocation->handle(
            merchant: $merchant,
            validated: $validated,
        );

        return $this->created(
            data: MerchantLocationResource::make($location),
        );
    }

    public function update(
        MerchantLocation $merchantLocation,
        UpdateMerchantLocationRequest $request,
        UpdateMerchantLocation $updateMerchantLocation,
    ): JsonResponse {
        /** @var array{is_online: bool, address: string|null, latitude: float|null, longitude: float|null} $validated */
        $validated = $request->validated();

        $merchantLocation = $updateMerchantLocation->handle($merchantLocation, $validated);

        return $this->ok(
            data: MerchantLocationResource::make($merchantLocation),
        );
    }

    public function destroy(
        MerchantLocation $merchantLocation,
        DestroyMerchantLocation $destroyMerchantLocation,
    ): JsonResponse {
        $destroyMerchantLocation->handle($merchantLocation);

        return $this->ok();
    }
}
