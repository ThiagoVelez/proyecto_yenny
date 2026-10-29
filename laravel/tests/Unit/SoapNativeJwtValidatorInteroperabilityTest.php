<?php

namespace Tests\Unit;

use App\Services\SoapJwtService;
use Firebase\JWT\ExpiredException;
use Tests\TestCase;

/**
 * Pruebas de interoperabilidad: Token generado en Laravel vs Validador SOAP nativo.
 */
class SoapNativeJwtValidatorInteroperabilityTest extends TestCase
{
    protected string $sharedSecret = 'clave_compartida_laravel_y_soap_987654321!';

    protected function setUp(): void
    {
        parent::setUp();
        if (file_exists(__DIR__ . '/../../../token/jwt_validator.php')) {
            require_once __DIR__ . '/../../../token/jwt_validator.php';
        }
        config(['services.soap.jwt_secret' => $this->sharedSecret]);
    }

    /**
     * Valida que el token emitido por SoapJwtService sea decodificado exitosamente
     * por la función validate_soap_jwt_token utilizada en el servidor SOAP nativo.
     */
    public function test_native_soap_validator_accepts_laravel_generated_token(): void
    {
        $jwtService = new SoapJwtService();
        $token = $jwtService->generateToken([
            'sub'       => '12',
            'user_name' => 'admin_sistema',
            'rol'       => 'ADMIN',
        ]);

        $decoded = validate_soap_jwt_token($this->sharedSecret, $token);

        $this->assertIsObject($decoded);
        $this->assertEquals('admin_sistema', $decoded->user_name);
        $this->assertEquals('ADMIN', $decoded->rol);
        $this->assertEquals('12', $decoded->sub);
    }

    /**
     * Valida que el validador SOAP rechace tokens cuando no se suministra cabecera ni token.
     */
    public function test_native_soap_validator_rejects_missing_token(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Token JWT no proporcionado o cabecera ausente.');

        validate_soap_jwt_token($this->sharedSecret, '');
    }

    /**
     * Valida que el validador SOAP rechace tokens expirados emitidos por Laravel.
     */
    public function test_native_soap_validator_rejects_expired_laravel_token(): void
    {
        $jwtService = new SoapJwtService();
        $past = time() - 3600;
        $token = $jwtService->generateToken([
            'iat' => $past - 120,
            'exp' => $past,
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('El token JWT ha expirado.');

        validate_soap_jwt_token($this->sharedSecret, $token);
    }
}
