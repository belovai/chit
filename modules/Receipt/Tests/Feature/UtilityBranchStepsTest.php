<?php

declare(strict_types=1);

namespace Modules\Receipt\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Extraction\Ai\Testing\FakeDocumentAi;
use Modules\Extraction\Ai\ValueObjects\ExtractedBill;
use Modules\Extraction\Enums\DocumentType;
use Modules\Pipeline\Enums\ArtifactKind;
use Modules\Pipeline\Enums\StepOutcome;
use Modules\Pipeline\Models\PipelineRun;
use Modules\Pipeline\Models\PipelineRunStep;
use Modules\Pipeline\Services\ArtifactWriter;
use Modules\Pipeline\ValueObjects\PendingArtifact;
use Modules\Receipt\Enums\ReceiptStatus;
use Modules\Receipt\Models\Receipt;
use Modules\Receipt\Steps\AnomalyCheckStep;
use Modules\Receipt\Steps\ExtractUtilityBillStep;
use Modules\Receipt\Steps\LinkSeriesStep;
use Modules\Receipt\Steps\MatchProviderStep;
use Modules\Receipt\Tests\Support\MakesReceiptRuns;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class UtilityBranchStepsTest extends TestCase
{
    use MakesReceiptRuns, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        FakeDocumentAi::reset();
        config()->set('extraction.ai.fake_documents', true);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function billPayload(array $overrides = []): array
    {
        return [
            'provider_name' => 'ELMU',
            'customer_reference' => '1234567890',
            'currency' => 'HUF',
            'total_minor' => 1845000,
            'issued_at' => '2026-07-05',
            'period_start' => '2026-06-01',
            'period_end' => '2026-06-30',
            'meter_reading' => 45231,
            'previous_meter_reading' => 44919,
            'consumption' => 312,
            'consumption_unit' => 'kWh',
            'confidence' => 0.9,
            ...$overrides,
        ];
    }

    /** Creates an approved earlier bill in the same series. */
    private function previousBill(Receipt $current, array $payload): Receipt
    {
        $earlier = Receipt::factory()->for($current->owner, 'owner')->create([
            'doc_type' => DocumentType::UtilityBill,
            'series_key' => sha1('ELMU|1234567890'),
            'status' => ReceiptStatus::Approved,
            'created_at' => now()->subMonth(),
        ]);
        $run = PipelineRun::factory()->for($current->owner, 'owner')->create([
            'subject_type' => Receipt::class,
            'subject_id' => $earlier->id,
        ]);
        $earlier->update(['current_run_id' => $run->id]);
        $step = PipelineRunStep::factory()->for($run, 'run')->create(['step_key' => 'extract_utility_bill']);
        app(ArtifactWriter::class)->write($step, new PendingArtifact(
            'extracted_bill', ArtifactKind::Json, ['payload' => $payload, 'confidence' => 0.9],
        ));

        return $earlier;
    }

    #[Test]
    public function extract_utility_bill_publishes_the_raw_payload_confidence_and_cost(): void
    {
        FakeDocumentAi::willExtract(new ExtractedBill(
            providerName: 'ELMU',
            customerReference: '1234567890',
            currency: 'HUF',
            totalMinor: 1845000,
            issuedAt: null,
            periodStart: null,
            periodEnd: null,
            meterReading: 45231.0,
            previousMeterReading: null,
            consumption: 312.0,
            consumptionUnit: 'kWh',
        ), 0.9);

        [$receipt, $run] = $this->receiptRun();
        $step = $this->stepRow($run, 'extract_utility_bill', 'extract');
        $this->putFile('local', 'normalized/x.png', 'png');
        app(ArtifactWriter::class)->write($step, new PendingArtifact(
            'normalized_image', ArtifactKind::Binary, null, 'local', 'normalized/x.png', 'image/png', 3,
        ));

        $result = app(ExtractUtilityBillStep::class)->handle($this->contextFor($step));

        $this->assertSame(StepOutcome::Success, $result->outcome());
        $this->assertSame('extracted_bill', $result->artifacts()[0]->key);
        $this->assertSame(0.9, $result->confidenceValue());
        $this->assertGreaterThan(0, $result->costUsdMicros());
    }

    #[Test]
    public function match_provider_projects_the_series_key_onto_the_receipt(): void
    {
        [$receipt, $run] = $this->receiptRun();
        $step = $this->stepRow($run, 'match_provider', 'resolve');
        $this->seedArtifact($step, 'extracted_bill', ['payload' => $this->billPayload(), 'confidence' => 0.9]);

        $result = app(MatchProviderStep::class)->handle($this->contextFor($step));

        $seriesKeyArtifact = null;
        foreach ($result->artifacts() as $artifact) {
            if ($artifact->key === 'series_key') {
                $seriesKeyArtifact = $artifact;
            }
        }

        $this->assertNotNull($seriesKeyArtifact);
        $this->assertSame(sha1('ELMU|1234567890'), $seriesKeyArtifact->payload['value']);
    }

    #[Test]
    public function match_provider_emits_merchant_candidates(): void
    {
        [$receipt, $run] = $this->receiptRun();
        $step = $this->stepRow($run, 'match_provider', 'resolve');
        $this->seedArtifact($step, 'extracted_bill', ['payload' => $this->billPayload(), 'confidence' => 0.9]);

        $result = app(MatchProviderStep::class)->handle($this->contextFor($step));

        $this->assertSame('merchant_candidates', $result->artifacts()[0]->key);
        $this->assertSame('new_merchant', $result->findings()[0]->code);
    }

    #[Test]
    public function link_series_finds_the_previous_bill(): void
    {
        [$receipt, $run] = $this->receiptRun(['series_key' => sha1('ELMU|1234567890')]);
        $this->previousBill($receipt, $this->billPayload(['period_end' => '2026-05-31', 'meter_reading' => 44919]));
        $step = $this->stepRow($run, 'link_series', 'resolve');
        $this->seedArtifact($step, 'extracted_bill', ['payload' => $this->billPayload(), 'confidence' => 0.9]);

        $result = app(LinkSeriesStep::class)->handle($this->contextFor($step));

        $payload = $result->artifacts()[0]->payload;
        $this->assertSame('previous_bill', $result->artifacts()[0]->key);
        $this->assertSame(44919.0, $payload['meter_reading']);
        $this->assertSame([], $result->findings());
    }

    #[Test]
    public function link_series_reports_the_first_bill_of_a_series_as_info(): void
    {
        [$receipt, $run] = $this->receiptRun(['series_key' => sha1('ELMU|1234567890')]);
        $step = $this->stepRow($run, 'link_series', 'resolve');
        $this->seedArtifact($step, 'extracted_bill', ['payload' => $this->billPayload(), 'confidence' => 0.9]);

        $result = app(LinkSeriesStep::class)->handle($this->contextFor($step));

        $this->assertNull($result->artifacts()[0]->payload['found']);
        $this->assertSame('no_previous_bill', $result->findings()[0]->code);
    }

    #[Test]
    public function anomaly_check_flags_a_meter_reading_that_went_backwards(): void
    {
        [$receipt, $run] = $this->receiptRun();
        $step = $this->stepRow($run, 'anomaly_check', 'validate');
        $this->seedArtifact($step, 'extracted_bill', [
            'payload' => $this->billPayload(['meter_reading' => 4523]),
            'confidence' => 0.9,
        ]);
        $this->seedArtifact($step, 'previous_bill', [
            'found' => true, 'meter_reading' => 44919.0, 'consumption' => 300.0,
            'period_end' => '2026-05-31', 'consumption_unit' => 'kWh',
        ]);

        $findings = app(AnomalyCheckStep::class)->handle($this->contextFor($step))->findings();

        $this->assertSame('meter_reading_decreased', $findings[0]->code);
        $this->assertSame(44919.0, $findings[0]->context['previous']);
    }

    #[Test]
    public function anomaly_check_flags_a_consumption_spike(): void
    {
        [$receipt, $run] = $this->receiptRun();
        $step = $this->stepRow($run, 'anomaly_check', 'validate');
        $this->seedArtifact($step, 'extracted_bill', [
            'payload' => $this->billPayload(['consumption' => 1500]),
            'confidence' => 0.9,
        ]);
        $this->seedArtifact($step, 'previous_bill', [
            'found' => true, 'meter_reading' => 44919.0, 'consumption' => 300.0,
            'period_end' => '2026-05-31', 'consumption_unit' => 'kWh',
        ]);

        $codes = array_map(fn ($f) => $f->code, app(AnomalyCheckStep::class)->handle($this->contextFor($step))->findings());

        $this->assertContains('consumption_anomaly', $codes);
    }

    #[Test]
    public function anomaly_check_flags_a_gap_between_billing_periods(): void
    {
        [$receipt, $run] = $this->receiptRun();
        $step = $this->stepRow($run, 'anomaly_check', 'validate');
        $this->seedArtifact($step, 'extracted_bill', ['payload' => $this->billPayload(), 'confidence' => 0.9]);
        $this->seedArtifact($step, 'previous_bill', [
            'found' => true, 'meter_reading' => 44919.0, 'consumption' => 300.0,
            'period_end' => '2026-01-31', 'consumption_unit' => 'kWh',
        ]);

        $codes = array_map(fn ($f) => $f->code, app(AnomalyCheckStep::class)->handle($this->contextFor($step))->findings());

        $this->assertContains('period_gap', $codes);
    }

    #[Test]
    public function anomaly_check_is_silent_on_a_normal_bill(): void
    {
        [$receipt, $run] = $this->receiptRun();
        $step = $this->stepRow($run, 'anomaly_check', 'validate');
        $this->seedArtifact($step, 'extracted_bill', ['payload' => $this->billPayload(), 'confidence' => 0.9]);
        $this->seedArtifact($step, 'previous_bill', [
            'found' => true, 'meter_reading' => 44919.0, 'consumption' => 300.0,
            'period_end' => '2026-05-31', 'consumption_unit' => 'kWh',
        ]);

        $this->assertSame([], app(AnomalyCheckStep::class)->handle($this->contextFor($step))->findings());
    }

    #[Test]
    public function anomaly_check_skips_itself_without_a_previous_bill(): void
    {
        [$receipt, $run] = $this->receiptRun();
        $step = $this->stepRow($run, 'anomaly_check', 'validate');
        $this->seedArtifact($step, 'extracted_bill', ['payload' => $this->billPayload(), 'confidence' => 0.9]);
        $this->seedArtifact($step, 'previous_bill', ['found' => null]);

        $result = app(AnomalyCheckStep::class)->handle($this->contextFor($step));

        $this->assertSame(StepOutcome::Skipped, $result->outcome());
    }
}
