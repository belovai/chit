<?php

use App\Providers\AppServiceProvider;
use App\Providers\HorizonServiceProvider;
use Modules\User\Providers\UserModuleServiceProvider;

return [
    AppServiceProvider::class,
    HorizonServiceProvider::class,

    UserModuleServiceProvider::class,
];
