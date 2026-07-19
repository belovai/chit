<?php

declare(strict_types=1);

namespace Modules\Auth\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Auth\ValueObjects\AccessToken;
use Modules\User\Resources\UserResource;

/**
 * @mixin AccessToken
 */
final class AccessTokenResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function toArray(Request $request): array
    {
        return [
            'token' => $this->plainText,
            'user' => UserResource::make($this->user),
        ];
    }
}
