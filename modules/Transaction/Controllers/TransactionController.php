<?php

declare(strict_types=1);

namespace Modules\Transaction\Controllers;

use App\Traits\ApiResponses;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Merchant\Models\Merchant;
use Modules\Merchant\Models\MerchantLocation;
use Modules\Product\Models\Product;
use Modules\Transaction\Actions\CreateTransaction;
use Modules\Transaction\Actions\DestroyTransaction;
use Modules\Transaction\Actions\UpdateTransaction;
use Modules\Transaction\Models\Transaction;
use Modules\Transaction\Requests\CreateTransactionRequest;
use Modules\Transaction\Requests\UpdateTransactionRequest;
use Modules\Transaction\Resources\TransactionResource;
use Modules\User\Models\User;

final class TransactionController
{
    use ApiResponses;

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return $this->ok(
            data: TransactionResource::collection(
                Transaction::query()
                    ->where('owner_id', $user->id)
                    ->with(['merchant', 'location', 'items.product'])
                    ->latest('occurred_at')
                    ->paginate(),
            ),
        );
    }

    public function show(Transaction $transaction): JsonResponse
    {
        $transaction->load(['merchant', 'location', 'items.product']);

        return $this->ok(
            data: TransactionResource::make($transaction),
        );
    }

    public function store(
        CreateTransactionRequest $request,
        CreateTransaction $createTransaction,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        $transaction = $createTransaction->handle(
            ownerId: $user->id,
            validated: $this->resolveIds($validated),
        );

        return $this->created(
            data: TransactionResource::make($transaction),
        );
    }

    public function destroy(
        Transaction $transaction,
        DestroyTransaction $destroyTransaction,
    ): JsonResponse {
        $destroyTransaction->handle($transaction);

        return $this->ok();
    }

    public function update(
        Transaction $transaction,
        UpdateTransactionRequest $request,
        UpdateTransaction $updateTransaction,
    ): JsonResponse {
        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        $transaction = $updateTransaction->handle($transaction, $this->resolveIds($validated));

        return $this->ok(
            data: TransactionResource::make($transaction),
        );
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{merchant_id: int, location_id: int|null, currency: string, source: string, payment_method: string, discount_amount: float|null, total_amount: float, occurred_at: string, items: list<array{product_id: int|null, description: string, quantity: float, unit: string|null, unit_price: float}>}
     */
    private function resolveIds(array $validated): array
    {
        /** @var string $merchantHashId */
        $merchantHashId = $validated['merchant_id'];

        /** @var string|null $locationHashId */
        $locationHashId = $validated['location_id'];

        /** @var list<array{product_id: string|null, description: string, quantity: float, unit: string|null, unit_price: float}> $items */
        $items = $validated['items'];

        return [
            'merchant_id' => (int) Merchant::query()->where('hash_id', $merchantHashId)->value('id'),
            'location_id' => $locationHashId !== null
                ? (int) MerchantLocation::query()->where('hash_id', $locationHashId)->value('id')
                : null,
            'currency' => (string) $validated['currency'],
            'source' => (string) $validated['source'],
            'payment_method' => (string) $validated['payment_method'],
            'discount_amount' => $validated['discount_amount'] !== null ? (float) $validated['discount_amount'] : null,
            'total_amount' => (float) $validated['total_amount'],
            'occurred_at' => (string) $validated['occurred_at'],
            'items' => array_map(function (array $item): array {
                return [
                    'product_id' => $item['product_id'] !== null
                        ? (int) Product::query()->where('hash_id', $item['product_id'])->value('id')
                        : null,
                    'description' => (string) $item['description'],
                    'quantity' => (float) $item['quantity'],
                    'unit' => $item['unit'] !== null ? (string) $item['unit'] : null,
                    'unit_price' => (float) $item['unit_price'],
                ];
            }, $items),
        ];
    }
}
