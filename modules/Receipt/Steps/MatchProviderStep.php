<?php

declare(strict_types=1);

namespace Modules\Receipt\Steps;

use Modules\Merchant\Actions\SuggestMerchantCandidates;
use Modules\Pipeline\Contracts\PipelineStep;
use Modules\Pipeline\ValueObjects\StepContext;
use Modules\Pipeline\ValueObjects\StepResult;
use Modules\Receipt\Services\ArtifactCodec;

/**
 * Same matching as match_merchant — a utility company is a merchant — plus one
 * extra job: projecting the series key that link_series looks up.
 *
 * The series key is emitted as an artifact rather than written to the receipt
 * directly — ProjectReceiptFields is the single writer of that column, same
 * discipline as doc_type from classify_document.
 */
final class MatchProviderStep implements PipelineStep
{
    public function __construct(private readonly SuggestMerchantCandidates $suggest) {}

    public static function key(): string
    {
        return 'match_provider';
    }

    public static function queue(): string
    {
        return (string) config('pipeline.queues.default');
    }

    public function handle(StepContext $context): StepResult
    {
        $bill = ArtifactCodec::readBill($context);

        return MerchantMatching::resolve(
            suggest: $this->suggest,
            ownerId: $context->ownerId(),
            rawName: $bill->providerName,
        )->artifact('series_key', ['value' => self::seriesKey($bill->providerName, $bill->customerReference)]);
    }

    public static function seriesKey(?string $provider, ?string $customerReference): ?string
    {
        if ($provider === null || $customerReference === null) {
            return null;
        }

        $digitsOnly = preg_replace('/\D+/', '', $customerReference);

        if ($digitsOnly === null || $digitsOnly === '') {
            return null;
        }

        return sha1(mb_strtoupper(trim($provider)).'|'.$digitsOnly);
    }
}
