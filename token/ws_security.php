<?php
// ==========================================================
// PROYECTO: Servidor SOAP Modular
// MÓDULO: Estándar WS-Security en el Header (<soap:Header>)
// ARCHIVO: token/ws_security.php
// ==========================================================

require_once __DIR__ . '/token.php';

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
 * Extracción de credenciales WS-Security del sobre SOAP (<soap:Header>)
 * Tal como lo solicitó la profesora en las diapositivas sobre WS-Security:
 *
 * <soapenv:Header>
 *    <wsse:Security xmlns:wsse="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd">
 *       <wsse:UsernameToken>
 *          <wsse:Username>nombre_usuario</wsse:Username>
 *          <wsse:Password>contraseña_o_token</wsse:Password>
 *       </wsse:UsernameToken>
 *    </wsse:Security>
 * </soapenv:Header>
 *
 * @param string|null $rawXml XML completo de la petición SOAP
 * @return array|null ['username' => ..., 'password_or_token' => ...] o null si no se encuentra
 */
function extract_wsse_credentials($rawXml = null) {
    global $RAW_POST_DATA, $server;

    if ($rawXml === null && isset($RAW_POST_DATA)) {
        $rawXml = $RAW_POST_DATA;
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
            $usernameNodes = $xpath->query("//*[local-name()='Header']//*[local-name()='Security']//*[local-name()='UsernameToken']//*[local-name()='Username']");
            $passwordNodes = $xpath->query("//*[local-name()='Header']//*[local-name()='Security']//*[local-name()='UsernameToken']//*[local-name()='Password']");

            if ($usernameNodes->length > 0 && $passwordNodes->length > 0) {
                $username = trim($usernameNodes->item(0)->nodeValue ?? '');
                $credential = trim($passwordNodes->item(0)->nodeValue ?? '');
                libxml_clear_errors();
                libxml_use_internal_errors($prevErrors);
                return array(
                    'username'          => $username,
                    'password_or_token' => $credential
                );
            }
        }
        libxml_clear_errors();
        libxml_use_internal_errors($prevErrors);

        // Intento 2: Expresión regular en caso de variaciones de cabecera
        if (preg_match('/<[a-zA-Z0-9_\-:]*Header[^>]*>(.*?)<\/[a-zA-Z0-9_\-:]*Header>/is', $rawXml, $headerMatches)) {
            $headerContent = $headerMatches[1];
            $uFound = preg_match('/<[a-zA-Z0-9_\-:]*Username[^>]*>(.*?)<\/[a-zA-Z0-9_\-:]*Username>/is', $headerContent, $uMatches);
            $pFound = preg_match('/<[a-zA-Z0-9_\-:]*Password[^>]*>(.*?)<\/[a-zA-Z0-9_\-:]*Password>/is', $headerContent, $pMatches);

            if ($uFound && $pFound) {
                return array(
                    'username'          => trim(html_entity_decode($uMatches[1], ENT_QUOTES | ENT_XML1, 'UTF-8')),
                    'password_or_token' => trim(html_entity_decode($pMatches[1], ENT_QUOTES | ENT_XML1, 'UTF-8'))
                );
            }
        }
    }

    // Intento 3: Inspeccionar array interno de NuSOAP si fue parseado
    if (isset($server) && is_object($server) && isset($server->requestHeader) && is_array($server->requestHeader)) {
        $nusoapHeader = $server->requestHeader;
        $sec = $nusoapHeader['Security'] ?? ($nusoapHeader['wsse:Security'] ?? null);
        if (is_array($sec)) {
            $ut = $sec['UsernameToken'] ?? ($sec['wsse:UsernameToken'] ?? null);
            if (is_array($ut)) {
                $username   = $ut['Username'] ?? ($ut['wsse:Username'] ?? '');
                $credential = $ut['Password'] ?? ($ut['wsse:Password'] ?? '');
                if ($username !== '' && $credential !== '') {
                    return array(
                        'username'          => is_array($username) ? ($username['!'] ?? '') : (string)$username,
                        'password_or_token' => is_array($credential) ? ($credential['!'] ?? '') : (string)$credential
                    );
                }
            }
        }
    }

    return null;
}

/**
 * Validador de Seguridad WS-Security para métodos CRUD.
 * Verifica si el usuario y su contraseña_o_token son válidos en MySQL.
 *
 * @return array|false Retorna los datos del usuario autenticado si es válido, o false si se rechaza.
 */
function wsse_authenticate() {
    global $pdo;

    if (!$pdo) {
        return false;
    }

    $creds = extract_wsse_credentials();
    if (!$creds || empty($creds['username']) || empty($creds['password_or_token'])) {
        return false;
    }

    $username   = $creds['username'];
    $credential = $creds['password_or_token'];

    try {
        $stmt = $pdo->prepare("SELECT id, user_name, password, token, token_date FROM user WHERE user_name = :user_name");
        $stmt->execute(array(':user_name' => $username));
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // A. Validar si la credencial coincide con el TOKEN ACTIVO del usuario
            if (!empty($user['token']) && hash_equals($user['token'], $credential)) {
                return $user;
            }

            // B. Validar si la credencial coincide con la CONTRASEÑA del usuario (Bcrypt o SHA-256)
            if (!empty($user['password']) && verify_user_password($credential, $user['password'])) {
                return $user;
            }
        }

        // C. Validación por token si se envió como credencial directa
        $stmtToken = $pdo->prepare("SELECT id, user_name, password, token, token_date FROM user WHERE token = :token");
        $stmtToken->execute(array(':token' => $credential));
        $userByToken = $stmtToken->fetch(PDO::FETCH_ASSOC);
        if ($userByToken && ($userByToken['user_name'] === $username || empty($username))) {
            return $userByToken;
        }

        return false;

    } catch (PDOException $e) {
        return false;
    }
}
