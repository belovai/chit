<?php

declare(strict_types=1);

namespace Modules\Auth\Controllers;

use App\Traits\ApiResponses;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Auth\Actions\LogoutUser;
use Modules\User\Models\User;

final class LogoutController
{
    use ApiResponses;

    public function __invoke(
        Request $request,
        LogoutUser $logoutUser,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        $logoutUser->handle($user);

        return $this->ok();
    }
}
