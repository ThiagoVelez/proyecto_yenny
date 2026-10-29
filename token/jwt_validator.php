<?php
/**
 * ==========================================================
 * PROYECTO: Servidor SOAP Modular
 * MÓDULO: Validador de Token JWT Simétrico (HS256)
 * ARCHIVO: token/jwt_validator.php
 * DESCRIPCIÓN: Valida tokens JWT firmados simétricamente con HS256
 * provenientes de peticiones HTTP (cabecera Authorization Bearer)
 * o de sobres SOAP. Compatible con SoapServer nativo de PHP y NuSOAP.
 * ==========================================================
 */

if (!class_exists('Firebase\JWT\JWT')) {
    if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
        require_once __DIR__ . '/../vendor/autoload.php';
    } elseif (file_exists(__DIR__ . '/../laravel/vendor/autoload.php')) {
        require_once __DIR__ . '/../laravel/vendor/autoload.php';
    }
}

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;

/**
 * Obtiene las cabeceras HTTP de forma compatible entre distintos entornos de servidor
 * (Apache, Nginx, PHP CLI Built-in server, FastCGI).
 *
 * @return array<string, string>
 */
function get_all_request_headers_compat() {
    $headers = [];

    if (function_exists('getallheaders')) {
        $rawHeaders = getallheaders();
        if (is_array($rawHeaders)) {
            foreach ($rawHeaders as $k => $v) {
                $headers[strtolower($k)] = $v;
            }
        }
    }

    // Compatibilidad con $_SERVER para entornos donde getallheaders() omite Authorization
    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $headers['authorization'] = $_SERVER['HTTP_AUTHORIZATION'];
    } elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $headers['authorization'] = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    }

    return $headers;
}

/**
 * Extrae el token JWT Bearer desde las cabeceras HTTP de la petición.
 *
 * @return string|null Token JWT sin el prefijo 'Bearer ', o null si no existe
 */
function extract_jwt_bearer_token() {
    $headers = get_all_request_headers_compat();
    $authHeader = $headers['authorization'] ?? '';

    if (empty($authHeader)) {
        return null;
    }

    if (preg_match('/Bearer\s(\S+)/i', $authHeader, $matches)) {
        return $matches[1];
    }

    return null;
}

/**
 * Valida un token JWT utilizando una clave secreta simétrica (HS256).
 *
 * En caso de fallo, genera un SOAP Fault estándar:
 * - Si está disponible SoapFault nativo de PHP (ext-soap), lanza SoapFault.
 * - Si se utiliza la jerarquía de excepciones del proyecto, lanza AuthenticationException.
 * - Si no está disponible ninguna, lanza una Exception estándar con código de fallo.
 *
 * @param string|null $secretKey Clave simétrica compartida. Si es null, utiliza la clave configurada.
 * @param string|null $token Token JWT opcional. Si es null, se extrae de las cabeceras HTTP.
 * @return object Payload decodificado del token
 * @throws SoapFault|AuthenticationException|Exception
 */
function validate_soap_jwt_token($secretKey = null, $token = null) {
    if ($secretKey === null) {
        $secretKey = getenv('SOAP_JWT_SECRET') ?: 'tu_clave_secreta_super_segura_y_larga_12345!';
    }

    if (empty($secretKey) || strlen($secretKey) < 32) {
        if (class_exists('SoapFault')) {
            throw new SoapFault("Server", "La clave simétrica SOAP JWT no está configurada o tiene menos de 32 caracteres.");
        }
        if (class_exists('AuthenticationException')) {
            throw new AuthenticationException("La clave simétrica SOAP JWT no está configurada o tiene menos de 32 caracteres.", "JWT_KEY_TOO_SHORT");
        }
        throw new Exception("La clave simétrica SOAP JWT no está configurada o tiene menos de 32 caracteres.");
    }

    if ($token === null) {
        $token = extract_jwt_bearer_token();
    }

    if (!$token) {
        if (class_exists('SoapFault')) {
            throw new SoapFault("Client", "Token JWT no proporcionado o cabecera ausente.");
        }
        if (class_exists('AuthenticationException')) {
            throw new AuthenticationException("Token JWT no proporcionado o cabecera ausente.", "JWT_TOKEN_MISSING");
        }
        throw new Exception("Token JWT no proporcionado o cabecera ausente.");
    }

    try {
        // Tolerancia de 60 segundos para compensar desfases de reloj entre servidores
        JWT::$leeway = 60;

        // Decodificar y validar el token con la misma clave simétrica
        $decoded = JWT::decode($token, new Key($secretKey, 'HS256'));

        return $decoded;

    } catch (ExpiredException $e) {
        if (class_exists('SoapFault')) {
            throw new SoapFault("Client", "El token JWT ha expirado.");
        }
        if (class_exists('AuthenticationException')) {
            throw new AuthenticationException("El token JWT ha expirado.", "JWT_TOKEN_EXPIRED");
        }
        throw new Exception("El token JWT ha expirado.");

    } catch (SignatureInvalidException $e) {
        if (class_exists('SoapFault')) {
            throw new SoapFault("Client", "Firma del token JWT inválida: clave simétrica no coincide.");
        }
        if (class_exists('AuthenticationException')) {
            throw new AuthenticationException("Firma del token JWT inválida: clave simétrica no coincide.", "JWT_SIGNATURE_INVALID");
        }
        throw new Exception("Firma del token JWT inválida: " . $e->getMessage());

    } catch (\Exception $e) {
        if (class_exists('SoapFault')) {
            throw new SoapFault("Client", "Token JWT inválido: " . $e->getMessage());
        }
        if (class_exists('AuthenticationException')) {
            throw new AuthenticationException("Token JWT inválido: " . $e->getMessage(), "JWT_INVALID");
        }
        throw new Exception("Token JWT inválido: " . $e->getMessage());
    }
}
