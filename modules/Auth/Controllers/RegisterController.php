<?php

declare(strict_types=1);

namespace Modules\Auth\Controllers;

use App\Traits\ApiResponses;
use Illuminate\Http\JsonResponse;
use Modules\Auth\Actions\RegisterUser;
use Modules\Auth\Requests\RegisterRequest;
use Modules\Auth\Resources\AccessTokenResource;

final class RegisterController
{
    use ApiResponses;

    public function __invoke(
        RegisterRequest $request,
        RegisterUser $registerUser,
    ): JsonResponse {
        $token = $registerUser->handle($request->validated());

        return $this->created(
            data: AccessTokenResource::make($token),
        );
    }
}
