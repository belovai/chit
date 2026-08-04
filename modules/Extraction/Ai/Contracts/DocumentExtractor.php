<?php

declare(strict_types=1);

namespace Modules\Extraction\Ai\Contracts;

use Modules\Extraction\Ai\ValueObjects\ExtractionResult;
use Modules\Extraction\Enums\DocumentType;
use Modules\Extraction\Exceptions\AiException;

interface DocumentExtractor
{
    /**
     * @throws AiException
     */
    public function extract(string $imageBytes, string $mimeType, DocumentType $type): ExtractionResult;
}
