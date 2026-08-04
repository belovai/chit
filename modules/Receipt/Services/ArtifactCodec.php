<?php

declare(strict_types=1);

namespace Modules\Receipt\Services;

use Modules\Extraction\Ai\Support\DocumentMapper;
use Modules\Extraction\Ai\ValueObjects\ExtractedBill;
use Modules\Extraction\Ai\ValueObjects\ExtractedReceipt;
use Modules\Pipeline\ValueObjects\StepContext;
use Modules\Receipt\Models\Receipt;

/** One place that knows the shape of the shared artifact payloads. */
final class ArtifactCodec
{
    /**
     * @return array{disk: string, path: string, mime: string, size_bytes: int}
     */
    public static function rawFile(Receipt $receipt): array
    {
        return [
            'disk' => $receipt->disk,
            'path' => $receipt->path,
            'mime' => $receipt->mime,
            'size_bytes' => $receipt->size_bytes,
        ];
    }

    /**
     * @return array{disk: string, path: string, mime: string, size_bytes: int}
     */
    public static function readRawFile(StepContext $context): array
    {
        /** @var array{disk: string, path: string, mime: string, size_bytes: int} $payload */
        $payload = $context->artifact('raw_file')->json();

        return $payload;
    }

    public static function subject(StepContext $context): Receipt
    {
        $subject = $context->subject();

        if (!$subject instanceof Receipt) {
            throw new \LogicException('This step can only run on a Receipt subject.');
        }

        return $subject;
    }

    public static function documentArtifactKey(StepContext $context): string
    {
        return $context->artifactOrNull('extracted_bill') !== null
            ? 'extracted_bill'
            : 'extracted_receipt';
    }

    public static function readReceipt(StepContext $context): ExtractedReceipt
    {
        /** @var array<string, mixed> $payload */
        $payload = $context->artifact('extracted_receipt')->json()['payload'] ?? [];

        return DocumentMapper::toReceipt($payload);
    }

    public static function readBill(StepContext $context): ExtractedBill
    {
        /** @var array<string, mixed> $payload */
        $payload = $context->artifact('extracted_bill')->json()['payload'] ?? [];

        return DocumentMapper::toBill($payload);
    }
}
