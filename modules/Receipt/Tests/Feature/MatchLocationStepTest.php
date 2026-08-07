<?php

declare(strict_types=1);

namespace Modules\Receipt\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Merchant\Models\Merchant;
use Modules\Merchant\Models\MerchantLocation;
use Modules\Pipeline\Enums\StepOutcome;
use Modules\Pipeline\ValueObjects\StepResult;
use Modules\Receipt\Steps\MatchLocationStep;
use Modules\Receipt\Tests\Support\MakesReceiptRuns;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class MatchLocationStepTest extends TestCase
{
    use MakesReceiptRuns, RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function receiptPayload(array $overrides = []): array
    {
        return [
            'merchant_name' => 'SPAR',
            'merchant_address' => '6723 Szeged, Szilléri sugár út 26.',
            'occurred_at' => '2026-07-30T10:35:00',
            'currency' => 'HUF',
            'total_minor' => 190000,
            'discount_minor' => null,
            'payment_method' => 'card',
            'items' => [],
            'confidence' => 0.9,
            ...$overrides,
        ];
    }

    /**
     * @param  array<string, mixed>  $payloadOverrides
     * @return array{0: StepResult, 1: Merchant}
     */
    private function runStep(array $payloadOverrides = [], int|false|null $merchantIdOverride = false): array
    {
        [$receipt, $run] = $this->receiptRun();
        $merchant = Merchant::factory()->create(['owner_id' => $receipt->owner_id, 'name' => 'SPAR']);

        $step = $this->stepRow($run, 'match_location', 'resolve');
        $this->seedArtifact($step, 'extracted_receipt', ['payload' => $this->receiptPayload($payloadOverrides)]);
        $this->seedArtifact($step, 'merchant_candidates', [
            'raw_name' => 'SPAR',
            'accepted_id' => $merchantIdOverride === false ? $merchant->id : $merchantIdOverride,
            'candidates' => [],
        ]);

        $result = app(MatchLocationStep::class)->handle($this->contextFor($step));

        return [$result, $merchant];
    }

    #[Test]
    public function a_receipt_without_an_address_produces_no_finding(): void
    {
        [$result] = $this->runStep(['merchant_address' => null]);

        $this->assertSame(StepOutcome::Success, $result->outcome());
        $this->assertSame([], $result->findings());
        $this->assertNull($result->artifacts()[0]->payload['accepted_id']);
        $this->assertNull($result->artifacts()[0]->payload['raw_address']);
    }

    #[Test]
    public function a_known_branch_is_accepted_silently(): void
    {
        [$receipt, $run] = $this->receiptRun();
        $merchant = Merchant::factory()->create(['owner_id' => $receipt->owner_id, 'name' => 'SPAR']);
        $location = MerchantLocation::factory()->for($merchant)->create([
            'address' => '6723 Szeged, Szilléri sugár út 26.',
        ]);

        $step = $this->stepRow($run, 'match_location', 'resolve');
        $this->seedArtifact($step, 'extracted_receipt', ['payload' => $this->receiptPayload()]);
        $this->seedArtifact($step, 'merchant_candidates', [
            'raw_name' => 'SPAR',
            'accepted_id' => $merchant->id,
            'candidates' => [],
        ]);

        $result = app(MatchLocationStep::class)->handle($this->contextFor($step));

        $this->assertSame([], $result->findings());
        $this->assertSame($location->id, $result->artifacts()[0]->payload['accepted_id']);
        $this->assertSame($location->hash_id, $result->artifacts()[0]->payload['accepted_hash_id']);
    }

    #[Test]
    public function a_new_branch_of_a_known_merchant_warns(): void
    {
        [$receipt, $run] = $this->receiptRun();
        $merchant = Merchant::factory()->create(['owner_id' => $receipt->owner_id, 'name' => 'SPAR']);
        MerchantLocation::factory()->for($merchant)->create(['address' => '1052 Budapest, Deák Ferenc tér 3.']);

        $step = $this->stepRow($run, 'match_location', 'resolve');
        $this->seedArtifact($step, 'extracted_receipt', ['payload' => $this->receiptPayload()]);
        $this->seedArtifact($step, 'merchant_candidates', [
            'raw_name' => 'SPAR',
            'accepted_id' => $merchant->id,
            'candidates' => [],
        ]);

        $result = app(MatchLocationStep::class)->handle($this->contextFor($step));

        $this->assertSame('new_location', $result->findings()[0]->code);
        $this->assertNull($result->artifacts()[0]->payload['accepted_id']);
        $this->assertSame('6723 Szeged, Szilléri sugár út 26.', $result->artifacts()[0]->payload['raw_address']);
    }

    #[Test]
    public function another_merchants_branch_is_not_a_candidate(): void
    {
        [$receipt, $run] = $this->receiptRun();
        $spar = Merchant::factory()->create(['owner_id' => $receipt->owner_id, 'name' => 'SPAR']);
        $lidl = Merchant::factory()->create(['owner_id' => $receipt->owner_id, 'name' => 'LIDL']);
        MerchantLocation::factory()->for($lidl)->create(['address' => '6723 Szeged, Szilléri sugár út 26.']);

        $step = $this->stepRow($run, 'match_location', 'resolve');
        $this->seedArtifact($step, 'extracted_receipt', ['payload' => $this->receiptPayload()]);
        $this->seedArtifact($step, 'merchant_candidates', [
            'raw_name' => 'SPAR',
            'accepted_id' => $spar->id,
            'candidates' => [],
        ]);

        $result = app(MatchLocationStep::class)->handle($this->contextFor($step));

        $this->assertSame([], $result->artifacts()[0]->payload['candidates']);
        $this->assertSame('new_location', $result->findings()[0]->code);
    }

    #[Test]
    public function two_close_candidates_are_ambiguous(): void
    {
        config()->set('receipt.matching.location_ambiguity_margin', 0.5);

        [$receipt, $run] = $this->receiptRun();
        $merchant = Merchant::factory()->create(['owner_id' => $receipt->owner_id, 'name' => 'SPAR']);
        MerchantLocation::factory()->for($merchant)->create(['address' => '6723 Szeged, Szilléri sugár út 26.']);
        MerchantLocation::factory()->for($merchant)->create(['address' => '6723 Szeged, Szilléri sugár út 27.']);

        $step = $this->stepRow($run, 'match_location', 'resolve');
        $this->seedArtifact($step, 'extracted_receipt', ['payload' => $this->receiptPayload()]);
        $this->seedArtifact($step, 'merchant_candidates', [
            'raw_name' => 'SPAR',
            'accepted_id' => $merchant->id,
            'candidates' => [],
        ]);

        $result = app(MatchLocationStep::class)->handle($this->contextFor($step));

        $this->assertSame('location_ambiguous', $result->findings()[0]->code);
        $this->assertNull($result->artifacts()[0]->payload['accepted_id']);
    }

    #[Test]
    public function no_accepted_merchant_means_no_location_and_no_finding(): void
    {
        [$result] = $this->runStep(merchantIdOverride: null);

        $this->assertSame([], $result->findings());
        $this->assertNull($result->artifacts()[0]->payload['accepted_id']);
    }
}
