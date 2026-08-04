<?php

declare(strict_types=1);

namespace Modules\Receipt\Controllers;

use App\Traits\ApiResponses;
use Illuminate\Http\JsonResponse;
use Modules\Receipt\Actions\ReviewReceipt;
use Modules\Receipt\Models\Receipt;
use Modules\Receipt\Requests\ReviewReceiptRequest;
use Modules\Receipt\Resources\ReceiptDetailResource;

final class ReviewReceiptController
{
    use ApiResponses;

    public function __invoke(
        Receipt $receipt,
        ReviewReceiptRequest $request,
        ReviewReceipt $reviewReceipt,
    ): JsonResponse {
        /** @var array{decision: string, values?: array<string, mixed>, note?: string|null} $validated */
        $validated = $request->validated();

        $receipt = $validated['decision'] === 'approve'
            ? $reviewReceipt->approve($receipt, $validated['values'] ?? [])
            : $reviewReceipt->reject($receipt, $validated['note'] ?? null);

        return $this->ok(data: ReceiptDetailResource::make($receipt->load(['currentRun', 'transaction'])));
    }
}
