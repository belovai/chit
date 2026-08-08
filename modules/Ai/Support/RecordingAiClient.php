<?php

declare(strict_types=1);

namespace Modules\Ai\Support;

use Modules\Ai\Contracts\AiClient;
use Modules\Ai\Exceptions\AiException;
use Modules\Ai\Services\CredentialHealth;
use Modules\Ai\Services\UsageRecorder;
use Modules\Ai\ValueObjects\AiConnection;
use Modules\Ai\ValueObjects\AiRequest;
use Modules\Ai\ValueObjects\AiResponse;
use Modules\Ai\ValueObjects\UsageContext;

/**
 * Usage logging and health bookkeeping wrap every vendor client, so a new
 * adapter inherits both without writing a line of either.
 */
final class RecordingAiClient implements AiClient
{
    public function __construct(
        private readonly AiClient $inner,
        private readonly AiConnection $connection,
        private readonly UsageContext $context,
        private readonly UsageRecorder $usage,
        private readonly CredentialHealth $health,
    ) {}

    public function complete(AiRequest $request): AiResponse
    {
        try {
            $response = $this->inner->complete($request);
        } catch (AiException $exception) {
            if ($this->connection->credentialId !== null) {
                $this->health->failed(
                    $this->connection->credentialId,
                    $exception->getMessage(),
                    $exception->isAuthFailure(),
                );
            }

            throw $exception;
        }

        $this->usage->record($this->connection, $response, $this->context);

        if ($this->connection->credentialId !== null) {
            $this->health->succeeded($this->connection->credentialId);
        }

        return $response;
    }
}
