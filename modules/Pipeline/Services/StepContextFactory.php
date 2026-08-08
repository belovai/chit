<?php

declare(strict_types=1);

namespace Modules\Pipeline\Services;

use Modules\Pipeline\Models\PipelineRun;
use Modules\Pipeline\Models\PipelineRunStep;
use Modules\Pipeline\ValueObjects\StepContext;

final class StepContextFactory
{
    public function __construct(private readonly ArtifactWriter $artifacts) {}

    public function for(PipelineRunStep $step): StepContext
    {
        /** @var PipelineRun $run */
        $run = $step->run;

        return new StepContext(
            artifacts: $this->artifacts->liveFor($run),
            config: $step->config ?? [],
            ownerId: $run->owner_id,
            aiCredentialId: $run->ai_credential_id,
            subject: $run->subject,
            attempt: $step->attempt,
        );
    }
}
