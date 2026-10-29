<?php

namespace App\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use InvalidArgumentException;

/**
 * Servicio encargado de la generación y decodificación de tokens JWT simétricos
 * destinados a la interoperabilidad y autenticación con el servidor SOAP.
 */
class SoapJwtService
{
    /**
     * Algoritmo simétrico estándar empleado para la firma y verificación.
     */
    protected const ALGORITHM = 'HS256';

    /**
     * Genera un token JWT firmado mediante la clave simétrica configurada.
     *
     * @param array<string, mixed> $additionalData Datos adicionales o claims a incluir en el payload
     * @return string Token JWT serializado
     * @throws InvalidArgumentException Si la clave secreta simétrica no se encuentra configurada
     */
    public function generateToken(array $additionalData = []): string
    {
        $key = (string) config('services.soap.jwt_secret', env('SOAP_JWT_SECRET', 'fallback_secret_simetrico_minimo_32_caracteres_12345'));

        if (trim($key) === '') {
            throw new InvalidArgumentException('La clave secreta simétrica SOAP JWT no está configurada.');
        }

        if (strlen($key) < 32) {
            throw new InvalidArgumentException('La clave secreta simétrica para HS256 debe tener al menos 32 caracteres (256 bits).');
        }

        $now = time();

        $payload = array_merge([
            'iss' => (string) config('app.url', 'http://127.0.0.1:8001'), // Emisor
            'aud' => 'servidor-soap',                                      // Audiencia
            'iat' => $now,                                                 // Tiempo en que se emitió el token
            'exp' => $now + 3600,                                          // Expiración por defecto (1 hora)
        ], $additionalData);

        // Generamos el token usando el algoritmo simétrico HS256
        return JWT::encode($payload, $key, self::ALGORITHM);
    }

    /**
     * Decodifica y valida un token JWT utilizando la clave simétrica compartida.
     *
     * @param string $token Token JWT en formato Bearer
     * @return object Contenido decodificado del payload
     * @throws InvalidArgumentException Si la clave secreta simétrica no se encuentra configurada
     */
    public function decodeToken(string $token): object
    {
        $key = (string) config('services.soap.jwt_secret', env('SOAP_JWT_SECRET', 'fallback_secret_simetrico_minimo_32_caracteres_12345'));

        if (trim($key) === '') {
            throw new InvalidArgumentException('La clave secreta simétrica SOAP JWT no está configurada.');
        }

        if (strlen($key) < 32) {
            throw new InvalidArgumentException('La clave secreta simétrica para HS256 debe tener al menos 32 caracteres (256 bits).');
        }

        return JWT::decode($token, new Key($key, self::ALGORITHM));
    }
}
