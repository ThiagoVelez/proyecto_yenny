<?php

namespace Tests\Unit;

use App\Services\SoapJwtService;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Pruebas unitarias para el servicio de generación y validación de tokens JWT simétricos (HS256).
 */
class SoapJwtServiceTest extends TestCase
{
    protected SoapJwtService $jwtService;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.soap.jwt_secret' => 'clave_de_prueba_unitaria_1234567890!']);
        $this->jwtService = new SoapJwtService();
    }

    /**
     * Verifica que generateToken retorne un token JWT estructurado en 3 segmentos Base64Url.
     */
    public function test_generate_token_returns_valid_jwt_structure(): void
    {
        $token = $this->jwtService->generateToken();

        $this->assertIsString($token);
        $segments = explode('.', $token);
        $this->assertCount(3, $segments, 'El token JWT debe contener cabecera, payload y firma.');

        $decoded = $this->jwtService->decodeToken($token);
        $this->assertEquals(config('app.url'), $decoded->iss);
        $this->assertEquals('servidor-soap', $decoded->aud);
        $this->assertGreaterThan(0, $decoded->iat);
        $this->assertGreaterThan($decoded->iat, $decoded->exp);
    }

    /**
     * Verifica que los claims adicionales se fusionen correctamente en el payload.
     */
    public function test_generate_token_merges_additional_claims(): void
    {
        $additional = [
            'sub'       => 'usuario_corte2',
            'user_name' => 'admin_test',
            'rol'       => 'ADMIN',
            'permisos'  => 'TOTALES',
        ];

        $token = $this->jwtService->generateToken($additional);
        $decoded = $this->jwtService->decodeToken($token);

        $this->assertEquals('usuario_corte2', $decoded->sub);
        $this->assertEquals('admin_test', $decoded->user_name);
        $this->assertEquals('ADMIN', $decoded->rol);
        $this->assertEquals('TOTALES', $decoded->permisos);
    }

    /**
     * Verifica que la decodificación falle con SignatureInvalidException si la clave simétrica no coincide.
     */
    public function test_decode_token_fails_with_different_secret_key(): void
    {
        $token = $this->jwtService->generateToken(['sub' => 'prueba']);

        // Cambiar la clave en la configuración para simular discrepancia de clave simétrica
        config(['services.soap.jwt_secret' => 'otra_clave_distinta_incompatible_999!']);

        $this->expectException(SignatureInvalidException::class);
        $this->jwtService->decodeToken($token);
    }

    /**
     * Verifica que la decodificación lance ExpiredException ante un token cuya expiración ya ocurrió.
     */
    public function test_decode_token_fails_when_expired(): void
    {
        // Generar token con expiración en el pasado
        $pastTime = time() - 3600;
        $token = $this->jwtService->generateToken([
            'iat' => $pastTime - 60,
            'exp' => $pastTime,
        ]);

        $this->expectException(ExpiredException::class);
        $this->jwtService->decodeToken($token);
    }

    /**
     * Verifica que generateToken lance InvalidArgumentException si la clave simétrica está vacía.
     */
    public function test_generate_token_throws_when_secret_key_is_empty(): void
    {
        config(['services.soap.jwt_secret' => '']);
        putenv('SOAP_JWT_SECRET=');

        $this->expectException(InvalidArgumentException::class);
        $this->jwtService->generateToken();
    }

    /**
     * Verifica que generateToken lance InvalidArgumentException si la clave tiene menos de 32 caracteres.
     */
    public function test_generate_token_throws_when_secret_key_is_too_short(): void
    {
        config(['services.soap.jwt_secret' => 'clave_demasiado_corta']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('La clave secreta simétrica para HS256 debe tener al menos 32 caracteres (256 bits).');
        $this->jwtService->generateToken();
    }
}
