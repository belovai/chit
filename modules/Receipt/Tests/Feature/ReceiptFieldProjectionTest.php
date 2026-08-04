<?php

declare(strict_types=1);

namespace Modules\Receipt\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Extraction\Enums\DocumentType;
use Modules\Pipeline\Enums\ArtifactKind;
use Modules\Pipeline\Models\PipelineRun;
use Modules\Pipeline\Services\ArtifactWriter;
use Modules\Pipeline\ValueObjects\PendingArtifact;
use Modules\Receipt\Tests\Support\MakesReceiptRuns;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ReceiptFieldProjectionTest extends TestCase
{
    use MakesReceiptRuns, RefreshDatabase;

    #[Test]
    public function it_projects_doc_type_from_the_published_artifact(): void
    {
        [$receipt, $run] = $this->receiptRun();
        $step = $this->stepRow($run, 'classify_document', 'classify');

        app(ArtifactWriter::class)->write($step, new PendingArtifact(
            'doc_type', ArtifactKind::Json, ['value' => DocumentType::Receipt->value],
        ));

        $this->assertSame(DocumentType::Receipt, $receipt->refresh()->doc_type);
    }

    #[Test]
    public function it_projects_series_key_from_the_published_artifact(): void
    {
        [$receipt, $run] = $this->receiptRun();
        $step = $this->stepRow($run, 'match_provider', 'resolve');

        app(ArtifactWriter::class)->write($step, new PendingArtifact(
            'series_key', ArtifactKind::Json, ['value' => 'some-hash'],
        ));

        $this->assertSame('some-hash', $receipt->refresh()->series_key);
    }

    #[Test]
    public function a_run_that_is_no_longer_the_receipts_current_run_is_ignored(): void
    {
        [$receipt, $run] = $this->receiptRun();
        $receipt->update(['current_run_id' => null]);
        $step = $this->stepRow($run, 'classify_document', 'classify');

        app(ArtifactWriter::class)->write($step, new PendingArtifact(
            'doc_type', ArtifactKind::Json, ['value' => DocumentType::Receipt->value],
        ));

        $this->assertNull($receipt->refresh()->doc_type);
    }

    #[Test]
    public function a_run_whose_subject_is_not_a_receipt_is_ignored(): void
    {
        $run = PipelineRun::factory()->create();
        $step = $this->stepRow($run, 'classify_document', 'classify');

        app(ArtifactWriter::class)->write($step, new PendingArtifact(
            'doc_type', ArtifactKind::Json, ['value' => DocumentType::Receipt->value],
        ));

        $this->assertTrue(true, 'no exception thrown');
    }

    #[Test]
    public function an_unrelated_artifact_key_does_not_touch_any_receipt_column(): void
    {
        [$receipt, $run] = $this->receiptRun();
        $step = $this->stepRow($run, 'ocr', 'read');

        app(ArtifactWriter::class)->write($step, new PendingArtifact(
            'ocr_text', ArtifactKind::Text, ['text' => 'ALDI ...'],
        ));

        $this->assertNull($receipt->refresh()->doc_type);
    }
}
