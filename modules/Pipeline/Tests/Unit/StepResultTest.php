<?php

declare(strict_types=1);

namespace Modules\Pipeline\Tests\Unit;

use Modules\Pipeline\Enums\ArtifactKind;
use Modules\Pipeline\Enums\FindingSeverity;
use Modules\Pipeline\Enums\StepOutcome;
use Modules\Pipeline\Exceptions\StepException;
use Modules\Pipeline\ValueObjects\Finding;
use Modules\Pipeline\ValueObjects\StepResult;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class StepResultTest extends TestCase
{
    #[Test]
    public function success_collects_artifacts_findings_and_cost(): void
    {
        $result = StepResult::success()
            ->artifact('doc_type', ['value' => 'receipt'])
            ->textArtifact('ocr_text', 'ALDI 1234')
            ->binaryArtifact('normalized_image', 'local', 'runs/1/norm.png', 'image/png', 2048, 'abc')
            ->confidence(0.87)
            ->finding(Finding::warning('low_ocr_confidence', context: ['pages' => [2]]))
            ->cost(inputTokens: 4210, outputTokens: 380, usdMicros: 12400);

        $this->assertSame(StepOutcome::Success, $result->outcome());
        $this->assertCount(3, $result->artifacts());
        $this->assertSame('doc_type', $result->artifacts()[0]->key);
        $this->assertSame(ArtifactKind::Json, $result->artifacts()[0]->kind);
        $this->assertSame(ArtifactKind::Text, $result->artifacts()[1]->kind);
        $this->assertSame(['text' => 'ALDI 1234'], $result->artifacts()[1]->payload);
        $this->assertSame(ArtifactKind::Binary, $result->artifacts()[2]->kind);
        $this->assertSame('runs/1/norm.png', $result->artifacts()[2]->path);
        $this->assertSame(0.87, $result->confidenceValue());
        $this->assertSame(4210, $result->inputTokens());
        $this->assertSame(12400, $result->costUsdMicros());
        $this->assertCount(1, $result->findings());
        $this->assertSame(FindingSeverity::Warning, $result->findings()[0]->severity);
    }

    #[Test]
    public function a_finding_serialises_to_a_stable_array(): void
    {
        $finding = Finding::blocker('line_items_sum_mismatch', 'Items total 4210, header says 4200', ['delta' => 10]);

        $this->assertSame([
            'code' => 'line_items_sum_mismatch',
            'severity' => 'blocker',
            'message' => 'Items total 4210, header says 4200',
            'context' => ['delta' => 10],
        ], $finding->toArray());
    }

    #[Test]
    public function failure_carries_the_exception(): void
    {
        $exception = StepException::retryable('AI provider returned 429');

        $result = StepResult::failure($exception);

        $this->assertSame(StepOutcome::Failure, $result->outcome());
        $this->assertSame($exception, $result->exception());
        $this->assertTrue($exception->isRetryable());
    }

    #[Test]
    public function skipped_carries_a_reason(): void
    {
        $result = StepResult::skipped('not applicable to this doc_type');

        $this->assertSame(StepOutcome::Skipped, $result->outcome());
        $this->assertSame('not applicable to this doc_type', $result->skipReason());
    }

    #[Test]
    public function hold_can_still_carry_artifacts_and_findings(): void
    {
        $result = StepResult::hold()
            ->artifact('review_request', ['fields' => ['total_amount']])
            ->finding(Finding::blocker('total_missing'));

        $this->assertSame(StepOutcome::Hold, $result->outcome());
        $this->assertCount(1, $result->artifacts());
        $this->assertCount(1, $result->findings());
    }
}
