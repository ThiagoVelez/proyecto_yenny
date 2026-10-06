<?php

use App\Http\Controllers\Api\CobroController;
use App\Http\Controllers\Api\PagoController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - Corte 2: Microservicio de Cobros y Penalidades
|--------------------------------------------------------------------------
|
| Endpoints RESTful para la gestión de cobros derivados de alquileres
| validados mediante el servicio SOAP, y registro de pagos asociados.
|
*/

// Endpoints de Cobros
Route::prefix('cobros')->group(function () {
    // POST /api/cobros: Generar un nuevo cobro para un alquiler (valida en SOAP)
    Route::post('/', [CobroController::class, 'store'])->name('cobros.store');

    // GET /api/cobros/{id}: Consultar el detalle de un cobro específico
    Route::get('/{id}', [CobroController::class, 'show'])->whereNumber('id')->name('cobros.show');

    // GET /api/cobros/alquiler/{idAlquiler}: Obtener cobro o historial de cobros de un alquiler
    Route::get('/alquiler/{idAlquiler}', [CobroController::class, 'byAlquiler'])->whereNumber('idAlquiler')->name('cobros.byAlquiler');
});

// Endpoints de Pagos
Route::prefix('pagos')->group(function () {
    // POST /api/pagos: Registrar el pago de un cobro existente
    Route::post('/', [PagoController::class, 'store'])->name('pagos.store');

    // GET /api/pagos/cobro/{idCobro}: Consultar pagos realizados sobre un cobro determinado
    Route::get('/cobro/{idCobro}', [PagoController::class, 'byCobro'])->whereNumber('idCobro')->name('pagos.byCobro');
});
