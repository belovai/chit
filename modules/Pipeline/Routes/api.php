<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Pipeline\Controllers\CancelPipelineRunController;
use Modules\Pipeline\Controllers\PipelineRunArtifactController;
use Modules\Pipeline\Controllers\PipelineRunController;
use Modules\Pipeline\Controllers\PipelineRunStepAttemptsController;
use Modules\Pipeline\Controllers\RetryPipelineRunController;

Route::middleware(['api', 'auth:sanctum'])
    ->prefix('api')
    ->name('api.')
    ->group(function () {
        Route::get('pipeline-runs', [PipelineRunController::class, 'index']);
        Route::get('pipeline-runs/{pipelineRun}', [PipelineRunController::class, 'show']);
        Route::get('pipeline-runs/{pipelineRun}/steps/{stepKey}/attempts', PipelineRunStepAttemptsController::class);
        Route::get('pipeline-runs/{pipelineRun}/artifacts/{key}', PipelineRunArtifactController::class);
        Route::post('pipeline-runs/{pipelineRun}/retry', RetryPipelineRunController::class);
        Route::post('pipeline-runs/{pipelineRun}/cancel', CancelPipelineRunController::class);
    });
