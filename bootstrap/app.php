<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Modules\Pipeline\Exceptions\RunNotAwaitingManualException;
use Modules\Pipeline\Exceptions\RunNotRetryableException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        $exceptions->render(function (RunNotRetryableException $e) {
            return response()->json([
                'message' => 'pipeline.run_not_retryable',
                'data' => [],
                'status' => 409,
            ], 409);
        });

        $exceptions->render(function (RunNotAwaitingManualException $e) {
            return response()->json([
                'message' => 'pipeline.run_not_awaiting_manual',
                'data' => [],
                'status' => 409,
            ], 409);
        });
    })->create();
