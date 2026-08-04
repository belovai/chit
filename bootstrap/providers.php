<?php

use App\Providers\AppServiceProvider;
use App\Providers\HorizonServiceProvider;
use Modules\Auth\Providers\AuthModuleServiceProvider;
use Modules\Extraction\Providers\ExtractionModuleServiceProvider;
use Modules\Merchant\Providers\MerchantModuleServiceProvider;
use Modules\Pipeline\Providers\PipelineModuleServiceProvider;
use Modules\Product\Providers\ProductModuleServiceProvider;
use Modules\Receipt\Providers\ReceiptModuleServiceProvider;
use Modules\Transaction\Providers\TransactionModuleServiceProvider;
use Modules\User\Providers\UserModuleServiceProvider;

return [
    AppServiceProvider::class,
    HorizonServiceProvider::class,

    UserModuleServiceProvider::class,
    AuthModuleServiceProvider::class,
    MerchantModuleServiceProvider::class,
    ProductModuleServiceProvider::class,
    PipelineModuleServiceProvider::class,
    TransactionModuleServiceProvider::class,
    ExtractionModuleServiceProvider::class,
    ReceiptModuleServiceProvider::class,
];
