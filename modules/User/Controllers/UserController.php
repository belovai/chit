<?php

declare(strict_types=1);

namespace Modules\User\Controllers;

use App\Traits\ApiResponses;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Modules\User\Actions\CreateUser;
use Modules\User\Actions\DestroyUser;
use Modules\User\Actions\UpdateUser;
use Modules\User\Models\User;
use Modules\User\Requests\CreateUserRequest;
use Modules\User\Requests\UpdateUserRequest;
use Modules\User\Resources\UserResource;

final class UserController
{
    use ApiResponses;

    public function index(): JsonResponse
    {
        Gate::authorize('user.list');

        return $this->ok(
            data: UserResource::collection(User::query()->paginate()),
        );
    }

    public function show(User $user): JsonResponse
    {
        Gate::authorize('user.view', $user);

        return $this->ok(
            data: UserResource::make($user),
        );
    }

    public function store(
        CreateUserRequest $request,
        CreateUser $createUser,
    ): JsonResponse {
        Gate::authorize('user.create');

        $user = $createUser->handle($request->validated());

        return $this->created(
            data: UserResource::make($user),
        );
    }

    /**
     * @throws \Throwable
     */
    public function destroy(
        User $user,
        DestroyUser $destroyUser,
    ): JsonResponse {
        Gate::authorize('user.delete', $user);

        $destroyUser->handle($user);

        return $this->ok();
    }

    public function update(
        User $user,
        UpdateUserRequest $request,
        UpdateUser $updateUser,
    ): JsonResponse {
        Gate::authorize('user.update', $user);

        $user = $updateUser->handle($user, $request->validated());

        return $this->ok(
            data: UserResource::make($user),
        );
    }
}
