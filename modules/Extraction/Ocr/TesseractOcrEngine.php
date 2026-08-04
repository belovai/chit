<?php

declare(strict_types=1);

namespace Modules\Extraction\Ocr;

use Illuminate\Support\Facades\Storage;
use Modules\Extraction\Exceptions\OcrException;
use Modules\Extraction\Ocr\Contracts\OcrEngine;
use Modules\Extraction\Ocr\ValueObjects\OcrResult;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

final class TesseractOcrEngine implements OcrEngine
{
    public function read(string $disk, string $path): OcrResult
    {
        $storage = Storage::disk($disk);

        if (!$storage->exists($path)) {
            throw OcrException::engineFailed("file [{$path}] not found on disk [{$disk}]");
        }

        $temporary = tempnam(sys_get_temp_dir(), 'ocr_');

        if ($temporary === false) {
            throw OcrException::engineFailed('could not allocate a temporary file');
        }

        file_put_contents($temporary, (string) $storage->get($path));

        try {
            $startedAt = microtime(true);
            $text = $this->runTesseract($temporary, stdout: true);
            $confidence = $this->confidenceFrom($temporary);
            $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
        } finally {
            @unlink($temporary);
        }

        return new OcrResult(
            text: trim($text),
            meanConfidence: $confidence,
            pageConfidences: [$confidence],
            engine: 'tesseract',
            durationMs: $durationMs,
        );
    }

    private function runTesseract(string $file, bool $stdout, string ...$extra): string
    {
        $process = new Process([
            (string) config('extraction.ocr.binary'),
            $file,
            $stdout ? 'stdout' : '-',
            '-l', (string) config('extraction.ocr.languages'),
            ...$extra,
        ]);

        $process->setTimeout((float) config('extraction.ocr.timeout_seconds'));

        try {
            $process->mustRun();
        } catch (ProcessFailedException $exception) {
            throw OcrException::engineFailed($process->getErrorOutput(), $exception);
        }

        return $process->getOutput();
    }

    /**
     * Tesseract does not report confidence on the plain-text path, so ask for
     * TSV and average the per-word confidences. Words with conf = -1 are layout
     * rows, not recognised text — they must be excluded or the mean is garbage.
     */
    private function confidenceFrom(string $file): float
    {
        $tsv = $this->runTesseract($file, true, 'tsv');

        $confidences = [];

        foreach (explode("\n", $tsv) as $index => $line) {
            if ($index === 0 || trim($line) === '') {
                continue;
            }

            $columns = explode("\t", $line);

            if (count($columns) < 12) {
                continue;
            }

            $confidence = (float) $columns[10];

            if ($confidence >= 0.0 && trim($columns[11]) !== '') {
                $confidences[] = $confidence;
            }
        }

        if ($confidences === []) {
            return 0.0;
        }

        return round(array_sum($confidences) / count($confidences) / 100, 4);
    }
}
