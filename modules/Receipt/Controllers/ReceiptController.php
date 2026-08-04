<?php

declare(strict_types=1);

namespace Modules\Receipt\Controllers;

use App\Traits\ApiResponses;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Extraction\Enums\DocumentType;
use Modules\Receipt\Actions\UploadReceipt;
use Modules\Receipt\Models\Receipt;
use Modules\Receipt\Requests\UploadReceiptRequest;
use Modules\Receipt\Resources\ReceiptDetailResource;
use Modules\Receipt\Resources\ReceiptResource;
use Modules\User\Models\User;

final class ReceiptController
{
    use ApiResponses;

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return $this->ok(
            data: ReceiptResource::collection(
                Receipt::query()
                    ->where('owner_id', $user->id)
                    ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
                    ->when($request->query('doc_type'), fn ($q, $t) => $q->where('doc_type', $t))
                    ->with(['currentRun', 'transaction'])
                    ->orderByDesc('created_at')
                    ->paginate(),
            ),
        );
    }

    public function show(Receipt $receipt): JsonResponse
    {
        return $this->ok(data: ReceiptDetailResource::make($receipt->load(['currentRun', 'transaction'])));
    }

    public function store(UploadReceiptRequest $request, UploadReceipt $uploadReceipt): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $hint = $request->validated('doc_type_hint');

        $receipt = $uploadReceipt->handle(
            ownerId: $user->id,
            file: $request->file('file'),
            hint: is_string($hint) ? DocumentType::from($hint) : null,
        );

        return $this->response(
            message: 'success',
            data: ReceiptDetailResource::make($receipt->load(['currentRun', 'transaction'])),
            statusCode: 202,
        );
    }
}
