<?php

declare(strict_types=1);

namespace Modules\Pipeline\Console\Commands;

use Illuminate\Console\Command;
use Modules\Pipeline\Actions\StartRun;
use Modules\User\Models\User;

final class RunDemoPipeline extends Command
{
    protected $signature = 'pipeline:demo {--user= : email of the owner} {--pass : let the gate open automatically}';

    protected $description = 'Start a demo pipeline run so the run UI has something to show';

    public function handle(StartRun $startRun): int
    {
        $email = $this->option('user');

        $user = $email === null
            ? User::query()->orderBy('id')->first()
            : User::query()->where('email', $email)->first();

        if ($user === null) {
            $this->error('No such user.');

            return self::FAILURE;
        }

        $run = $startRun->handle('demo', $user->id, config: [
            'demo_gate' => ['auto_pass' => (bool) $this->option('pass')],
        ]);

        $this->info("Started demo run {$run->hash_id} for {$user->email}.");

        return self::SUCCESS;
    }
}
