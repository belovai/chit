<?php

declare(strict_types=1);

namespace Modules\Ai\Services;

use Modules\Ai\Models\AiUsageLog;
use Modules\Ai\ValueObjects\AiConnection;
use Modules\Ai\ValueObjects\AiResponse;
use Modules\Ai\ValueObjects\UsageContext;

final class UsageRecorder
{
    public function record(AiConnection $connection, AiResponse $response, UsageContext $context): void
    {
        if ($connection->userId === null) {
            return;
        }

        AiUsageLog::query()->create([
            'owner_id' => $connection->userId,
            'ai_credential_id' => $connection->credentialId,
            'provider' => $connection->provider,
            'model' => $connection->model,
            'purpose' => $context->purpose,
            'subject_type' => $context->subjectType,
            'subject_id' => $context->subjectId,
            'input_tokens' => $response->usage->inputTokens,
            'output_tokens' => $response->usage->outputTokens,
            'cached_input_tokens' => $response->usage->cachedInputTokens,
            'cost_usd_micros' => $response->usage->costUsdMicros,
        ]);
    }
}
