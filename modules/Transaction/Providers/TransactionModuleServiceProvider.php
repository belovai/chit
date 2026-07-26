<?php

declare(strict_types=1);

namespace Modules\Transaction\Providers;

use Illuminate\Support\ServiceProvider;

final class TransactionModuleServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->loadRoutesFrom(__DIR__.'/../Routes/api.php');
    }
}
