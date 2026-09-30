<?php

use App\Http\Middleware\AddHstsHeader;
use App\Http\Middleware\EnsurePasswordIsChanged;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: 'api/v1',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
        $middleware->append(AddHstsHeader::class);
        $middleware->alias([
            'password.changed' => EnsurePasswordIsChanged::class,
            'permission' => PermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson()
        );

        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            if ($e instanceof ValidationException) {
                return ApiResponse::error($e->errors(), $e->status);
            }

            if ($e instanceof AuthenticationException) {
                return ApiResponse::error(['auth' => ['Unauthenticated.']], 401);
            }

            if ($e instanceof AuthorizationException) {
                return ApiResponse::error([
                    'auth' => [$e->getMessage() !== '' ? $e->getMessage() : 'This action is unauthorized.'],
                ], 403);
            }

            if ($e instanceof ModelNotFoundException || $e instanceof NotFoundHttpException) {
                return ApiResponse::error(['resource' => ['Record not found.']], 404);
            }

            if ($e instanceof HttpException) {
                $message = $e->getMessage() !== '' ? $e->getMessage() : 'Request failed.';

                return ApiResponse::error(['request' => [$message]], $e->getStatusCode());
            }

            return null;
        });
    })->create();
