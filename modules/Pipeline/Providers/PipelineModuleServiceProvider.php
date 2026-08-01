<?php

declare(strict_types=1);

namespace Modules\Pipeline\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Pipeline\Console\Commands\ExpireStaleRuns;
use Modules\Pipeline\Console\Commands\PruneArtifacts;
use Modules\Pipeline\Console\Commands\RunDemoPipeline;
use Modules\Pipeline\Demo\DemoPipeline;
use Modules\Pipeline\Demo\Steps\DemoClassifyStep;
use Modules\Pipeline\Demo\Steps\DemoCommitStep;
use Modules\Pipeline\Demo\Steps\DemoExtractStep;
use Modules\Pipeline\Demo\Steps\DemoGateStep;
use Modules\Pipeline\Demo\Steps\DemoIngestStep;
use Modules\Pipeline\Demo\Steps\DemoReadStep;
use Modules\Pipeline\Registries\PipelineRegistry;
use Modules\Pipeline\Registries\StepRegistry;

final class PipelineModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../Config/pipeline.php', 'pipeline');

        $this->app->singleton(StepRegistry::class);
        $this->app->singleton(PipelineRegistry::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->loadRoutesFrom(__DIR__.'/../Routes/api.php');

        if (config('pipeline.demo.enabled') === true) {
            $steps = $this->app->make(StepRegistry::class);

            foreach ([
                DemoIngestStep::class,
                DemoReadStep::class,
                DemoClassifyStep::class,
                DemoExtractStep::class,
                DemoGateStep::class,
                DemoCommitStep::class,
            ] as $stepClass) {
                $steps->register($stepClass);
            }

            $this->app->make(PipelineRegistry::class)->register(new DemoPipeline);
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                ExpireStaleRuns::class,
                PruneArtifacts::class,
                RunDemoPipeline::class,
            ]);
        }
    }
}
