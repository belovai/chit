<?php

declare(strict_types=1);

namespace Modules\User\Controllers;

use App\Traits\ApiResponses;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\User\Actions\ChangeAccountPassword;
use Modules\User\Actions\DeleteAccount;
use Modules\User\Actions\UpdateAccount;
use Modules\User\Models\User;
use Modules\User\Requests\ChangeAccountPasswordRequest;
use Modules\User\Requests\DeleteAccountRequest;
use Modules\User\Requests\UpdateAccountRequest;
use Modules\User\Resources\UserResource;

/**
 * The logged-in user's own account. Not to be confused with UserController's
 * admin CRUD: there's no id in the route here, always the token-owning user.
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

    /**
     * @throws \Throwable
     */
    public function destroy(
        DeleteAccountRequest $request,
        DeleteAccount $deleteAccount,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        $deleteAccount->handle($user);

        return $this->ok();
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
