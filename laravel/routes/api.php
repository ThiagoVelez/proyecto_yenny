<?php

use App\Http\Controllers\Api\CobroController;
use App\Http\Controllers\Api\PagoController;
use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - Autenticacion Publica
|--------------------------------------------------------------------------
|
| Endpoint publico para validar credenciales y generar tokens.
|
*/

Route::post('/login', [AuthController::class, 'login'])->name('auth.login');

/*
|--------------------------------------------------------------------------
| API Routes Protegidas (Exigen Token Bearer y Roles por Middleware)
|--------------------------------------------------------------------------
|
| Todas las rutas (excepto login) exigen token Bearer (auth:sanctum).
| Se asignan los middlewares correspondientes segun el perfil y permisos:
| - IsAdministrator (Admin: todos los metodos)
| - CanManageCharges (Operador de Recepcion: generarCobro, consultarCobro, consultarCobrosPorAlquiler)
| - CanProcessPayments (Cajero: registrarPago, consultarCobro, consultarPagosPorCobro)
| - CanViewRentals (Cliente: consultarCobro, consultarCobrosPorAlquiler, consultarPagosPorCobro)
|
*/

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me'])->name('auth.me');
    Route::post('/logout', [AuthController::class, 'logout'])->name('auth.logout');

    // Endpoints de Cobros
    Route::prefix('cobros')->group(function () {
        // POST /api/cobros: generarCobro (Admin y Operador de Recepcion)
        Route::post('/', [CobroController::class, 'store'])
            ->middleware('CanManageCharges')
            ->name('cobros.store');

        // GET /api/cobros/{id}: consultarCobro (Accesible por todos los roles autenticados)
        Route::get('/{id}', [CobroController::class, 'show'])
            ->whereNumber('id')
            ->name('cobros.show');

        // GET /api/cobros/alquiler/{idAlquiler}: consultarCobrosPorAlquiler (Admin, Operador, Cliente)
        Route::get('/alquiler/{idAlquiler}', [CobroController::class, 'byAlquiler'])
            ->whereNumber('idAlquiler')
            ->middleware('CanViewRentals')
            ->name('cobros.byAlquiler');
    });

    // Endpoints de Pagos
    Route::prefix('pagos')->group(function () {
        // POST /api/pagos: registrarPago (Admin y Cajero)
        Route::post('/', [PagoController::class, 'store'])
            ->middleware('CanProcessPayments')
            ->name('pagos.store');

        // GET /api/pagos/cobro/{idCobro}: consultarPagosPorCobro (Admin, Cajero, Cliente)
        Route::get('/cobro/{idCobro}', [PagoController::class, 'byCobro'])
            ->whereNumber('idCobro')
            ->middleware('CanProcessPayments')
            ->name('pagos.byCobro');
    });
});
