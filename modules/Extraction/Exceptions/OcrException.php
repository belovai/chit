<?php

declare(strict_types=1);

namespace Modules\Extraction\Exceptions;

use RuntimeException;
use Throwable;

final class OcrException extends RuntimeException
{
    public static function binaryMissing(string $binary): self
    {
        return new self("OCR binary [{$binary}] not found in the container.");
    }

    public static function engineFailed(string $message, ?Throwable $previous = null): self
    {
        return new self("OCR engine failed: {$message}", 0, $previous);
    }

    public static function unsupportedMime(string $mime): self
    {
        return new self("Cannot preprocess a document of type [{$mime}].");
    }
}
