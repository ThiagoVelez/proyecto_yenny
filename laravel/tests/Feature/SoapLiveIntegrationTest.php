<?php

namespace Tests\Feature;

use App\Models\Cobro;
use App\Services\SoapAlquilerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SoapLiveIntegrationTest extends TestCase
{
    use RefreshDatabase;
    /**
     * Test de integración en vivo:
     * Verifica que el microservicio Laravel se comunica efectivamente
     * con el servidor SOAP real en 127.0.0.1:8000/server.php.
     */
    public function test_consumo_real_de_alquiler_desde_servidor_soap(): void
    {
        $soapService = app(SoapAlquilerService::class);
        $alquiler = $soapService->consultarAlquiler(1);

        $this->assertIsArray($alquiler);
        $this->assertEquals(1, $alquiler['id']);
        $this->assertEquals('BIC-001', $alquiler['bicicleta_codigo']);
        $this->assertEquals('Finalizado', $alquiler['estado']);
        $this->assertEquals('Carlos Pérez', $alquiler['cliente_nombre']);
    }

    /**
     * Test de endpoint POST /api/cobros con el servidor SOAP real en vivo.
     */
    public function test_endpoint_post_cobros_con_soap_real(): void
    {
        // Limpiar cobros previos del alquiler 1 para asegurar prueba limpia
        Cobro::where('idAlquiler', 1)->delete();

        $response = $this->postJson('/api/cobros', [
            'idAlquiler'     => 1,
            'montoPenalidad' => 2500.00,
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'idAlquiler'     => 1,
                    'montoPenalidad' => 2500.00,
                    'estado'         => 'PENDIENTE',
                    'detalleAlquilerSoap' => [
                        'bicicleta_codigo' => 'BIC-001',
                        'cliente_nombre'   => 'Carlos Pérez',
                    ],
                ],
            ]);

        $this->assertDatabaseHas('cobros', [
            'idAlquiler'     => 1,
            'montoPenalidad' => 2500.00,
            'estado'         => 'PENDIENTE',
        ]);
    }
}
