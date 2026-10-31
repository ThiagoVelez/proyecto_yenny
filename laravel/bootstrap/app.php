<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // PENDIENTE DE ACTIVACION: descomentar junto con las clases de app/Http/Middleware.
        // $middleware->api(prepend: [
        //     \App\Http\Middleware\ForzarRespuestaJson::class,
        //     \App\Http\Middleware\RegistrarPeticionApi::class,
        // ]);
        //
        // $middleware->alias([
        //     'capacidad.token' => \App\Http\Middleware\VerificarCapacidadToken::class,
        // ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
