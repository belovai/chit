<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Modules\Pipeline\Exceptions\RunNotAwaitingManualException;
use Modules\Pipeline\Exceptions\RunNotRetryableException;
use Modules\Receipt\Exceptions\ReceiptNotAwaitingReviewException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // This app is API-only — there is no web "login" route to redirect
        // guests to. Left at the framework default, an unauthenticated
        // request without an explicit `Accept: application/json` header
        // (e.g. a plain `post()` in tests carrying an UploadedFile, which
        // can't go through `postJson()`) crashes with a RouteNotFoundException
        // instead of the 401 JSON response `shouldRenderJsonWhen` intends.
        $middleware->redirectGuestsTo(fn () => null);
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

        $exceptions->render(fn (ReceiptNotAwaitingReviewException $e) => response()->json([
            'message' => 'receipts.not_awaiting_review',
            'data' => [],
            'status' => 409,
        ], 409));
    })->create();
