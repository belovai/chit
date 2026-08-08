<?php

declare(strict_types=1);

namespace Modules\Extraction\Ai\Contracts;

use Modules\Ai\Exceptions\AiException;
use Modules\Ai\ValueObjects\AiConnection;
use Modules\Extraction\Ai\ValueObjects\ClassificationResult;
use Modules\Extraction\Enums\DocumentType;

/**
 * Decides what kind of document some OCR text is. Takes text, never an image —
 * raw images never leave this machine.
 */
interface DocumentClassifier
{
    /**
     * @param  AiConnection  $on  the caller's resolved credential; this module
     *                            never looks one up for itself
     * @param  DocumentType|null  $hint  a user-supplied guess; treat as a strong
     *                                   prior, but report what the text actually says
     *
     * @throws AiException
     */
    public function classify(AiConnection $on, string $ocrText, ?DocumentType $hint = null): ClassificationResult;
}
