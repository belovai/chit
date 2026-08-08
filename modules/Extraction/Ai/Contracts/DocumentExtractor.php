<?php

declare(strict_types=1);

namespace Modules\Extraction\Ai\Contracts;

use Modules\Ai\Exceptions\AiException;
use Modules\Ai\ValueObjects\AiConnection;
use Modules\Extraction\Ai\ValueObjects\ExtractionResult;
use Modules\Extraction\Enums\DocumentType;

interface DocumentExtractor
{
    /**
     * @throws AiException
     */
    public function extract(
        AiConnection $on,
        string $imageBytes,
        string $mimeType,
        DocumentType $type,
    ): ExtractionResult;
}
