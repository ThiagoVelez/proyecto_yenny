<?php

namespace Tests\Feature;

use App\Models\Cobro;
use App\Models\PagoCobro;
use App\Services\SoapAlquilerService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class CobrosPagosApiTest extends TestCase
{
    use RefreshDatabase;
    /**
     * Test POST /api/cobros genera un cobro consumiendo el servicio SOAP.
     */
    public function test_generar_cobro_exitoso_con_servicio_soap(): void
    {
        $idAlquilerFicticio = 9991;

        // Mock del servicio SOAP para aislar la prueba unitaria del estado del servidor HTTP SOAP
        $this->mock(SoapAlquilerService::class, function (MockInterface $mock) use ($idAlquilerFicticio) {
            $mock->shouldReceive('consultarAlquiler')
                ->with($idAlquilerFicticio)
                ->once()
                ->andReturn([
                    'id'                => $idAlquilerFicticio,
                    'bicicleta_codigo'  => 'BIC-999',
                    'bicicleta_tipo'    => 'Montaña',
                    'bicicleta_tarifa'  => 15000.00,
                    'cliente_documento' => '1001234567',
                    'cliente_nombre'    => 'Cliente Prueba',
                    'fecha_inicio'      => '2026-10-06 10:00:00',
                    'fecha_fin'         => '2026-10-06 12:00:00',
                    'total'             => 30000.00,
                    'estado'            => 'Finalizado',
                ]);

            $mock->shouldReceive('calcularTarifas')
                ->once()
                ->andReturn([
                    'montoBase'      => 30000.00,
                    'montoPenalidad' => 5000.00,
                    'montoTotal'     => 35000.00,
                    'horas'          => 2,
                ]);
        });

        $response = $this->postJson('/api/cobros', [
            'idAlquiler'     => $idAlquilerFicticio,
            'montoPenalidad' => 5000.00,
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'idAlquiler'     => $idAlquilerFicticio,
                    'montoBase'      => 30000.00,
                    'montoPenalidad' => 5000.00,
                    'montoTotal'     => 35000.00,
                    'estado'         => 'PENDIENTE',
                ],
            ]);

        $this->assertDatabaseHas('cobros', [
            'idAlquiler' => $idAlquilerFicticio,
            'montoTotal' => 35000.00,
            'estado'     => 'PENDIENTE',
        ]);
    }

    /**
     * Test POST /api/cobros rechaza si el alquiler no existe en SOAP (404).
     */
    public function test_generar_cobro_falla_si_alquiler_no_existe_en_soap(): void
    {
        $idAlquilerInexistente = 88888;

        $this->mock(SoapAlquilerService::class, function (MockInterface $mock) use ($idAlquilerInexistente) {
            $mock->shouldReceive('consultarAlquiler')
                ->with($idAlquilerInexistente)
                ->once()
                ->andThrow(new Exception("El alquiler con ID $idAlquilerInexistente no existe en el sistema SOAP.", 404));
        });

        $response = $this->postJson('/api/cobros', [
            'idAlquiler' => $idAlquilerInexistente,
        ]);

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
            ]);
    }

    /**
     * Test GET /api/cobros/{id} consulta el detalle de un cobro específico.
     */
    public function test_consultar_detalle_cobro(): void
    {
        $cobro = Cobro::create([
            'idAlquiler'     => 9992,
            'montoBase'      => 20000.00,
            'montoPenalidad' => 0.00,
            'montoTotal'     => 20000.00,
            'estado'         => 'PENDIENTE',
            'fechaEmision'   => now(),
        ]);

        $response = $this->getJson("/api/cobros/{$cobro->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'id'             => $cobro->id,
                    'idAlquiler'     => 9992,
                    'montoTotal'     => 20000.00,
                    'saldoPendiente' => 20000.00,
                    'estado'         => 'PENDIENTE',
                ],
            ]);
    }

    /**
     * Test GET /api/cobros/alquiler/{idAlquiler} obtiene historial por alquiler.
     */
    public function test_consultar_cobros_por_alquiler(): void
    {
        $idAlquiler = 9993;

        Cobro::create([
            'idAlquiler'     => $idAlquiler,
            'montoBase'      => 15000.00,
            'montoPenalidad' => 0.00,
            'montoTotal'     => 15000.00,
            'estado'         => 'PENDIENTE',
            'fechaEmision'   => now(),
        ]);

        $response = $this->getJson("/api/cobros/alquiler/{$idAlquiler}");

        $response->assertStatus(200)
            ->assertJson([
                'success'    => true,
                'idAlquiler' => $idAlquiler,
            ]);

        $this->assertGreaterThanOrEqual(1, count($response->json('data')));
    }

    /**
     * Test POST /api/pagos registra abono parcial y posterior pago total.
     */
    public function test_registrar_pago_parcial_y_total_con_cambio_de_estado(): void
    {
        $cobro = Cobro::create([
            'idAlquiler'     => 9994,
            'montoBase'      => 50000.00,
            'montoPenalidad' => 10000.00,
            'montoTotal'     => 60000.00,
            'estado'         => 'PENDIENTE',
            'fechaEmision'   => now(),
        ]);

        // 1. Pago parcial: $20,000 con TARJETA
        $pago1 = $this->postJson('/api/pagos', [
            'idCobro'     => $cobro->id,
            'metodoPago'  => 'TARJETA',
            'montoPagado' => 20000.00,
        ]);

        $pago1->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'montoPagado' => 20000.00,
                    'metodoPago'  => 'TARJETA',
                    'resumenCobro' => [
                        'totalPagado'    => 20000.00,
                        'saldoPendiente' => 40000.00,
                        'estadoCobro'    => 'PENDIENTE',
                    ],
                ],
            ]);

        // 2. Pago restante: $40,000 con TRANSFERENCIA -> debe pasar a PAGADO
        $pago2 = $this->postJson('/api/pagos', [
            'idCobro'     => $cobro->id,
            'metodoPago'  => 'TRANSFERENCIA',
            'montoPagado' => 40000.00,
        ]);

        $pago2->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'montoPagado' => 40000.00,
                    'resumenCobro' => [
                        'totalPagado'    => 60000.00,
                        'saldoPendiente' => 0.00,
                        'estadoCobro'    => 'PAGADO',
                    ],
                ],
            ]);

        $this->assertDatabaseHas('cobros', [
            'id'     => $cobro->id,
            'estado' => 'PAGADO',
        ]);
    }

    /**
     * Test GET /api/pagos/cobro/{idCobro} consulta el detalle de pagos de un cobro.
     */
    public function test_consultar_pagos_por_cobro(): void
    {
        $cobro = Cobro::create([
            'idAlquiler'     => 9995,
            'montoBase'      => 10000.00,
            'montoPenalidad' => 0.00,
            'montoTotal'     => 10000.00,
            'estado'         => 'PENDIENTE',
            'fechaEmision'   => now(),
        ]);

        PagoCobro::create([
            'idCobro'     => $cobro->id,
            'fechaPago'   => now(),
            'metodoPago'  => 'EFECTIVO',
            'montoPagado' => 10000.00,
        ]);

        $cobro->update(['estado' => 'PAGADO']);

        $response = $this->getJson("/api/pagos/cobro/{$cobro->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success'     => true,
                'idCobro'     => $cobro->id,
                'montoTotal'  => 10000.00,
                'totalPagado' => 10000.00,
                'estadoCobro' => 'PAGADO',
                'totalPagos'  => 1,
            ]);
    }

    /**
     * Test validaciones: pago sobre cobro pagado o monto mayor al saldo.
     */
    public function test_rechazo_de_pago_que_supera_el_saldo(): void
    {
        $cobro = Cobro::create([
            'idAlquiler'     => 9996,
            'montoBase'      => 10000.00,
            'montoPenalidad' => 0.00,
            'montoTotal'     => 10000.00,
            'estado'         => 'PENDIENTE',
            'fechaEmision'   => now(),
        ]);

        $response = $this->postJson('/api/pagos', [
            'idCobro'     => $cobro->id,
            'metodoPago'  => 'EFECTIVO',
            'montoPagado' => 15000.00, // Supera el saldo de 10000
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);
    }
}
