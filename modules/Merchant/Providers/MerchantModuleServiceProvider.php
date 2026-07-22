<?php

declare(strict_types=1);

namespace Modules\Merchant\Providers;

use Illuminate\Support\ServiceProvider;

final class MerchantModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../Config/merchant.php', 'merchant');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->loadRoutesFrom(__DIR__.'/../Routes/api.php');
    }
}
