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
        $middleware->api(prepend: [
            \App\Http\Middleware\ForzarRespuestaJson::class,
        ]);

        $middleware->alias([
            'IsAdministrator'      => \App\Http\Middleware\IsAdministrator::class,
            'CanManageCharges'     => \App\Http\Middleware\CanManageCharges::class,
            'CanProcessPayments'   => \App\Http\Middleware\CanProcessPayments::class,
            'CanViewRentals'       => \App\Http\Middleware\CanViewRentals::class,
            'is.admin'             => \App\Http\Middleware\IsAdministrator::class,
            'can.manage.charges'   => \App\Http\Middleware\CanManageCharges::class,
            'can.process.payments' => \App\Http\Middleware\CanProcessPayments::class,
            'can.view.rentals'     => \App\Http\Middleware\CanViewRentals::class,
            'registrar.peticion'   => \App\Http\Middleware\RegistrarPeticionApi::class,
            'capacidad.token'      => \App\Http\Middleware\VerificarCapacidadToken::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
