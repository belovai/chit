<?php

declare(strict_types=1);

namespace Modules\Auth\Controllers;

use App\Traits\ApiResponses;
use Illuminate\Http\JsonResponse;
use Modules\Auth\Actions\IsRegistrationEnabled;

final class RegistrationStatusController
{
    use ApiResponses;

    public function __invoke(IsRegistrationEnabled $isRegistrationEnabled): JsonResponse
    {
        return $this->ok(data: [
            'enabled' => $isRegistrationEnabled->handle(),
        ]);
    }
}
