<?php

declare(strict_types=1);

namespace Modules\User\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Modules\User\Events\UserPurging;
use Modules\User\Models\User;

/**
 * Account deletion is two steps: the request soft-deletes immediately (the
 * user is logged out), and the actual cleanup runs here, in the background —
 * with many receipt/artifact files this can take seconds.
 */
final class PurgeUserData implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly int $userId) {}

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping((string) $this->userId))->releaseAfter(30)->expireAfter(600)];
    }

    public function handle(): void
    {
        $user = User::withTrashed()->find($this->userId);

        if ($user === null) {
            return;
        }

        // Files first, while the rows still exist: after cleanup there'd be
        // nothing left to read the disk/path pairs from.
        Event::dispatch(new UserPurging($user->id));

        /** @var list<string> $tables */
        $tables = config('user.purge_tables', []);

        DB::transaction(function () use ($user, $tables): void {
            // The tables also reference each other with RESTRICT, so we can't
            // trust it all to the `users` cascade — see config/user.purge_tables.
            foreach ($tables as $table) {
                DB::table($table)->where('owner_id', $user->id)->delete();
            }

            $user->forceDelete();
        });
    }
}
