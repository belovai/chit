<?php

use App\Providers\AppServiceProvider;
use App\Providers\HorizonServiceProvider;
use Modules\Auth\Providers\AuthModuleServiceProvider;
use Modules\Merchant\Providers\MerchantModuleServiceProvider;
use Modules\Product\Providers\ProductModuleServiceProvider;
use Modules\Transaction\Providers\TransactionModuleServiceProvider;
use Modules\User\Providers\UserModuleServiceProvider;

return [
    AppServiceProvider::class,
    HorizonServiceProvider::class,

    UserModuleServiceProvider::class,
    AuthModuleServiceProvider::class,
    MerchantModuleServiceProvider::class,
    ProductModuleServiceProvider::class,
    TransactionModuleServiceProvider::class,
];
