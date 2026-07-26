<?php

declare(strict_types=1);

namespace Modules\Product\Providers;

use Illuminate\Support\ServiceProvider;

final class ProductModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../Config/product.php', 'product');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->loadRoutesFrom(__DIR__.'/../Routes/api.php');
    }
}
