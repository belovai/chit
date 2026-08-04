<?php

declare(strict_types=1);

namespace Modules\Extraction\Ocr\Testing;

use Modules\Extraction\Ocr\Contracts\OcrEngine;
use Modules\Extraction\Ocr\ValueObjects\OcrResult;

/**
 * Lives in the module (not Tests/) so other modules' tests can bind it without
 * depending on this module's test autoload paths.
 */
final class FakeOcrEngine implements OcrEngine
{
    private static string $text = '';

    private static float $confidence = 0.95;

    private static int $readCount = 0;

    public static function returns(string $text, float $confidence = 0.95): void
    {
        self::$text = $text;
        self::$confidence = $confidence;
    }

    public static function reset(): void
    {
        self::$text = '';
        self::$confidence = 0.95;
        self::$readCount = 0;
    }

    public static function readCount(): int
    {
        return self::$readCount;
    }

    public function read(string $disk, string $path): OcrResult
    {
        self::$readCount++;

        return new OcrResult(
            text: self::$text,
            meanConfidence: self::$confidence,
            pageConfidences: [self::$confidence],
            engine: 'fake',
            durationMs: 1,
        );
    }
}
