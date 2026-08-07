<?php

declare(strict_types=1);

namespace Modules\User\Controllers;

use App\Traits\ApiResponses;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\User\Actions\ChangeAccountPassword;
use Modules\User\Actions\UpdateAccount;
use Modules\User\Models\User;
use Modules\User\Requests\ChangeAccountPasswordRequest;
use Modules\User\Requests\UpdateAccountRequest;
use Modules\User\Resources\UserResource;

/**
 * A bejelentkezett felhasználó saját fiókja. Nem keverhető a UserController
 * admin CRUD-jával: itt nincs id a route-ban, mindig a tokent birtokló user.
 */
final class AccountController
{
    use ApiResponses;

    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return $this->ok(
            data: UserResource::make($user),
        );
    }

    public function update(
        UpdateAccountRequest $request,
        UpdateAccount $updateAccount,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        return $this->ok(
            data: UserResource::make($updateAccount->handle($user, $request->validated())),
        );
    }

    public function updatePassword(
        ChangeAccountPasswordRequest $request,
        ChangeAccountPassword $changeAccountPassword,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        $changeAccountPassword->handle(
            user: $user,
            password: $request->string('password')->toString(),
        );

        return $this->ok();
    }
}
