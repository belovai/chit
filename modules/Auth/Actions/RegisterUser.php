<?php

declare(strict_types=1);

namespace Modules\Auth\Actions;

use Modules\Auth\ValueObjects\AccessToken;
use Modules\User\Actions\CreateUser;

final readonly class RegisterUser
{
    public function __construct(
        private CreateUser $createUser,
    ) {}

    /**
     * @param  array<string, mixed> $data
     */
    public function handle(array $data): AccessToken
    {
        $user = $this->createUser->handle($data);

        return new AccessToken(
            plainText: $user->createToken('api')->plainTextToken,
            user: $user,
        );
    }
}
