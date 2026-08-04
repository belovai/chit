<?php

declare(strict_types=1);

namespace Modules\Receipt\Tests\Feature;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Extraction\Enums\DocumentType;
use Modules\Pipeline\Models\PipelineRun;
use Modules\Receipt\Enums\ReceiptStatus;
use Modules\Receipt\Models\Receipt;
use Modules\Receipt\Models\ReceiptCorrection;
use Modules\User\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ReceiptModelTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_casts_and_hashes(): void
    {
        $receipt = Receipt::factory()->create([
            'status' => ReceiptStatus::NeedsReview,
            'doc_type' => DocumentType::Receipt,
        ]);

        $receipt->refresh();

        $this->assertSame(ReceiptStatus::NeedsReview, $receipt->status);
        $this->assertSame(DocumentType::Receipt, $receipt->doc_type);
        $this->assertSame(10, mb_strlen($receipt->hash_id));
    }

    #[Test]
    public function it_belongs_to_a_run_and_an_owner(): void
    {
        $user = User::factory()->create();
        $run = PipelineRun::factory()->for($user, 'owner')->create();
        $receipt = Receipt::factory()->for($user, 'owner')->create(['current_run_id' => $run->id]);

        $this->assertSame($user->id, $receipt->owner?->id);
        $this->assertSame($run->id, $receipt->currentRun?->id);
    }

    #[Test]
    public function it_collects_corrections(): void
    {
        $receipt = Receipt::factory()->create();
        ReceiptCorrection::factory()->for($receipt, 'receipt')->create([
            'field_path' => 'items.2.unit_price_minor',
            'ai_value' => ['value' => 38900],
            'corrected_value' => ['value' => 39900],
        ]);

        $this->assertCount(1, $receipt->corrections);
        $this->assertSame(39900, $receipt->corrections->first()?->corrected_value['value']);
    }

    #[Test]
    public function another_users_receipt_is_not_route_bindable(): void
    {
        $receipt = Receipt::factory()->create();
        $intruder = User::factory()->create();
        $this->actingAs($intruder);

        $this->expectException(ModelNotFoundException::class);

        (new Receipt)->resolveRouteBinding($receipt->hash_id);
    }
}
