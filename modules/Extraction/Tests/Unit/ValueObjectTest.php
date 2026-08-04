<?php

declare(strict_types=1);

namespace Modules\Extraction\Tests\Unit;

use Carbon\CarbonImmutable;
use Modules\Extraction\Ai\ValueObjects\AiUsage;
use Modules\Extraction\Ai\ValueObjects\ExtractedLineItem;
use Modules\Extraction\Ai\ValueObjects\ExtractedReceipt;
use Modules\Extraction\Enums\DocumentType;
use Modules\Extraction\Exceptions\AiException;
use Modules\Extraction\Ocr\ValueObjects\OcrResult;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ValueObjectTest extends TestCase
{
    #[Test]
    public function an_ocr_result_exposes_its_text_and_confidence(): void
    {
        $result = new OcrResult(
            text: "ALDI\nTej 2.8%  389",
            meanConfidence: 0.91,
            pageConfidences: [0.94, 0.88],
            engine: 'tesseract',
            durationMs: 1840,
        );

        $this->assertStringContainsString('ALDI', $result->text);
        $this->assertSame(0.91, $result->meanConfidence);
        $this->assertSame([0.94, 0.88], $result->pageConfidences);
    }

    #[Test]
    public function a_receipt_sums_its_line_items_in_minor_units(): void
    {
        $receipt = new ExtractedReceipt(
            merchantName: 'ALDI',
            occurredAt: CarbonImmutable::parse('2026-07-30 14:12:00'),
            currency: 'HUF',
            totalMinor: 132700,
            discountMinor: null,
            paymentMethod: 'card',
            items: [
                new ExtractedLineItem('Tej 2.8%', 2.0, 'db', 38900, 77800),
                new ExtractedLineItem('Kenyer', 1.0, 'db', 54900, 54900),
            ],
        );

        $this->assertSame(132700, $receipt->itemsTotalMinor());
        $this->assertSame(132700, $receipt->totalMinor);
    }

    #[Test]
    public function a_line_item_total_falls_back_to_quantity_times_unit_price(): void
    {
        $item = new ExtractedLineItem('Tej', 3.0, 'db', 38900, null);

        $this->assertSame(116700, $item->effectiveTotalMinor());
    }

    #[Test]
    public function usage_none_is_all_zeroes(): void
    {
        $usage = AiUsage::none();

        $this->assertSame(0, $usage->inputTokens);
        $this->assertSame(0, $usage->outputTokens);
        $this->assertSame(0, $usage->costUsdMicros);
    }

    #[Test]
    public function usage_can_be_summed(): void
    {
        $total = (new AiUsage(100, 20, 0, 900))->plus(new AiUsage(50, 10, 40, 400));

        $this->assertSame(150, $total->inputTokens);
        $this->assertSame(30, $total->outputTokens);
        $this->assertSame(40, $total->cachedInputTokens);
        $this->assertSame(1300, $total->costUsdMicros);
    }

    #[Test]
    public function document_type_parses_an_unknown_string_as_unknown(): void
    {
        $this->assertSame(DocumentType::Receipt, DocumentType::parse('receipt'));
        $this->assertSame(DocumentType::Unknown, DocumentType::parse('banana'));
    }

    #[Test]
    public function an_ai_exception_carries_its_retryability(): void
    {
        $this->assertTrue(AiException::retryable('429 from provider')->isRetryable());
        $this->assertFalse(AiException::permanent('schema mismatch')->isRetryable());
    }
}
