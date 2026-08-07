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
 * A fiók törlése két lépés: a kérés azonnal soft delete-el (a felhasználó
 * kilép), a tényleges takarítás pedig itt, háttérben fut — sok receipt/artifact
 * fájlnál ez másodpercekig is tarthat.
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

        // Előbb a fájlok, amíg a sorok még megvannak: a takarítás után már nem
        // lenne miből kiolvasni a disk/path párokat.
        Event::dispatch(new UserPurging($user->id));

        /** @var list<string> $tables */
        $tables = config('user.purge_tables', []);

        DB::transaction(function () use ($user, $tables): void {
            // A táblák egymásra is hivatkoznak RESTRICT-tel, ezért nem bízhatjuk
            // az egészet a `users` cascade-jére — lásd config/user.purge_tables.
            foreach ($tables as $table) {
                DB::table($table)->where('owner_id', $user->id)->delete();
            }

            $user->forceDelete();
        });
    }
}
