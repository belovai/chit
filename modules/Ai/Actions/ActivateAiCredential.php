<?php

declare(strict_types=1);

namespace Modules\Ai\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Ai\Models\AiCredential;

final class ActivateAiCredential
{
    /**
     * Clearing the old row and setting the new one happen in one transaction:
     * the partial unique index would otherwise reject the intermediate state
     * where two rows are active.
     */
    public function handle(AiCredential $credential): AiCredential
    {
        return DB::transaction(function () use ($credential): AiCredential {
            AiCredential::query()
                ->forUser($credential->owner_id)
                ->active()
                ->whereKeyNot($credential->getKey())
                ->update(['is_active' => false]);

            $credential->update(['is_active' => true]);

            return $credential->refresh();
        });
    }
}
