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
use Modules\Product\Models\Product;
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
                'location_id' => $this->resolveLocation($context, $document, $merchant, $overrides),
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
                'items' => $this->items($context, $document, $isBill, $overrides),
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
        if (isset($overrides['merchant_hash_id']) && is_string($overrides['merchant_hash_id']) && $overrides['merchant_hash_id'] !== '') {
            return Merchant::query()
                ->where('owner_id', $context->ownerId())
                ->where('hash_id', $overrides['merchant_hash_id'])
                ->first();
        }

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

        $name = trim($name);

        // The reviewer can type a name that already exists — the review screen
        // resolves an exact match to a selection, but nothing stops a client
        // from posting the name instead. Exact and case-insensitive on purpose:
        // a fuzzy match here would silently merge two genuinely different shops.
        $existing = Merchant::query()
            ->where('owner_id', $context->ownerId())
            ->whereRaw('lower(name) = ?', [mb_strtolower($name)])
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return $this->createMerchant->handle($context->ownerId(), ['name' => $name]);
    }

    /**
     * The reviewer's decision is read in four layers: an explicit pick (which
     * may be an explicit "none"), then a typed address, then whatever the
     * matching step accepted, and finally the address read off the picture.
     * `array_key_exists` rather than `??`, because clearing the field is a
     * statement, not an absence.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function resolveLocation(
        StepContext $context,
        ExtractedReceipt|ExtractedBill $document,
        Merchant $merchant,
        array $overrides,
    ): ?int {
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

        $typed = is_string($overrides['location_address'] ?? null) ? trim($overrides['location_address']) : '';

        if ($typed !== '') {
            return $this->locationFor($merchant, $typed);
        }

        $acceptedId = $context->artifactOrNull('location_candidate')?->json()['accepted_id'] ?? null;

        if (is_numeric($acceptedId)) {
            return (int) $acceptedId;
        }

        // The printed address is evidence about the shop printed on the receipt.
        // Picking a different merchant by hand replaces that identity, and the
        // review screen offers the address as a one-click branch right there —
        // so silently filing one shop's address as another shop's branch is a
        // guess the reviewer already declined to make.
        if (isset($overrides['merchant_id']) || isset($overrides['merchant_hash_id'])) {
            return null;
        }

        // Nothing matched and the reviewer typed nothing: the branch is new,
        // and the only address we have is the extracted one. Mirrors how a new
        // merchant is created above — approval is what writes the row, so a
        // first receipt from a branch still lands on a real location.
        $extracted = $document instanceof ExtractedReceipt ? trim((string) $document->merchantAddress) : '';

        return $extracted === '' ? null : $this->locationFor($merchant, $extracted);
    }

    /**
     * The same branch may be spelled differently ("Szilleri sgt." vs "Szilléri
     * sugár út"); the normalized key is what decides whether this is the same
     * row. An address that normalizes to nothing ("---") has no such key, so
     * every receipt carrying it would add another row that can never match —
     * no location at all is the honest answer there.
     */
    private function locationFor(Merchant $merchant, string $address): ?int
    {
        $normalized = AddressNormalizer::normalize($address);

        if ($normalized === null) {
            return null;
        }

        $existing = MerchantLocation::query()
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

    /**
     * @param  array<string, mixed>  $overrides
     * @return list<array{product_id: int|null, description: string, quantity: float, unit: string|null, unit_price: float}>
     */
    private function items(StepContext $context, ExtractedReceipt|ExtractedBill $document, bool $isBill, array $overrides): array
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

        // The reviewer's per-item pick, keyed by item_index, wins over the
        // auto-match — same rule as every other field on this document.
        $itemOverrides = [];
        foreach ((array) ($overrides['items'] ?? []) as $entry) {
            if (is_array($entry) && isset($entry['item_index']) && is_numeric($entry['item_index'])) {
                $itemOverrides[(int) $entry['item_index']] = $entry;
            }
        }

        $items = [];

        foreach ($document->items as $index => $item) {
            // A negative line is a discount the model misfiled as an item —
            // never matched or created as a product, same rule the review
            // screen enforces by hiding the picker for these rows.
            if ($item->effectiveTotalMinor() < 0) {
                continue;
            }

            $override = $itemOverrides[$index] ?? null;

            if ($override !== null) {
                $productId = $this->overrideProductId($context->ownerId(), $override);
            } else {
                $productId = $matches[$index]['accepted_id'] ?? null;
            }

            if ($productId === null) {
                // A new product is only created here, on approval — the matching
                // step deliberately proposes without writing, mirroring how a
                // new merchant is resolved above. There is no gate finding for
                // an unmatched product: config's `receipt.gate.severity` has no
                // product-related key, so this never blocks for human review.
                $productName = is_string($override['product_name'] ?? null) && trim($override['product_name']) !== ''
                    ? $override['product_name']
                    : $item->description;

                $productId = $this->createProduct->handle($context->ownerId(), ['name' => $productName])->id;
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

    /**
     * The review screen resolves a product the same way it resolves a merchant:
     * by hash id, because the suggest endpoint it types against exposes no
     * numeric ids. The pipeline's own candidates carry `product_id`, so both
     * forms are read here — a hash id that matches nothing belongs to another
     * owner and falls through to "create it on approval", never to their row.
     *
     * @param  array<string, mixed>  $override
     */
    private function overrideProductId(int $ownerId, array $override): ?int
    {
        $hashId = $override['product_hash_id'] ?? null;

        if (is_string($hashId) && $hashId !== '') {
            /** @var int|null $id */
            $id = Product::query()
                ->where('owner_id', $ownerId)
                ->where('hash_id', $hashId)
                ->value('id');

            return $id;
        }

        return isset($override['product_id']) && is_numeric($override['product_id'])
            ? (int) $override['product_id']
            : null;
    }
}
