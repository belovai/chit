<?php

declare(strict_types=1);

namespace Modules\Extraction\Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Modules\Extraction\Exceptions\OcrException;
use Modules\Extraction\Ocr\Contracts\OcrEngine;
use Modules\Extraction\Ocr\ImagePreprocessor;
use Modules\Extraction\Ocr\Testing\FakeOcrEngine;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class OcrTest extends TestCase
{
    private function fixture(string $name): string
    {
        return __DIR__.'/../Support/fixtures/'.$name;
    }

    #[Test]
    public function it_reads_a_receipt_image(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('in/aldi.png', (string) file_get_contents($this->fixture('aldi-receipt.png')));

        $result = app(OcrEngine::class)->read('local', 'in/aldi.png');

        $this->assertStringContainsString('ALDI', $result->text);
        $this->assertStringContainsString('1327', $result->text);
        $this->assertGreaterThan(0.0, $result->meanConfidence);
        $this->assertLessThanOrEqual(1.0, $result->meanConfidence);
        $this->assertSame('tesseract', $result->engine);
        $this->assertGreaterThan(0, $result->durationMs);
    }

    #[Test]
    public function it_reads_a_utility_bill_image(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('in/elmu.png', (string) file_get_contents($this->fixture('elmu-bill.png')));

        $result = app(OcrEngine::class)->read('local', 'in/elmu.png');

        $this->assertStringContainsString('45231', $result->text);
        $this->assertStringContainsString('1234567890', $result->text);
    }

    #[Test]
    public function reading_a_missing_file_fails_loudly(): void
    {
        Storage::fake('local');

        $this->expectException(OcrException::class);

        app(OcrEngine::class)->read('local', 'in/nope.png');
    }

    #[Test]
    public function the_preprocessor_writes_a_normalized_png_next_to_the_source(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('in/aldi.png', (string) file_get_contents($this->fixture('aldi-receipt.png')));

        $path = app(ImagePreprocessor::class)->normalize('local', 'in/aldi.png', 'image/png');

        $this->assertNotSame('in/aldi.png', $path);
        Storage::disk('local')->assertExists($path);
        $this->assertStringEndsWith('.png', $path);
    }

    #[Test]
    public function the_preprocessor_rejects_an_unsupported_mime(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('in/notes.txt', 'hello');

        $this->expectException(OcrException::class);

        app(ImagePreprocessor::class)->normalize('local', 'in/notes.txt', 'text/plain');
    }

    #[Test]
    public function the_fake_engine_returns_whatever_a_test_told_it_to(): void
    {
        FakeOcrEngine::reset();
        FakeOcrEngine::returns("ALDI\nOSSZESEN 1327", 0.88);
        $this->app->bind(OcrEngine::class, FakeOcrEngine::class);

        $result = app(OcrEngine::class)->read('local', 'anything.png');

        $this->assertSame("ALDI\nOSSZESEN 1327", $result->text);
        $this->assertSame(0.88, $result->meanConfidence);
        $this->assertSame(1, FakeOcrEngine::readCount());
    }
}
