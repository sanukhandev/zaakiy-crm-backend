<?php

use App\Http\Middleware\AuthMiddleware;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api:__DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'auth.api' => AuthMiddleware::class,
            'auth.custom' => AuthMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (ValidationException $e, $request) {
            if (!$request->is('api/*')) {
                return null;
            }

            return response()->json(
                [
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $e->errors(),
                ],
                422,
            );
        });

        $exceptions->render(function (AuthenticationException $e, $request) {
            if (!$request->is('api/*')) {
                return null;
            }

            return response()->json(
                [
                    'success' => false,
                    'message' => 'Unauthorized',
                    'errors' => [
                        'auth' => [$e->getMessage()],
                    ],
                ],
                401,
            );
        });

        $exceptions->render(function (NotFoundHttpException $e, $request) {
            if (!$request->is('api/*')) {
                return null;
            }

            return response()->json(
                [
                    'success' => false,
                    'message' => 'Resource not found',
                    'errors' => [
                        'route' => [$e->getMessage()],
                    ],
                ],
                404,
            );
        });

        $exceptions->render(function (\Throwable $e, $request) {
            if (!$request->is('api/*')) {
                return null;
            }

            Log::error('Unhandled API exception', [
                'message' => $e->getMessage(),
                'path' => $request->path(),
            ]);

            return response()->json(
                [
                    'success' => false,
                    'message' => 'Server error',
                    'errors' => config('app.debug')
                        ? ['exception' => [$e->getMessage()]]
                        : new stdClass(),
                ],
                500,
            );
        });
    })->create();
