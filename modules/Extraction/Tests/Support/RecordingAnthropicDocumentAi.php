<?php

declare(strict_types=1);

namespace Modules\Extraction\Tests\Support;

use Modules\Extraction\Ai\Anthropic\AnthropicDocumentAi;
use Modules\Extraction\Ai\ValueObjects\AiUsage;
use Modules\Extraction\Exceptions\AiException;

/** Captures what the provider would have sent, and returns a canned payload. */
final class RecordingAnthropicDocumentAi extends AnthropicDocumentAi
{
    /** @var array<string, mixed>|null */
    public ?array $lastRequest = null;

    /** @var array<string, mixed> */
    public array $nextPayload = [];

    public ?AiException $nextFailure = null;

    /**
     * @param  string|list<array<string, mixed>>  $user
     * @param  array<string, mixed>  $schema
     * @return array{payload: array<string, mixed>, usage: AiUsage}
     */
    protected function callModel(string $system, string|array $user, array $schema): array
    {
        $this->lastRequest = ['system' => $system, 'user' => $user, 'schema' => $schema];

        if ($this->nextFailure !== null) {
            throw $this->nextFailure;
        }

        return ['payload' => $this->nextPayload, 'usage' => new AiUsage(4000, 400, 0, 30000)];
    }
}
