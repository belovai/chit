<?php

declare(strict_types=1);

namespace Modules\Ai\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Ai\Enums\CredentialStatus;
use Modules\Ai\Models\AiCredential;

final class DeleteAiCredential
{
    public function __construct(private readonly ActivateAiCredential $activate) {}

    /**
     * Deleting the active credential would otherwise leave the user with keys
     * on file and no AI, which reads as a bug from their side.
     */
    public function handle(AiCredential $credential): void
    {
        DB::transaction(function () use ($credential): void {
            $wasActive = $credential->is_active;
            $userId = $credential->owner_id;

            $credential->delete();

            if (!$wasActive) {
                return;
            }

            $replacement = AiCredential::query()
                ->forUser($userId)
                ->where('status', CredentialStatus::Verified)
                ->orderByDesc('last_verified_at')
                ->first();

            if ($replacement !== null) {
                $this->activate->handle($replacement);
            }
        });
    }
}
