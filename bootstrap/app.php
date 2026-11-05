<?php

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(web: __DIR__.'/../routes/web.php', api: __DIR__.'/../routes/api.php', apiPrefix: 'api/v1', commands: __DIR__.'/../routes/console.php', health: '/up')
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->convertEmptyStringsToNull(except: [fn (Request $request): bool => $request->is('api/*')]);
        $middleware->trimStrings(except: ['password', 'password_confirmation', 'currentPassword', 'newPassword', 'confirmPassword']);
        $middleware->alias(['role' => RoleMiddleware::class, 'permission' => PermissionMiddleware::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(fn (Request $request): bool => $request->is('api/*') || $request->expectsJson());
        $exceptions->render(function (Throwable $error, Request $request): ?JsonResponse {
            if (! $request->is('api/*')) {
                return null;
            }
            $status = match (true) {
                $error instanceof ValidationException => 400,
                $error instanceof UniqueConstraintViolationException => 409,
                $error instanceof AuthenticationException => 401,
                $error instanceof AuthorizationException => 403,
                $error instanceof HttpExceptionInterface => $error->getStatusCode(),
                default => 500
            };
            $message = match (true) {
                $error instanceof UniqueConstraintViolationException => ['A record with these unique fields already exists.'],
                $error instanceof ValidationException => array_merge(...array_values($error->errors())),
                $error instanceof HttpExceptionInterface && ! empty($error->getMessage()) => [$error->getMessage()],
                $status === 503 => ['Service temporarily unavailable.'],
                $status >= 500 => ['An unexpected error occurred.'],
                default => [$error->getMessage() ?: Response::$statusTexts[$status] ?? 'Unknown error']
            };
            $headers = $error instanceof HttpExceptionInterface ? $error->getHeaders() : [];

            return response()->json([
                'success' => false,
                'statusCode' => $status,
                'message' => $message,
                'timestamp' => now()->utc()->toIso8601String(),
            ], $status, $headers);
        });
    })->create();
