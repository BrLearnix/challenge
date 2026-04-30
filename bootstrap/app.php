<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
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
        $exceptions->shouldRenderJsonWhen(function (Request $request, Throwable $e): bool {
            return $request->is('api/*') || $request->expectsJson();
        });

        $exceptions->render(function (MethodNotAllowedHttpException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $headers = $e->getHeaders();
            $allowHeader = $headers['Allow'] ?? '';
            $allowedMethods = $allowHeader !== ''
                ? array_values(array_filter(array_map(trim(...), explode(',', $allowHeader))))
                : [];

            return response()->json([
                'error' => 'method_not_allowed',
                'message' => 'El método HTTP no está permitido para esta ruta.',
                'allowed_methods' => $allowedMethods,
                'hint' => 'Para crear un pago envía POST a /api/v1/payments con cabecera Content-Type: application/json y un cuerpo JSON válido.',
            ], 405, $headers);
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'error' => 'not_found',
                'message' => 'No existe una ruta de API para esta URL.',
                'path' => '/'.$request->path(),
                'hint' => 'Ejemplo: POST /api/v1/payments para crear una operación.',
            ], 404);
        });
    })->create();
