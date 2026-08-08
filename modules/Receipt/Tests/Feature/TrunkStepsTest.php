<?php

declare(strict_types=1);

namespace Modules\Receipt\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Modules\Extraction\Ai\Testing\FakeDocumentAi;
use Modules\Extraction\Enums\DocumentType;
use Modules\Extraction\Ocr\Testing\FakeOcrEngine;
use Modules\Pipeline\Enums\ArtifactKind;
use Modules\Pipeline\Enums\StepOutcome;
use Modules\Pipeline\Services\ArtifactWriter;
use Modules\Pipeline\ValueObjects\PendingArtifact;
use Modules\Receipt\Models\Receipt;
use Modules\Receipt\Steps\ClassifyDocumentStep;
use Modules\Receipt\Steps\DedupeFileHashStep;
use Modules\Receipt\Steps\OcrStep;
use Modules\Receipt\Steps\PreprocessImageStep;
use Modules\Receipt\Steps\StoreFileStep;
use Modules\Receipt\Tests\Support\MakesReceiptRuns;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class TrunkStepsTest extends TestCase
{
    use MakesReceiptRuns, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        FakeOcrEngine::reset();
        FakeDocumentAi::reset();
        config()->set('extraction.ocr.engine', 'fake');
        config()->set('extraction.ai.fake_documents', true);
    }

    #[Test]
    public function store_file_publishes_the_file_reference_and_hash(): void
    {
        [$receipt, $run] = $this->receiptRun(['path' => 'receipts/a.jpg', 'file_hash' => 'abc123']);
        $this->putFile('local', 'receipts/a.jpg', 'bytes');
        $step = $this->stepRow($run, 'store_file');

        $result = app(StoreFileStep::class)->handle($this->contextFor($step));

        $this->assertSame(StepOutcome::Success, $result->outcome());
        $keys = array_map(fn ($a) => $a->key, $result->artifacts());
        $this->assertContains('raw_file', $keys);
        $this->assertContains('file_hash', $keys);
        $this->assertSame(
            'receipts/a.jpg',
            $result->artifacts()[array_search('raw_file', $keys, true)]->payload['path'],
        );
    }

    #[Test]
    public function store_file_fails_permanently_when_the_file_is_gone(): void
    {
        [$receipt, $run] = $this->receiptRun(['path' => 'receipts/missing.jpg']);
        $step = $this->stepRow($run, 'store_file');

        $result = app(StoreFileStep::class)->handle($this->contextFor($step));

        $this->assertSame(StepOutcome::Failure, $result->outcome());
    }

    #[Test]
    public function dedupe_flags_a_byte_identical_upload_by_the_same_owner(): void
    {
        [$receipt, $run] = $this->receiptRun(['file_hash' => 'samehash']);
        Receipt::factory()->for($receipt->owner, 'owner')->create(['file_hash' => 'samehash']);
        $step = $this->stepRow($run, 'dedupe_file_hash');
        $this->seedArtifact($step, 'file_hash', ['value' => 'samehash']);

        $result = app(DedupeFileHashStep::class)->handle($this->contextFor($step));

        $this->assertSame(StepOutcome::Success, $result->outcome());
        $this->assertSame('exact_duplicate', $result->findings()[0]->code);
    }

    #[Test]
    public function dedupe_ignores_the_same_hash_belonging_to_another_owner(): void
    {
        [$receipt, $run] = $this->receiptRun(['file_hash' => 'samehash']);
        Receipt::factory()->create(['file_hash' => 'samehash']);
        $step = $this->stepRow($run, 'dedupe_file_hash');
        $this->seedArtifact($step, 'file_hash', ['value' => 'samehash']);

        $result = app(DedupeFileHashStep::class)->handle($this->contextFor($step));

        $this->assertSame([], $result->findings());
    }

    #[Test]
    public function preprocess_writes_a_normalized_binary_artifact(): void
    {
        [$receipt, $run] = $this->receiptRun(['path' => 'receipts/a.png', 'mime' => 'image/png']);
        $this->putFile('local', 'receipts/a.png', (string) file_get_contents(
            base_path('modules/Extraction/Tests/Support/fixtures/aldi-receipt.png'),
        ));
        $step = $this->stepRow($run, 'preprocess_image', 'prepare');
        $this->seedArtifact($step, 'raw_file', [
            'disk' => 'local', 'path' => 'receipts/a.png', 'mime' => 'image/png', 'size_bytes' => 100,
        ]);

        $result = app(PreprocessImageStep::class)->handle($this->contextFor($step));

        $this->assertSame(StepOutcome::Success, $result->outcome());
        $this->assertSame('normalized_image', $result->artifacts()[0]->key);
        $this->assertSame(ArtifactKind::Binary, $result->artifacts()[0]->kind);
    }

    #[Test]
    public function ocr_publishes_text_and_confidence(): void
    {
        FakeOcrEngine::returns("ALDI\nOSSZESEN 1327", 0.93);
        [$receipt, $run] = $this->receiptRun();
        $step = $this->stepRow($run, 'ocr', 'read');
        $this->putFile('local', 'normalized/x.png', 'png');
        app(ArtifactWriter::class)->write($step, new PendingArtifact(
            'normalized_image', ArtifactKind::Binary, null, 'local', 'normalized/x.png', 'image/png', 3,
        ));

        $result = app(OcrStep::class)->handle($this->contextFor($step));

        $keys = array_map(fn ($a) => $a->key, $result->artifacts());
        $this->assertContains('ocr_text', $keys);
        $this->assertContains('ocr_confidence', $keys);
        $this->assertSame(0.93, $result->confidenceValue());
        $this->assertSame([], $result->findings());
    }

    #[Test]
    public function ocr_flags_low_confidence(): void
    {
        FakeOcrEngine::returns('smudged', 0.41);
        [$receipt, $run] = $this->receiptRun();
        $step = $this->stepRow($run, 'ocr', 'read');
        $this->putFile('local', 'normalized/x.png', 'png');
        app(ArtifactWriter::class)->write($step, new PendingArtifact(
            'normalized_image', ArtifactKind::Binary, null, 'local', 'normalized/x.png', 'image/png', 3,
        ));

        $result = app(OcrStep::class)->handle($this->contextFor($step));

        $this->assertSame('low_ocr_confidence', $result->findings()[0]->code);
    }

    #[Test]
    public function classify_expands_the_run_for_a_receipt(): void
    {
        FakeDocumentAi::willClassify(DocumentType::Receipt, 0.96);
        [$receipt, $run] = $this->receiptRun();
        $step = $this->stepRow($run, 'classify_document', 'classify');
        $this->seedTextArtifact($step, 'ocr_text', 'ALDI ...');

        $result = app(ClassifyDocumentStep::class)->handle($this->contextFor($step));

        $expanded = array_map(fn ($d) => $d->key(), $result->expansions());
        $this->assertSame(
            ['extract_receipt', 'match_merchant', 'match_location', 'match_products', 'validate_totals', 'dedupe_content'],
            $expanded,
        );
    }

    #[Test]
    public function classify_expands_the_run_for_a_utility_bill(): void
    {
        FakeDocumentAi::willClassify(DocumentType::UtilityBill, 0.91);
        [$receipt, $run] = $this->receiptRun();
        $step = $this->stepRow($run, 'classify_document', 'classify');
        $this->seedTextArtifact($step, 'ocr_text', 'ELMU ...');

        $result = app(ClassifyDocumentStep::class)->handle($this->contextFor($step));

        $expanded = array_map(fn ($d) => $d->key(), $result->expansions());
        $this->assertSame(
            ['extract_utility_bill', 'match_provider', 'link_series', 'validate_totals', 'anomaly_check', 'dedupe_content'],
            $expanded,
        );
    }

    #[Test]
    public function an_unknown_classification_blocks_and_does_not_expand(): void
    {
        FakeDocumentAi::willClassify(DocumentType::Unknown, 0.2);
        [$receipt, $run] = $this->receiptRun();
        $step = $this->stepRow($run, 'classify_document', 'classify');
        $this->seedTextArtifact($step, 'ocr_text', '???');

        $result = app(ClassifyDocumentStep::class)->handle($this->contextFor($step));

        $this->assertSame([], $result->expansions());
        $this->assertSame('classification_uncertain', $result->findings()[0]->code);
    }

    #[Test]
    public function a_hint_that_contradicts_the_classification_is_flagged(): void
    {
        FakeDocumentAi::willClassify(DocumentType::UtilityBill, 0.9);
        [$receipt, $run] = $this->receiptRun(['doc_type_hint' => DocumentType::Receipt]);
        $step = $this->stepRow($run, 'classify_document', 'classify');
        $this->seedTextArtifact($step, 'ocr_text', 'ELMU ...');

        $result = app(ClassifyDocumentStep::class)->handle($this->contextFor($step));

        $codes = array_map(fn ($f) => $f->code, $result->findings());
        $this->assertContains('classification_conflict', $codes);
    }
}
