<?php

declare(strict_types=1);

namespace Modules\Auth\Controllers;

use App\Traits\ApiResponses;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Receipt\Enums\ReceiptStatus;
use Modules\Receipt\Models\Receipt;
use Modules\User\Models\User;

final class HeartbeatController
{
    use ApiResponses;

    public function __invoke(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return $this->ok(data: [
            'receipts_needs_review' => Receipt::query()
                ->where('owner_id', $user->id)
                ->where('status', ReceiptStatus::NeedsReview)
                ->count(),
        ]);
    }
}
