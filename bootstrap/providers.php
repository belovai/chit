<?php

use App\Providers\AppServiceProvider;
use App\Providers\HorizonServiceProvider;
use Modules\Auth\Providers\AuthModuleServiceProvider;
use Modules\Merchant\Providers\MerchantModuleServiceProvider;
use Modules\User\Providers\UserModuleServiceProvider;

return [
    AppServiceProvider::class,
    HorizonServiceProvider::class,

    UserModuleServiceProvider::class,
    AuthModuleServiceProvider::class,
    MerchantModuleServiceProvider::class,
];
