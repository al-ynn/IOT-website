<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use App\Exceptions\FeatureNotAvailableException;
use App\Exceptions\BillingLimitExceededException;
use App\Http\Middleware\EnsurePlatformAdmin;
use App\Http\Middleware\EnsureAccountActive;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->statefulApi();
        $middleware->alias(['admin' => EnsurePlatformAdmin::class,'active'=>EnsureAccountActive::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        $exceptions->render(
            function (
                FeatureNotAvailableException $exception,
                Request $request
            ) {
                if ($request->is('api/*') || $request->expectsJson()) {
                    return response()->json([
                        'message' => $exception->getMessage(),
                        'code' => 'FEATURE_NOT_AVAILABLE',
                    ], 403);
                }

                return null;
            }
        );
        $exceptions->render(function (BillingLimitExceededException $exception, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(['message'=>$exception->getMessage(),'code'=>'BILLING_LIMIT_REACHED','resource'=>$exception->resource,'limit'=>$exception->limit,'currentUsage'=>$exception->current], 403);
            }
            return null;
        });
    })->create();
