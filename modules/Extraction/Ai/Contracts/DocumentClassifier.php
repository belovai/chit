<?php

declare(strict_types=1);

namespace Modules\Extraction\Ai\Contracts;

use Modules\Extraction\Ai\ValueObjects\ClassificationResult;
use Modules\Extraction\Enums\DocumentType;
use Modules\Extraction\Exceptions\AiException;

/**
 * Decides what kind of document some OCR text is. Takes text, never an image —
 * raw images never leave this machine.
 */
interface DocumentClassifier
{
    /**
     * @param  DocumentType|null  $hint  a user-supplied guess; treat as a strong
     *                                   prior, but report what the text actually says
     *
     * @throws AiException
     */
    public function classify(string $ocrText, ?DocumentType $hint = null): ClassificationResult;
}
