<?php

declare(strict_types=1);

namespace Modules\Auth\Controllers;

use App\Traits\ApiResponses;
use Illuminate\Http\JsonResponse;
use Modules\Auth\Actions\AuthenticateUser;
use Modules\Auth\Requests\LoginRequest;
use Modules\Auth\Resources\AccessTokenResource;

final class LoginController
{
    use ApiResponses;

    public function __invoke(
        LoginRequest $request,
        AuthenticateUser $authenticateUser,
    ): JsonResponse {
        $token = $authenticateUser->handle(
            email: $request->string('email')->toString(),
            password: $request->string('password')->toString(),
        );

        return $this->ok(
            data: AccessTokenResource::make($token),
        );
    }
}
