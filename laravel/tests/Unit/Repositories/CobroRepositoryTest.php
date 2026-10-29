<?php

namespace Tests\Unit\Repositories;

use App\Models\Cobro;
use App\Models\PagoCobro;
use App\Repositories\Contracts\CobroRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pruebas unitarias para CobroRepository.
 */
class CobroRepositoryTest extends TestCase
{
    use RefreshDatabase;

    protected CobroRepositoryInterface $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = app(CobroRepositoryInterface::class);
    }

    /**
     * Valida que el repositorio cree un cobro y lo persista en la base de datos.
     */
    public function test_create_and_find_cobro(): void
    {
        $cobro = $this->repository->create([
            'idAlquiler'     => 101,
            'montoBase'      => 20000.00,
            'montoPenalidad' => 0.00,
            'montoTotal'     => 20000.00,
            'estado'         => 'PENDIENTE',
            'fechaEmision'   => now(),
        ]);

        $this->assertInstanceOf(Cobro::class, $cobro);
        $this->assertDatabaseHas('cobros', ['id' => $cobro->id, 'idAlquiler' => 101]);

        $found = $this->repository->findById($cobro->id);
        $this->assertNotNull($found);
        $this->assertEquals(101, $found->idAlquiler);
    }

    /**
     * Valida que findActiveByAlquiler retorne el ultimo cobro activo.
     */
    public function test_find_active_by_alquiler(): void
    {
        $this->repository->create([
            'idAlquiler'     => 102,
            'montoBase'      => 15000.00,
            'montoPenalidad' => 0.00,
            'montoTotal'     => 15000.00,
            'estado'         => 'CANCELADO',
            'fechaEmision'   => now()->subDays(2),
        ]);

        $activo = $this->repository->create([
            'idAlquiler'     => 102,
            'montoBase'      => 18000.00,
            'montoPenalidad' => 2000.00,
            'montoTotal'     => 20000.00,
            'estado'         => 'PENDIENTE',
            'fechaEmision'   => now(),
        ]);

        $result = $this->repository->findActiveByAlquiler(102);
        $this->assertNotNull($result);
        $this->assertEquals($activo->id, $result->id);
    }

    /**
     * Valida la carga de cobro junto con su relacion de pagos.
     */
    public function test_find_with_pagos(): void
    {
        $cobro = $this->repository->create([
            'idAlquiler'     => 103,
            'montoBase'      => 30000.00,
            'montoPenalidad' => 0.00,
            'montoTotal'     => 30000.00,
            'estado'         => 'PENDIENTE',
            'fechaEmision'   => now(),
        ]);

        PagoCobro::create([
            'idCobro'     => $cobro->id,
            'fechaPago'   => now(),
            'metodoPago'  => 'TARJETA',
            'montoPagado' => 10000.00,
        ]);

        $loaded = $this->repository->findWithPagos($cobro->id);
        $this->assertNotNull($loaded);
        $this->assertTrue($loaded->relationLoaded('pagos'));
        $this->assertCount(1, $loaded->pagos);
    }

    /**
     * Valida la actualizacion de estado del cobro.
     */
    public function test_update_estado(): void
    {
        $cobro = $this->repository->create([
            'idAlquiler'     => 104,
            'montoBase'      => 12000.00,
            'montoPenalidad' => 0.00,
            'montoTotal'     => 12000.00,
            'estado'         => 'PENDIENTE',
            'fechaEmision'   => now(),
        ]);

        $updated = $this->repository->updateEstado($cobro->id, 'PAGADO');
        $this->assertTrue($updated);
        $this->assertDatabaseHas('cobros', ['id' => $cobro->id, 'estado' => 'PAGADO']);
    }
}
