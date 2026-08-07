<?php

declare(strict_types=1);

namespace Modules\Receipt\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Extraction\Enums\DocumentType;
use Modules\Pipeline\Enums\StepOutcome;
use Modules\Receipt\Steps\ReviewGateStep;
use Modules\Receipt\Tests\Support\MakesReceiptRuns;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ReviewGateStepTest extends TestCase
{
    use MakesReceiptRuns, RefreshDatabase;

    /**
     * @param  list<string>  $codes
     * @return array<string, mixed>
     */
    private function gateWith(array $codes): array
    {
        [$receipt, $run] = $this->receiptRun(['doc_type' => DocumentType::Receipt]);

        $matchStep = $this->stepRow($run, 'match_location', 'resolve');
        $matchStep->update(['findings' => array_map(
            static fn (string $code): array => [
                'code' => $code, 'severity' => 'warning', 'message' => null, 'context' => [],
            ],
            $codes,
        )]);

        $gateStep = $this->stepRow($run, 'review_gate', 'review');
        $this->seedArtifact($gateStep, 'extracted_receipt', ['payload' => [], 'confidence' => 0.95]);

        $result = app(ReviewGateStep::class)->handle($this->contextFor($gateStep));

        $this->assertSame(StepOutcome::Hold, $result->outcome());

        /** @var array<string, mixed> $payload */
        $payload = $result->artifacts()[0]->payload;

        return $payload;
    }

    #[Test]
    public function a_new_location_finding_flags_the_merchant_field(): void
    {
        $this->assertSame(['merchant'], $this->gateWith(['new_location'])['fields']);
    }

    #[Test]
    public function an_ambiguous_location_flags_the_merchant_field(): void
    {
        $this->assertSame(['merchant'], $this->gateWith(['location_ambiguous'])['fields']);
    }

    #[Test]
    public function a_new_merchant_and_a_new_location_collapse_to_one_field(): void
    {
        $this->assertSame(['merchant'], $this->gateWith(['new_merchant', 'new_location'])['fields']);
    }
}
