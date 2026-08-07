<?php

declare(strict_types=1);

namespace Modules\Receipt\Steps;

use Illuminate\Support\Facades\DB;
use Modules\Extraction\Ai\ValueObjects\ExtractedBill;
use Modules\Extraction\Ai\ValueObjects\ExtractedReceipt;
use Modules\Merchant\Actions\CreateMerchant;
use Modules\Merchant\Actions\CreateMerchantLocation;
use Modules\Merchant\Models\Merchant;
use Modules\Merchant\Models\MerchantLocation;
use Modules\Merchant\Services\AddressNormalizer;
use Modules\Pipeline\Contracts\PipelineStep;
use Modules\Pipeline\Exceptions\StepException;
use Modules\Pipeline\ValueObjects\StepContext;
use Modules\Pipeline\ValueObjects\StepResult;
use Modules\Product\Actions\CreateProduct;
use Modules\Receipt\Services\ArtifactCodec;
use Modules\Transaction\Actions\CreateTransaction;

/**
 * The only step that writes domain rows, and the last one in the run — so a
 * failure anywhere upstream leaves no half-created transaction behind.
 *
 * The review decision, when there was one, wins over the extraction: the user
 * looked at the picture, the model did not.
 */
final class CreateTransactionStep implements PipelineStep
{
    public function __construct(
        private readonly CreateTransaction $createTransaction,
        private readonly CreateMerchant $createMerchant,
        private readonly CreateMerchantLocation $createMerchantLocation,
        private readonly CreateProduct $createProduct,
    ) {}

    public static function key(): string
    {
        return 'create_transaction';
    }

    public static function queue(): string
    {
        return (string) config('pipeline.queues.default');
    }

    public function handle(StepContext $context): StepResult
    {
        $receipt = ArtifactCodec::subject($context)->refresh();

        // Idempotency guard: a rerun of this step must not create a second
        // transaction for the same receipt.
        if ($receipt->transaction_id !== null) {
            return StepResult::skipped('This receipt already has a transaction.');
        }

        $isBill = ArtifactCodec::documentArtifactKey($context) === 'extracted_bill';
        $document = $isBill ? ArtifactCodec::readBill($context) : ArtifactCodec::readReceipt($context);
        $decision = $context->artifactOrNull('review_decision')?->json() ?? [];
        /** @var array<string, mixed> $overrides */
        $overrides = $decision['values'] ?? [];

        $merchant = $this->resolveMerchant($context, $document, $overrides);

        if ($merchant === null) {
            return StepResult::failure(StepException::permanent('No merchant could be resolved for this document.'));
        }

        $total = $overrides['total_minor'] ?? $document->totalMinor;

        if (!is_numeric($total)) {
            return StepResult::failure(StepException::permanent('No total amount to record.'));
        }

        $occurredAt = $overrides['occurred_at']
            ?? ($document instanceof ExtractedReceipt ? $document->occurredAt : $document->periodEnd)?->toDateTimeString();

        // A discount read off the picture is the single easiest thing to get
        // wrong — one deduction line counted twice moves the sum but nothing
        // else — so the reviewer's number wins here like every other field.
        // Keyed on presence, not on `??`: clearing the field means "there was
        // no discount", which must not fall back to what the model read.
        $discount = $document instanceof ExtractedReceipt
            ? (array_key_exists('discount_minor', $overrides) ? $overrides['discount_minor'] : $document->discountMinor)
            : null;

        $transaction = DB::transaction(function () use ($context, $document, $isBill, $merchant, $overrides, $total, $occurredAt, $discount) {
            return $this->createTransaction->handle($context->ownerId(), [
                'merchant_id' => $merchant->id,
                'location_id' => $this->resolveLocation($context, $merchant, $overrides),
                'currency' => (string) ($overrides['currency'] ?? $document->currency ?? 'HUF'),
                'source' => 'receipt',
                'payment_method' => $document instanceof ExtractedReceipt
                    ? (string) ($overrides['payment_method'] ?? $document->paymentMethod ?? 'card')
                    : 'bank_transfer',
                'discount_amount' => is_numeric($discount) ? ((int) $discount) / 100 : null,
                // Minor units become decimals exactly here, at the persistence
                // boundary, and nowhere else.
                'total_amount' => ((int) $total) / 100,
                'occurred_at' => (string) $occurredAt,
                'items' => $this->items($context, $document, $isBill),
            ]);
        });

        $receipt->update(['transaction_id' => $transaction->id]);

        return StepResult::success()->artifact('transaction', [
            'id' => $transaction->id,
            'hash_id' => $transaction->hash_id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function resolveMerchant(
        StepContext $context,
        ExtractedReceipt|ExtractedBill $document,
        array $overrides,
    ): ?Merchant {
        if (isset($overrides['merchant_id']) && is_numeric($overrides['merchant_id'])) {
            return Merchant::query()
                ->where('owner_id', $context->ownerId())
                ->find((int) $overrides['merchant_id']);
        }

        $candidates = $context->artifactOrNull('merchant_candidates')?->json() ?? [];

        if (isset($candidates['accepted_id']) && is_numeric($candidates['accepted_id'])) {
            return Merchant::query()
                ->where('owner_id', $context->ownerId())
                ->find((int) $candidates['accepted_id']);
        }

        // A new merchant is only created here, on approval — the matching step
        // deliberately proposes without writing.
        $name = is_string($overrides['merchant_name'] ?? null)
            ? $overrides['merchant_name']
            : ($document instanceof ExtractedReceipt ? $document->merchantName : $document->providerName);

        if ($name === null || trim($name) === '') {
            return null;
        }

        return $this->createMerchant->handle($context->ownerId(), ['name' => trim($name)]);
    }

    /**
     * The reviewer's decision is read in three layers: an explicit pick (which
     * may be an explicit "none"), then a typed address, then whatever the
     * matching step accepted. `array_key_exists` rather than `??`, because
     * clearing the field is a statement, not an absence.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function resolveLocation(StepContext $context, Merchant $merchant, array $overrides): ?int
    {
        if (array_key_exists('location_hash_id', $overrides)) {
            $hashId = $overrides['location_hash_id'];

            if (!is_string($hashId) || $hashId === '') {
                return null;
            }

            return MerchantLocation::query()
                ->where('merchant_id', $merchant->id)
                ->where('hash_id', $hashId)
                ->first()?->id;
        }

        $address = is_string($overrides['location_address'] ?? null) ? trim($overrides['location_address']) : '';

        if ($address !== '') {
            $normalized = AddressNormalizer::normalize($address);

            // The reviewer may spell a branch we already know differently
            // ("Szilleri sgt." vs "Szilléri sugár út"); the normalized key is
            // what decides whether this is the same row.
            $existing = $normalized === null ? null : MerchantLocation::query()
                ->where('merchant_id', $merchant->id)
                ->where('normalized_address', $normalized)
                ->first();

            if ($existing !== null) {
                return $existing->id;
            }

            return $this->createMerchantLocation->handle($merchant, [
                'is_online' => false,
                'address' => $address,
                'latitude' => null,
                'longitude' => null,
            ])->id;
        }

        $acceptedId = $context->artifactOrNull('location_candidate')?->json()['accepted_id'] ?? null;

        return is_numeric($acceptedId) ? (int) $acceptedId : null;
    }

    /**
     * @return list<array{product_id: int|null, description: string, quantity: float, unit: string|null, unit_price: float}>
     */
    private function items(StepContext $context, ExtractedReceipt|ExtractedBill $document, bool $isBill): array
    {
        if ($isBill) {
            /** @var ExtractedBill $document */
            return [[
                'product_id' => null,
                'description' => trim(sprintf(
                    '%s %s',
                    $document->providerName ?? 'Utility',
                    $document->consumption !== null ? "{$document->consumption} {$document->consumptionUnit}" : '',
                )),
                'quantity' => $document->consumption ?? 1.0,
                'unit' => $document->consumptionUnit,
                'unit_price' => $document->consumption !== null && $document->consumption > 0.0
                    ? round(($document->totalMinor ?? 0) / 100 / $document->consumption, 2)
                    : ($document->totalMinor ?? 0) / 100,
            ]];
        }

        /** @var ExtractedReceipt $document */
        $matches = $context->artifactOrNull('product_matches')?->json()['items'] ?? [];
        $items = [];

        foreach ($document->items as $index => $item) {
            $productId = $matches[$index]['accepted_id'] ?? null;

            if ($productId === null) {
                // A new product is only created here, on approval — the matching
                // step deliberately proposes without writing, mirroring how a
                // new merchant is resolved above. There is no gate finding for
                // an unmatched product: config's `receipt.gate.severity` has no
                // product-related key, so this never blocks for human review.
                $productId = $this->createProduct->handle($context->ownerId(), ['name' => $item->description])->id;
            }

            $items[] = [
                'product_id' => $productId,
                'description' => $item->description,
                'quantity' => $item->quantity,
                'unit' => $item->unit,
                'unit_price' => $item->unitPriceMinor / 100,
            ];
        }

        return $items;
    }
}
