<?php

namespace Tests\Feature;

use App\Http\Middleware\CanManageCharges;
use App\Http\Middleware\CanProcessPayments;
use App\Http\Middleware\CanViewRentals;
use App\Http\Middleware\IsAdministrator;
use App\Models\Cobro;
use App\Models\PagoCobro;
use App\Models\User;
use App\Services\SoapAlquilerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Pruebas integrales de validacion de Token Bearer (401) y Middlewares de Roles (403):
 * - Perfil Administrador (IsAdministrator)
 * - Perfil Operador de Recepcion (CanManageCharges)
 * - Perfil Cajero (CanProcessPayments)
 * - Perfil Cliente (CanViewRentals)
 */
class RoleMiddlewareSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $operadorUser;
    protected User $cajeroUser;
    protected User $clienteUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::factory()->create([
            'email' => 'admin_test@example.test',
            'role'  => 'admin',
        ]);

        $this->operadorUser = User::factory()->create([
            'email' => 'operador_test@example.test',
            'role'  => 'operador_recepcion',
        ]);

        $this->cajeroUser = User::factory()->create([
            'email' => 'cajero_test@example.test',
            'role'  => 'cajero',
        ]);

        $this->clienteUser = User::factory()->create([
            'email' => 'cliente_test@example.test',
            'role'  => 'cliente',
        ]);
    }

    /**
     * Requerimiento 1: Rutas protegidas sin Token Bearer retornan 401 Unauthorized.
     */
    public function test_rutas_protegidas_sin_token_retornan_401(): void
    {
        $this->postJson('/api/cobros', ['idAlquiler' => 1])
            ->assertStatus(401);

        $this->getJson('/api/cobros/1')
            ->assertStatus(401);

        $this->getJson('/api/cobros/alquiler/1')
            ->assertStatus(401);

        $this->postJson('/api/pagos', ['idCobro' => 1, 'montoPagado' => 1000, 'metodoPago' => 'EFECTIVO'])
            ->assertStatus(401);

        $this->getJson('/api/pagos/cobro/1')
            ->assertStatus(401);
    }

    /**
     * Requerimiento 2: IsAdministrator permite a Admin y rechaza a otros con 403.
     */
    public function test_is_administrator_middleware(): void
    {
        $middleware = new IsAdministrator();

        // 1. Admin pasa
        $requestAdmin = Request::create('/api/admin-only', 'GET');
        $requestAdmin->setUserResolver(fn () => $this->adminUser);
        $responseAdmin = $middleware->handle($requestAdmin, fn () => response()->json(['ok' => true]));
        $this->assertEquals(200, $responseAdmin->getStatusCode());

        // 2. Operador bloqueado con 403
        $requestOperador = Request::create('/api/admin-only', 'GET');
        $requestOperador->setUserResolver(fn () => $this->operadorUser);
        $responseOperador = $middleware->handle($requestOperador, fn () => response()->json(['ok' => true]));
        $this->assertEquals(403, $responseOperador->getStatusCode());
        $this->assertEquals(
            ['error' => 'Permisos insuficientes para ejecutar este método'],
            json_decode($responseOperador->getContent(), true)
        );

        // 3. Cajero bloqueado con 403
        $requestCajero = Request::create('/api/admin-only', 'GET');
        $requestCajero->setUserResolver(fn () => $this->cajeroUser);
        $responseCajero = $middleware->handle($requestCajero, fn () => response()->json(['ok' => true]));
        $this->assertEquals(403, $responseCajero->getStatusCode());
        $this->assertEquals(
            ['error' => 'Permisos insuficientes para ejecutar este método'],
            json_decode($responseCajero->getContent(), true)
        );

        // 4. Cliente bloqueado con 403
        $requestCliente = Request::create('/api/admin-only', 'GET');
        $requestCliente->setUserResolver(fn () => $this->clienteUser);
        $responseCliente = $middleware->handle($requestCliente, fn () => response()->json(['ok' => true]));
        $this->assertEquals(403, $responseCliente->getStatusCode());
        $this->assertEquals(
            ['error' => 'Permisos insuficientes para ejecutar este método'],
            json_decode($responseCliente->getContent(), true)
        );
    }

    /**
     * Requerimiento 3: Operador de Recepcion puede generarCobro y consultarCobro.
     */
    public function test_operador_puede_generar_cobro(): void
    {
        $this->mock(SoapAlquilerService::class, function (MockInterface $mock) {
            $mock->shouldReceive('consultarAlquiler')
                ->once()
                ->andReturn([
                    'id'                => 101,
                    'bicicleta_codigo'  => 'BIC-001',
                    'bicicleta_tipo'    => 'Urbana',
                    'bicicleta_tarifa'  => 10000.00,
                    'cliente_documento' => '12345678',
                    'cliente_nombre'    => 'Juan Perez',
                    'fecha_inicio'      => '2026-11-10 10:00:00',
                    'fecha_fin'         => '2026-11-10 12:00:00',
                    'total'             => 20000.00,
                    'estado'            => 'Finalizado',
                ]);

            $mock->shouldReceive('calcularTarifas')
                ->once()
                ->andReturn([
                    'montoBase'      => 20000.00,
                    'montoPenalidad' => 0.00,
                    'montoTotal'     => 20000.00,
                    'horas'          => 2,
                ]);
        });

        $response = $this->actingAs($this->operadorUser)
            ->postJson('/api/cobros', [
                'idAlquiler' => 101,
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'idAlquiler' => 101,
                ],
            ]);
    }

    /**
     * Requerimiento 4: Cajero no tiene permiso de generarCobro (bloqueado antes de SOAP).
     */
    public function test_cajero_bloqueado_en_generar_cobro_retorna_403(): void
    {
        // No se debe invocar el servicio SOAP; el middleware aborta antes
        $this->mock(SoapAlquilerService::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('consultarAlquiler');
        });

        $response = $this->actingAs($this->cajeroUser)
            ->postJson('/api/cobros', [
                'idAlquiler' => 102,
            ]);

        $response->assertStatus(403)
            ->assertJson([
                'error' => 'Permisos insuficientes para ejecutar este método',
            ]);
    }

    /**
     * Requerimiento 5: Cliente no tiene permiso de generarCobro.
     */
    public function test_cliente_bloqueado_en_generar_cobro_retorna_403(): void
    {
        $this->mock(SoapAlquilerService::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('consultarAlquiler');
        });

        $response = $this->actingAs($this->clienteUser)
            ->postJson('/api/cobros', [
                'idAlquiler' => 103,
            ]);

        $response->assertStatus(403)
            ->assertJson([
                'error' => 'Permisos insuficientes para ejecutar este método',
            ]);
    }

    /**
     * Requerimiento 6: Cajero puede registrarPago en CanProcessPayments.
     */
    public function test_cajero_puede_registrar_pago(): void
    {
        $cobro = Cobro::create([
            'idAlquiler'     => 501,
            'montoBase'      => 30000.00,
            'montoPenalidad' => 0.00,
            'montoTotal'     => 30000.00,
            'estado'         => 'PENDIENTE',
            'fechaEmision'   => now(),
        ]);

        $response = $this->actingAs($this->cajeroUser)
            ->postJson('/api/pagos', [
                'idCobro'     => $cobro->id,
                'metodoPago'  => 'TARJETA',
                'montoPagado' => 15000.00,
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'idCobro'     => $cobro->id,
                    'montoPagado' => 15000.00,
                ],
            ]);
    }

    /**
     * Requerimiento 7: Operador de Recepcion NO puede registrarPago (retorna 403).
     */
    public function test_operador_bloqueado_en_registrar_pago_retorna_403(): void
    {
        $cobro = Cobro::create([
            'idAlquiler'     => 502,
            'montoBase'      => 30000.00,
            'montoPenalidad' => 0.00,
            'montoTotal'     => 30000.00,
            'estado'         => 'PENDIENTE',
            'fechaEmision'   => now(),
        ]);

        $response = $this->actingAs($this->operadorUser)
            ->postJson('/api/pagos', [
                'idCobro'     => $cobro->id,
                'metodoPago'  => 'EFECTIVO',
                'montoPagado' => 10000.00,
            ]);

        $response->assertStatus(403)
            ->assertJson([
                'error' => 'Permisos insuficientes para ejecutar este método',
            ]);
    }

    /**
     * Requerimiento 8: Cliente NO puede registrarPago (retorna 403).
     */
    public function test_cliente_bloqueado_en_registrar_pago_retorna_403(): void
    {
        $cobro = Cobro::create([
            'idAlquiler'     => 503,
            'montoBase'      => 30000.00,
            'montoPenalidad' => 0.00,
            'montoTotal'     => 30000.00,
            'estado'         => 'PENDIENTE',
            'fechaEmision'   => now(),
        ]);

        $response = $this->actingAs($this->clienteUser)
            ->postJson('/api/pagos', [
                'idCobro'     => $cobro->id,
                'metodoPago'  => 'TRANSFERENCIA',
                'montoPagado' => 10000.00,
            ]);

        $response->assertStatus(403)
            ->assertJson([
                'error' => 'Permisos insuficientes para ejecutar este método',
            ]);
    }

    /**
     * Requerimiento 9: Cajero NO puede consultarCobrosPorAlquiler (retorna 403).
     */
    public function test_cajero_bloqueado_en_consultar_cobros_por_alquiler_retorna_403(): void
    {
        $response = $this->actingAs($this->cajeroUser)
            ->getJson('/api/cobros/alquiler/1');

        $response->assertStatus(403)
            ->assertJson([
                'error' => 'Permisos insuficientes para ejecutar este método',
            ]);
    }

    /**
     * Requerimiento 10: Cliente y Operador pueden consultarCobrosPorAlquiler (200 OK).
     */
    public function test_cliente_y_operador_pueden_consultar_cobros_por_alquiler(): void
    {
        $cobro = Cobro::create([
            'idAlquiler'     => 701,
            'montoBase'      => 20000.00,
            'montoPenalidad' => 0.00,
            'montoTotal'     => 20000.00,
            'estado'         => 'PENDIENTE',
            'fechaEmision'   => now(),
        ]);

        // Cliente consulta
        $this->actingAs($this->clienteUser)
            ->getJson('/api/cobros/alquiler/701')
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        // Operador consulta
        $this->actingAs($this->operadorUser)
            ->getJson('/api/cobros/alquiler/701')
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);
    }

    /**
     * Requerimiento 11: Todos los perfiles autenticados pueden consultarCobro por ID.
     */
    public function test_todos_los_perfiles_pueden_consultar_cobro_por_id(): void
    {
        $cobro = Cobro::create([
            'idAlquiler'     => 801,
            'montoBase'      => 15000.00,
            'montoPenalidad' => 0.00,
            'montoTotal'     => 15000.00,
            'estado'         => 'PENDIENTE',
            'fechaEmision'   => now(),
        ]);

        $this->actingAs($this->adminUser)->getJson("/api/cobros/{$cobro->id}")->assertStatus(200);
        $this->actingAs($this->operadorUser)->getJson("/api/cobros/{$cobro->id}")->assertStatus(200);
        $this->actingAs($this->cajeroUser)->getJson("/api/cobros/{$cobro->id}")->assertStatus(200);
        $this->actingAs($this->clienteUser)->getJson("/api/cobros/{$cobro->id}")->assertStatus(200);
    }
}

