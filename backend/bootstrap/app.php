<?php

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // 管理画面で存在しない予約 ID を開いたとき（Laravel の英語の文章ではなく、日本語で返す）
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/admin/*') && $e->getPrevious() instanceof ModelNotFoundException) {
                return response()->json(['message' => '予約が見つかりません。'], 404);
            }
        });
    })->create();
