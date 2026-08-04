<?php

declare(strict_types=1);

namespace Modules\Extraction\Ocr\Contracts;

use Modules\Extraction\Ocr\ValueObjects\OcrResult;

/**
 * Local, offline text recognition. Implementations must never send the image
 * anywhere — keeping raw images off third-party APIs is a project constraint,
 * not an implementation detail.
 */
interface OcrEngine
{
    public function read(string $disk, string $path): OcrResult;
}
