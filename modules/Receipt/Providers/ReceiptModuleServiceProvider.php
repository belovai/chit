<?php

declare(strict_types=1);

namespace Modules\Receipt\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Modules\Pipeline\Events\ArtifactPublished;
use Modules\Pipeline\Events\RunStatusChanged;
use Modules\Pipeline\Models\PipelineRun;
use Modules\Pipeline\Registries\PipelineRegistry;
use Modules\Pipeline\Registries\StepRegistry;
use Modules\Receipt\Listeners\DeleteOwnerFilesOnPurge;
use Modules\Receipt\Listeners\ProjectReceiptCurrentRun;
use Modules\Receipt\Listeners\ProjectReceiptFields;
use Modules\Receipt\Listeners\ProjectReceiptStatus;
use Modules\Receipt\Pipelines\ReceiptIngestPipeline;
use Modules\Receipt\Steps\AnomalyCheckStep;
use Modules\Receipt\Steps\ClassifyDocumentStep;
use Modules\Receipt\Steps\CreateTransactionStep;
use Modules\Receipt\Steps\DedupeContentStep;
use Modules\Receipt\Steps\DedupeFileHashStep;
use Modules\Receipt\Steps\ExtractReceiptStep;
use Modules\Receipt\Steps\ExtractUtilityBillStep;
use Modules\Receipt\Steps\LinkSeriesStep;
use Modules\Receipt\Steps\MatchLocationStep;
use Modules\Receipt\Steps\MatchMerchantStep;
use Modules\Receipt\Steps\MatchProductsStep;
use Modules\Receipt\Steps\MatchProviderStep;
use Modules\Receipt\Steps\OcrStep;
use Modules\Receipt\Steps\PreprocessImageStep;
use Modules\Receipt\Steps\ReviewGateStep;
use Modules\Receipt\Steps\StoreFileStep;
use Modules\Receipt\Steps\ValidateTotalsStep;
use Modules\User\Events\UserPurging;

final class ReceiptModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../Config/receipt.php', 'receipt');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->loadRoutesFrom(__DIR__.'/../Routes/api.php');

        Event::listen(RunStatusChanged::class, ProjectReceiptStatus::class);
        Event::listen(ArtifactPublished::class, ProjectReceiptFields::class);
        // A run's own `created` model event, not a Pipeline-module event — this
        // stays entirely inside the Receipt module and keeps the Pipeline
        // module unaware that Receipt exists.
        Event::listen('eloquent.created: '.PipelineRun::class, ProjectReceiptCurrentRun::class);
        Event::listen(UserPurging::class, DeleteOwnerFilesOnPurge::class);

        $steps = $this->app->make(StepRegistry::class);

        foreach ([
            StoreFileStep::class,
            DedupeFileHashStep::class,
            PreprocessImageStep::class,
            OcrStep::class,
            ClassifyDocumentStep::class,
            ExtractReceiptStep::class,
            MatchMerchantStep::class,
            MatchLocationStep::class,
            MatchProductsStep::class,
            ExtractUtilityBillStep::class,
            MatchProviderStep::class,
            LinkSeriesStep::class,
            AnomalyCheckStep::class,
            ValidateTotalsStep::class,
            DedupeContentStep::class,
        ] as $stepClass) {
            $steps->register($stepClass);
        }

        $steps->register(ReviewGateStep::class);
        $steps->register(CreateTransactionStep::class);

        $this->app->make(PipelineRegistry::class)->register(new ReceiptIngestPipeline);
    }
}
