<?php

declare(strict_types=1);

namespace Modules\Ai\Contracts;

use Modules\Ai\Exceptions\AiException;
use Modules\Ai\ValueObjects\AiRequest;
use Modules\Ai\ValueObjects\AiResponse;

interface AiClient
{
    /**
     * @throws AiException
     */
    public function complete(AiRequest $request): AiResponse;
}
