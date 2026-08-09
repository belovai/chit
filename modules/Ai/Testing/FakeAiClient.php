<?php

declare(strict_types=1);

namespace Modules\Ai\Testing;

use Modules\Ai\Contracts\AiClient;
use Modules\Ai\ValueObjects\AiConnection;
use Modules\Ai\ValueObjects\AiRequest;
use Modules\Ai\ValueObjects\AiResponse;

final readonly class FakeAiClient implements AiClient
{
    public function __construct(private AiConnection $connection) {}

    /**
     * @throws \JsonException
     */
    #[\Override]
    public function complete(AiRequest $request): AiResponse
    {
        FakeAiProvider::record($request, $this->connection);

        return FakeAiProvider::nextResponse();
    }
}
