<?php
// ==========================================================
// PROYECTO: Servidor SOAP Modular
// MÓDULO: Estándar WS-Security & Consumo de Token en el Header (<soap:Header>)
// ARCHIVO: token/ws_security.php
// ==========================================================

require_once __DIR__ . '/token.php';
require_once __DIR__ . '/../helpers/exceptions.php';

/**
 * Verificación de Contraseñas (Soporte Dual: Bcrypt y SHA-256 de MySQL)
 * Admite tanto el hash nativo de PHP (password_verify) como hashes SHA-256
 * generados en MySQL (SHA2(..., 256)).
 *
 * @param string $inputPassword Contraseña en texto plano
 * @param string $storedHash    Hash almacenado en la base de datos
 * @return bool True si es válida, False en caso contrario
 */
function verify_user_password($inputPassword, $storedHash) {
    if (empty($inputPassword) || empty($storedHash)) {
        return false;
    }

    // 1. Verificación nativa Bcrypt (password_hash)
    if (password_verify($inputPassword, $storedHash)) {
        return true;
    }

    // 2. Verificación contra hash SHA-256 de MySQL (SHA2(..., 256) -> 64 hex)
    if (strcasecmp(hash('sha256', $inputPassword), $storedHash) === 0) {
        return true;
    }

    // 3. Comparación directa exacta
    if (hash_equals((string)$storedHash, (string)$inputPassword)) {
        return true;
    }

    return false;
}

/**
 * Extracción de credenciales WS-Security o Header de Autenticación del sobre SOAP (<soap:Header>)
 * Admite:
 * 1. Estándar WS-Security (<wsse:Security><wsse:UsernameToken>...)
 * 2. Header de Autenticación personalizado (<AuthHeader><usuario>...<token>...<permisos>...)
 * 3. Etiquetas directas en el Header (<usuario>, <token>, <permisos>)
 *
 * @param string|null $rawXml XML completo de la petición SOAP
 * @return array|null ['username' => ..., 'password_or_token' => ..., 'permisos' => ...] o null
 */
function extract_wsse_credentials($rawXml = null) {
    global $RAW_POST_DATA, $server;

    if ($rawXml === null && isset($RAW_POST_DATA)) {
        $rawXml = $RAW_POST_DATA;
    }
    if ($rawXml === null && isset($GLOBALS['HTTP_RAW_POST_DATA'])) {
        $rawXml = $GLOBALS['HTTP_RAW_POST_DATA'];
    }
    if ($rawXml === null) {
        $rawXml = file_get_contents("php://input");
    }

    if (!empty($rawXml)) {
        // Intento 1: DOMDocument + XPath independiente del prefijo de namespace
        $prevErrors = libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        if ($dom->loadXML($rawXml, LIBXML_NOERROR | LIBXML_NOWARNING)) {
            $xpath = new DOMXPath($dom);
            
            // Buscar username / usuario / user_name
            $usernameNodes = $xpath->query("//*[local-name()='Header']//*[local-name()='Username' or local-name()='user_name' or local-name()='usuario']");
            
            // Buscar password / token / clave
            $passwordNodes = $xpath->query("//*[local-name()='Header']//*[local-name()='Password' or local-name()='token' or local-name()='clave' or local-name()='password']");
            
            // Buscar permisos / rol / role
            $permisosNodes = $xpath->query("//*[local-name()='Header']//*[local-name()='permisos' or local-name()='permiso' or local-name()='rol' or local-name()='role']");

            $username = $usernameNodes->length > 0 ? trim($usernameNodes->item(0)->nodeValue ?? '') : '';
            $credential = $passwordNodes->length > 0 ? trim($passwordNodes->item(0)->nodeValue ?? '') : '';
            $permisos = $permisosNodes->length > 0 ? trim($permisosNodes->item(0)->nodeValue ?? '') : '';

            libxml_clear_errors();
            libxml_use_internal_errors($prevErrors);

            if ($credential !== '') {
                return array(
                    'username'          => $username,
                    'password_or_token' => $credential,
                    'permisos'          => $permisos
                );
            }
        }
        libxml_clear_errors();
        libxml_use_internal_errors($prevErrors);

        // Intento 2: Expresión regular en caso de variaciones de cabecera
        if (preg_match('/<[a-zA-Z0-9_\-:]*Header[^>]*>(.*?)<\/[a-zA-Z0-9_\-:]*Header>/is', $rawXml, $headerMatches)) {
            $headerContent = $headerMatches[1];
            
            $uFound = preg_match('/<[a-zA-Z0-9_\-:]*(?:Username|user_name|usuario)[^>]*>(.*?)<\/[a-zA-Z0-9_\-:]*(?:Username|user_name|usuario)>/is', $headerContent, $uMatches);
            $pFound = preg_match('/<[a-zA-Z0-9_\-:]*(?:Password|token|clave|password)[^>]*>(.*?)<\/[a-zA-Z0-9_\-:]*(?:Password|token|clave|password)>/is', $headerContent, $pMatches);
            $permFound = preg_match('/<[a-zA-Z0-9_\-:]*(?:permisos|permiso|rol|role)[^>]*>(.*?)<\/[a-zA-Z0-9_\-:]*(?:permisos|permiso|rol|role)>/is', $headerContent, $permMatches);

            if ($pFound) {
                return array(
                    'username'          => $uFound ? trim(html_entity_decode($uMatches[1], ENT_QUOTES | ENT_XML1, 'UTF-8')) : '',
                    'password_or_token' => trim(html_entity_decode($pMatches[1], ENT_QUOTES | ENT_XML1, 'UTF-8')),
                    'permisos'          => $permFound ? trim(html_entity_decode($permMatches[1], ENT_QUOTES | ENT_XML1, 'UTF-8')) : ''
                );
            }
        }
    }

    // Intento 3: Inspeccionar array interno de NuSOAP si fue parseado
    if (isset($server) && is_object($server) && isset($server->requestHeader) && is_array($server->requestHeader)) {
        $nusoapHeader = $server->requestHeader;
        $sec = $nusoapHeader['Security'] ?? ($nusoapHeader['wsse:Security'] ?? ($nusoapHeader['AuthHeader'] ?? $nusoapHeader));
        if (is_array($sec)) {
            $ut = $sec['UsernameToken'] ?? ($sec['wsse:UsernameToken'] ?? $sec);
            if (is_array($ut)) {
                $username   = $ut['Username'] ?? ($ut['wsse:Username'] ?? ($ut['usuario'] ?? ($ut['user_name'] ?? '')));
                $credential = $ut['Password'] ?? ($ut['wsse:Password'] ?? ($ut['token'] ?? ($ut['clave'] ?? '')));
                $permisos   = $ut['permisos'] ?? ($ut['rol'] ?? '');

                if ($credential !== '') {
                    return array(
                        'username'          => is_array($username) ? ($username['!'] ?? '') : (string)$username,
                        'password_or_token' => is_array($credential) ? ($credential['!'] ?? '') : (string)$credential,
                        'permisos'          => is_array($permisos) ? ($permisos['!'] ?? '') : (string)$permisos
                    );
                }
            }
        }
    }

    return null;
}

/**
 * Validador de Seguridad y Consumo de Token para todos los métodos y consultas SOAP.
 * Verifica si el usuario y su token son válidos en MySQL y añade la cabecera
 * de respuesta con usuario, token y permisos.
 *
 * @param bool $reset Forzar reinicio de caché de autenticación para pruebas
 * @return array|false Retorna los datos del usuario autenticado si es válido, o false si se rechaza.
 */
function wsse_authenticate($reset = false) {
    global $pdo, $server;
    static $authenticatedUser = null;

    if ($reset) {
        $authenticatedUser = null;
        return false;
    }

    if ($authenticatedUser !== null) {
        return $authenticatedUser;
    }

    if (!$pdo) {
        return false;
    }

    $creds = extract_wsse_credentials();
    if (!$creds || empty($creds['password_or_token'])) {
        return false;
    }

    $username   = trim((string)$creds['username']);
    $credential = trim((string)$creds['password_or_token']);

    try {
        $user = false;

        // 1. Si se especificó nombre de usuario, buscar primero por user_name
        if (!empty($username)) {
            $stmt = $pdo->prepare("SELECT id, user_name, rol, permisos, password, token, token_date FROM user WHERE user_name = :user_name LIMIT 1");
            $stmt->execute(array(':user_name' => $username));
            $found = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($found) {
                // A. Validar si la credencial coincide con el TOKEN ACTIVO del usuario
                if (!empty($found['token']) && hash_equals($found['token'], $credential)) {
                    $user = $found;
                }
                // B. Validar si la credencial coincide con la CONTRASEÑA del usuario (Bcrypt o SHA-256)
                elseif (!empty($found['password']) && verify_user_password($credential, $found['password'])) {
                    $user = $found;
                }
            }
        }

        // 2. Si no se autenticó por username (o no vino username), buscar directamente por token
        if (!$user) {
            $stmtToken = $pdo->prepare("SELECT id, user_name, rol, permisos, password, token, token_date FROM user WHERE token = :token LIMIT 1");
            $stmtToken->execute(array(':token' => $credential));
            $foundToken = $stmtToken->fetch(PDO::FETCH_ASSOC);

            if ($foundToken) {
                if (empty($username) || strcasecmp($foundToken['user_name'], $username) === 0) {
                    $user = $foundToken;
                }
            }
        }

        if (!$user) {
            return false;
        }

        // Normalizar rol y permisos
        $rol = !empty($user['rol']) ? $user['rol'] : (($user['user_name'] === 'admin') ? 'ADMIN' : 'OPERADOR');
        $permisos = !empty($user['permisos']) ? $user['permisos'] : (($user['user_name'] === 'admin') ? 'CREAR, CONSULTAR, ACTUALIZAR, ELIMINAR (ADMIN)' : 'CONSULTAR, ALQUILAR');

        $user['rol'] = $rol;
        $user['permisos'] = $permisos;

        // ==========================================================
        // CONFIGURAR HEADER DE RESPUESTA SOAP (<SOAP-ENV:Header>)
        // Muestra en la respuesta de SoapUI: WS-Security con usuario, token, rol y permisos
        // ==========================================================
        if (isset($server) && is_object($server)) {
            $tokenVal = !empty($user['token']) ? $user['token'] : $credential;
            $server->responseHeaders = '<wsse:Security xmlns:wsse="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd">'
                . '<wsse:UsernameToken>'
                . '<wsse:Username>' . htmlspecialchars($user['user_name'], ENT_XML1, 'UTF-8') . '</wsse:Username>'
                . '<wsse:Password Type="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-username-token-profile-1.0#PasswordText">' . htmlspecialchars($tokenVal, ENT_XML1, 'UTF-8') . '</wsse:Password>'
                . '<usuario>' . htmlspecialchars($user['user_name'], ENT_XML1, 'UTF-8') . '</usuario>'
                . '<token>' . htmlspecialchars($tokenVal, ENT_XML1, 'UTF-8') . '</token>'
                . '<rol>' . htmlspecialchars($rol, ENT_XML1, 'UTF-8') . '</rol>'
                . '<permisos>' . htmlspecialchars($permisos, ENT_XML1, 'UTF-8') . '</permisos>'
                . '</wsse:UsernameToken>'
                . '</wsse:Security>';
        }

        $authenticatedUser = $user;
        return $user;

    } catch (PDOException $e) {
        error_log("Error en wsse_authenticate: " . $e->getMessage());
        return false;
    }
}

/**
 * Función middleware/helper para obligar autenticación por token en métodos de servicio.
 * Si no está autenticado, lanza AuthenticationException para generar un SOAP Fault estándar.
 *
 * @param string $action Nombre de la operación para trazabilidad
 * @return array Datos del usuario autenticado
 * @throws AuthenticationException Si no hay cabecera con token válido
 */
function require_token_authentication($action = '') {
    $user = wsse_authenticate();
    if (!$user) {
        throw new AuthenticationException(
            "Acceso no autorizado a '$action': Se requiere cabecera de seguridad con usuario y token activo.",
            "TOKEN_AUTH_REQUIRED"
        );
    }
    return $user;
}
