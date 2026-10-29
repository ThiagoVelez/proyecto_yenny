<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        User::create([
            'name' => 'Estudiante Demo',
            'email' => 'estudiante@example.test',
            'password' => 'ClaveDemo123!',
        ]);
    }
    public function test_login_con_credenciales_invalidas_retorna_401(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => 'noexiste@example.test',
            'password' => 'clave_invalida',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'Credenciales incorrectas',
            ]);
    }

    /**
     * Valida que el login correcto emita un token Bearer con la habilidad configurada.
     */
    public function test_login_exitoso_retorna_token_bearer(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => 'estudiante@example.test',
            'password' => 'ClaveDemo123!',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'token_type',
                'access_token',
            ])
            ->assertJson([
                'token_type' => 'Bearer',
            ]);
    }

    /**
     * Valida que el endpoint /api/me retorne los datos del usuario autenticado.
     */
    public function test_me_retorna_usuario_autenticado(): void
    {
        $loginResponse = $this->postJson('/api/login', [
            'email' => 'estudiante@example.test',
            'password' => 'ClaveDemo123!',
        ]);

        $token = $loginResponse->json('access_token');

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/me');

        $response->assertStatus(200)
            ->assertJson([
                'email' => 'estudiante@example.test',
            ]);
    }

    /**
     * Valida que el logout revoque el token y bloquee solicitudes subsiguientes.
     */
    public function test_logout_revoca_token_actual(): void
    {
        $loginResponse = $this->postJson('/api/login', [
            'email' => 'estudiante@example.test',
            'password' => 'ClaveDemo123!',
        ]);

        $token = $loginResponse->json('access_token');

        $logoutResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/logout');

        $logoutResponse->assertStatus(200)
            ->assertJson([
                'message' => 'Sesion cerrada',
            ]);

        // Limpiar el guard en memoria para verificar que el token revocado ya no autentica
        $this->app['auth']->forgetGuards();

        $subsequentResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/me');

        $subsequentResponse->assertStatus(401);
    }
}
