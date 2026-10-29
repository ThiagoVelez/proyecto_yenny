<?php

namespace Tests\Unit\Repositories;

use App\Models\Cobro;
use App\Models\PagoCobro;
use App\Repositories\Contracts\PagoCobroRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pruebas unitarias para PagoCobroRepository.
 */
class PagoCobroRepositoryTest extends TestCase
{
    use RefreshDatabase;

    protected PagoCobroRepositoryInterface $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = app(PagoCobroRepositoryInterface::class);
    }

    /**
     * Valida el registro de un nuevo pago mediante el repositorio.
     */
    public function test_registrar_pago_exitoso(): void
    {
        $cobro = Cobro::create([
            'idAlquiler'     => 201,
            'montoBase'      => 25000.00,
            'montoPenalidad' => 0.00,
            'montoTotal'     => 25000.00,
            'estado'         => 'PENDIENTE',
            'fechaEmision'   => now(),
        ]);

        $pago = $this->repository->registrarPago($cobro->id, 'TRANSFERENCIA', 15000.00);

        $this->assertInstanceOf(PagoCobro::class, $pago);
        $this->assertEquals($cobro->id, $pago->idCobro);
        $this->assertEquals('TRANSFERENCIA', $pago->metodoPago);
        $this->assertEquals(15000.00, $pago->montoPagado);
        $this->assertDatabaseHas('pago_cobros', ['id' => $pago->id, 'idCobro' => $cobro->id]);
    }

    /**
     * Valida el calculo acumulado de pagos para un cobro.
     */
    public function test_sum_pagos_by_cobro(): void
    {
        $cobro = Cobro::create([
            'idAlquiler'     => 202,
            'montoBase'      => 50000.00,
            'montoPenalidad' => 0.00,
            'montoTotal'     => 50000.00,
            'estado'         => 'PENDIENTE',
            'fechaEmision'   => now(),
        ]);

        $this->repository->registrarPago($cobro->id, 'EFECTIVO', 20000.00);
        $this->repository->registrarPago($cobro->id, 'TARJETA', 10000.00);

        $total = $this->repository->sumPagosByCobro($cobro->id);
        $this->assertEquals(30000.00, $total);
    }

    /**
     * Valida la obtencion de la lista de pagos de un cobro.
     */
    public function test_get_by_cobro(): void
    {
        $cobro = Cobro::create([
            'idAlquiler'     => 203,
            'montoBase'      => 40000.00,
            'montoPenalidad' => 0.00,
            'montoTotal'     => 40000.00,
            'estado'         => 'PENDIENTE',
            'fechaEmision'   => now(),
        ]);

        $this->repository->registrarPago($cobro->id, 'EFECTIVO', 15000.00);
        $this->repository->registrarPago($cobro->id, 'TARJETA', 25000.00);

        $pagos = $this->repository->getByCobro($cobro->id);
        $this->assertCount(2, $pagos);
    }
}
