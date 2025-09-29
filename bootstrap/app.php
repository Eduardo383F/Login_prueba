<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;

return Application::configure(basePath: dirname(__DIR__))
    ->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        // tus otros alias...
        //'abilities' => \Laravel\Sanctum\Http\Middleware\CheckAbilities::class,
        //'ability'   => \Laravel\Sanctum\Http\Middleware\CheckForAnyAbility::class,
        ]);
    })
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // CORS para API
        $middleware->appendToGroup('api', \Illuminate\Http\Middleware\HandleCors::class);

        // Aliases de Spatie
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // 422 Validación
        $exceptions->render(function (ValidationException $e, $request) {
            return ApiResponse::error(
                'Datos inválidos.',
                ['errors' => $e->errors()],
                422
            );
        });

        // 401 No autenticado
        $exceptions->render(function (AuthenticationException $e, $request) {
            return ApiResponse::error('No autenticado.', ['http_code' => 401], 401);
        });

        // 403 Prohibido (sin permisos)
        $exceptions->render(function (AuthorizationException $e, $request) {
            return ApiResponse::error('Acceso denegado.', ['http_code' => 403], 403);
        });

        // 404 Modelo o ruta no encontrada
        $exceptions->render(function (ModelNotFoundException|NotFoundHttpException $e, $request) {
            return ApiResponse::error('Recurso no encontrado.', ['http_code' => 404], 404);
        });

        // 405 Método no permitido
        $exceptions->render(function (MethodNotAllowedHttpException $e, $request) {
            return ApiResponse::error('Método no permitido.', ['http_code' => 405], 405);
        });

        // 429 Demasiadas solicitudes
        $exceptions->render(function (ThrottleRequestsException $e, $request) {
            return ApiResponse::error('Demasiadas solicitudes. Intenta más tarde.', ['http_code' => 429], 429);
        });

        // HttpException genérica
        $exceptions->render(function (HttpException $e, $request) {
            $code = $e->getStatusCode();
            return ApiResponse::error(
                $e->getMessage() ?: 'Error HTTP.',
                ['http_code' => $code],
                $code
            );
        });

        // 500 y errores no controlados
        $exceptions->render(function (\Throwable $e, $request) {
            $msg = app()->hasDebugModeEnabled()
                ? $e->getMessage()
                : 'Error interno del servidor.';

            return ApiResponse::error($msg, ['http_code' => 500], 500);
        });
    })->create();

    
