<?php
/**
 * ==========================================================
 * PROYECTO: Integración Híbrida SOAP (XML) <-> REST (JSON)
 * MÓDULO: Helpers / Adaptador de Comunicación
 * ARCHIVO: helpers/SoapAdapter.php
 * DESCRIPCIÓN: Adaptador bidireccional encargado de transformar:
 *   1. XML SOAP (generado por el servidor NuSOAP) -> Formato JSON para Laravel.
 *   2. JSON (emitido por el microservicio Laravel) -> XML SOAP con WS-Security.
 * Incluye validación bidireccional de Tokens criptográficos y credenciales WS-Security:
 *   - Si el token en XML es válido y coincide, expone toda la información en JSON.
 *   - Si el token en JSON es válido y coincide, genera el XML SOAP con toda la información.
 *   - Si el token no es válido o no coincide, bloquea el acceso en ambas direcciones.
 * ==========================================================
 */

require_once __DIR__ . '/utf8_helper.php';

class SoapAdapter
{
    /**
     * Namespace SOAP por defecto para la aplicación
     */
    public const DEFAULT_NAMESPACE = 'InsertUserSOAP';
    public const SOAP_ENV_NS = 'http://schemas.xmlsoap.org/soap/envelope/';
    public const WSSE_NS = 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd';

    /**
     * Convierte una trama XML de respuesta SOAP en una cadena JSON limpia.
     * Si se configuran opciones de token (expected_token o require_token), valida
     * el token del SOAP antes de exponer la información.
     *
     * @param string $soapXml XML crudo de respuesta SOAP
     * @param bool $pretty Formatear con sangría (pretty print)
     * @param array $options Opciones adicionales (expected_token, require_token, validate_token_db, pdo)
     * @return string Cadena JSON
     */
    public static function xmlToJson(string $soapXml, bool $pretty = true, array $options = []): string
    {
        $arrayData = self::xmlToArray($soapXml, $options);
        $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
        if ($pretty) {
            $flags |= JSON_PRETTY_PRINT;
        }
        return json_encode($arrayData, $flags);
    }

    /**
     * Acceso rápido para validar que el token del SOAP coincida con el esperado
     * y convertir el XML a JSON con toda la información.
     *
     * @param string $soapXml XML de respuesta SOAP
     * @param string|null $expectedToken Token esperado que debe coincidir
     * @param array $options Opciones adicionales
     * @return string JSON resultante
     */
    public static function xmlToJsonWithTokenValidation(string $soapXml, ?string $expectedToken = null, array $options = []): string
    {
        $options['expected_token'] = $expectedToken;
        $options['require_token']  = true;
        return self::xmlToJson($soapXml, true, $options);
    }

    /**
     * Parsea una trama XML SOAP y la convierte en un arreglo asociativo PHP.
     * Soporta detección automática de SOAP Faults, envelopes, extracción de payloads
     * y validación estricta de tokens en la cabecera/cuerpo.
     *
     * @param string $soapXml
     * @param array $options Opciones de validación de token y base de datos
     * @return array
     */
    public static function xmlToArray(string $soapXml, array $options = []): array
    {
        $soapXml = trim($soapXml);
        if (empty($soapXml)) {
            return [
                'success' => false,
                'error'   => 'XML_VACIO',
                'message' => 'La trama XML proporcionada está vacía.',
            ];
        }

        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        if (!$dom->loadXML($soapXml)) {
            $errors = libxml_get_errors();
            libxml_clear_errors();
            $msg = !empty($errors) ? trim($errors[0]->message) : 'XML malformado.';
            return [
                'success' => false,
                'error'   => 'XML_INVALIDO',
                'message' => 'No fue posible parsear el XML SOAP: ' . $msg,
            ];
        }
        libxml_clear_errors();

        $xpath = new \DOMXPath($dom);

        // 1. Detección de SOAP Fault (Errores a nivel de protocolo SOAP)
        $faultNode = $xpath->query("//*[local-name()='Fault']")->item(0);
        if ($faultNode) {
            $faultCode = $xpath->query(".//*[local-name()='faultcode']", $faultNode)->item(0)?->nodeValue ?? 'SOAP-ENV:Server';
            $faultString = $xpath->query(".//*[local-name()='faultstring']", $faultNode)->item(0)?->nodeValue ?? 'Error desconocido en el servidor SOAP';
            $detail = $xpath->query(".//*[local-name()='detail']", $faultNode)->item(0)?->nodeValue ?? null;

            return [
                'success'   => false,
                'error'     => 'SOAP_FAULT',
                'faultcode' => trim($faultCode),
                'message'   => trim($faultString),
                'detail'    => $detail ? trim($detail) : null,
            ];
        }

        // 2. Extraer información del Token desde la cabecera o el cuerpo SOAP
        $tokenInfo = self::extractTokenFromXml($xpath);

        // 3. Validación de Token si fue solicitado
        $expectedToken = $options['expected_token'] ?? null;
        $requireToken  = $options['require_token'] ?? ($expectedToken !== null);
        $pdo           = $options['pdo'] ?? null;

        if ($requireToken || $expectedToken !== null || !empty($options['validate_token_db'])) {
            $tokenRecibido = $tokenInfo['token'] ?? null;
            $tokenCheck = self::validateToken($tokenRecibido, $expectedToken, $pdo);

            if (!$tokenCheck['valid']) {
                return [
                    'success'        => false,
                    'authenticated'  => false,
                    'error'          => $tokenCheck['error'] ?? 'TOKEN_INVALIDO',
                    'message'        => 'Acceso denegado: ' . ($tokenCheck['message'] ?? 'Token no válido.'),
                    'token_recibido' => $tokenRecibido,
                    'data'           => null,
                ];
            }
        }

        // 4. Extraer el contenido del Body SOAP
        $bodyNode = $xpath->query("//*[local-name()='Body']")->item(0);
        $targetRoot = $bodyNode ?: $dom->documentElement;

        // 5. Buscar respuesta de la operación (ej. consultarAlquilerPorIdResponse o su nodo return)
        $returnNodes = $xpath->query(".//*[local-name()='return']", $targetRoot);
        $extractedData = null;

        if ($returnNodes->length > 0) {
            $extractedData = self::domNodeToValue($xpath, $returnNodes->item(0));
        } else {
            // Si no hay nodo <return>, convertir los hijos directos del Body
            $childNodes = [];
            foreach ($targetRoot->childNodes as $child) {
                if ($child->nodeType === XML_ELEMENT_NODE) {
                    $childNodes[$child->localName] = self::domNodeToValue($xpath, $child);
                }
            }
            $extractedData = count($childNodes) === 1 ? reset($childNodes) : $childNodes;
        }

        // 6. Construir respuesta exitosa con metadatos de autenticación si existen
        $response = [
            'success' => true,
        ];

        if ($tokenInfo && !empty($tokenInfo['token'])) {
            $response['authenticated'] = true;
            $response['token']         = $tokenInfo['token'];
            if (!empty($tokenInfo['username'])) {
                $response['usuario']   = $tokenInfo['username'];
            }
            if (!empty($tokenInfo['rol'])) {
                $response['rol']       = $tokenInfo['rol'];
            }
            if (!empty($tokenInfo['permisos'])) {
                $response['permisos']  = $tokenInfo['permisos'];
            }
        }

        $response['data'] = $extractedData;
        return $response;
    }

    /**
     * Extrae información de token, usuario, rol y permisos desde el XML SOAP.
     *
     * @param \DOMXPath $xpath
     * @return array|null
     */
    public static function extractTokenFromXml(\DOMXPath $xpath): ?array
    {
        // Buscar en Header (WS-Security o cabecera personalizada)
        $tokenNodes = $xpath->query("//*[local-name()='Header']//*[local-name()='token' or local-name()='Password']");
        $userNodes  = $xpath->query("//*[local-name()='Header']//*[local-name()='usuario' or local-name()='Username']");
        $rolNodes   = $xpath->query("//*[local-name()='Header']//*[local-name()='rol' or local-name()='role']");
        $permNodes  = $xpath->query("//*[local-name()='Header']//*[local-name()='permisos']");

        $token    = $tokenNodes->length > 0 ? trim($tokenNodes->item(0)->nodeValue ?? '') : '';
        $username = $userNodes->length > 0 ? trim($userNodes->item(0)->nodeValue ?? '') : '';
        $rol      = $rolNodes->length > 0 ? trim($rolNodes->item(0)->nodeValue ?? '') : '';
        $permisos = $permNodes->length > 0 ? trim($permNodes->item(0)->nodeValue ?? '') : '';

        // Si no está en el Header, buscar si vino en el payload de respuesta (ej. LoginService)
        if ($token === '') {
            $bodyToken = $xpath->query("//*[local-name()='Body']//*[local-name()='token'] | //*[local-name()='return'][string-length(text())=64]");
            if ($bodyToken->length > 0) {
                $token = trim($bodyToken->item(0)->nodeValue ?? '');
            }
        }

        if ($token === '' && $username === '') {
            return null;
        }

        return [
            'token'    => $token !== '' ? $token : null,
            'username' => $username !== '' ? $username : null,
            'rol'      => $rol !== '' ? $rol : null,
            'permisos' => $permisos !== '' ? $permisos : null,
        ];
    }

    /**
     * Validador de Token:
     * 1. Verifica no vacío y formato válido (64 hex o seguro).
     * 2. Si se provee $expectedToken, verifica coincidencia exacta con hash_equals.
     * 3. Si se provee $pdo, verifica existencia y vigencia en la base de datos (tabla user).
     *
     * @param string|null $token Token a evaluar
     * @param string|null $expectedToken Token esperado
     * @param \PDO|null $pdo Conexión a la base de datos opcional
     * @return array ['valid' => bool, 'error' => string, 'message' => string, 'user' => array|null]
     */
    public static function validateToken(?string $token, ?string $expectedToken = null, ?\PDO $pdo = null): array
    {
        $token = trim((string)$token);
        if ($token === '') {
            return [
                'valid'   => false,
                'error'   => 'TOKEN_VACIO',
                'message' => 'No se proporcionó ningún token de autenticación.',
            ];
        }

        // 1. Verificación de coincidencia con token esperado
        if ($expectedToken !== null && $expectedToken !== '') {
            $expectedToken = trim($expectedToken);
            if (!hash_equals($expectedToken, $token)) {
                return [
                    'valid'   => false,
                    'error'   => 'TOKEN_NO_COINCIDE',
                    'message' => 'El token proporcionado no coincide con el token esperado.',
                ];
            }
        }

        // 2. Verificación en base de datos si se provee $pdo
        if ($pdo !== null) {
            try {
                $stmt = $pdo->prepare("SELECT id, user_name, rol, permisos, token, token_date FROM user WHERE token = :token LIMIT 1");
                $stmt->execute([':token' => $token]);
                $user = $stmt->fetch(\PDO::FETCH_ASSOC);

                if (!$user || empty($user['token'])) {
                    return [
                        'valid'   => false,
                        'error'   => 'TOKEN_NO_ENCONTRADO_BD',
                        'message' => 'El token no existe o no se encuentra activo en la base de datos.',
                    ];
                }

                return [
                    'valid'   => true,
                    'user'    => $user,
                    'message' => 'Token verificado exitosamente en base de datos.',
                ];
            } catch (\PDOException $e) {
                return [
                    'valid'   => false,
                    'error'   => 'ERROR_BD_TOKEN',
                    'message' => 'Error al verificar token en base de datos: ' . $e->getMessage(),
                ];
            }
        }

        // 3. Validación de formato criptográfico (64 hex generado por random_bytes(32))
        if (strlen($token) === 64 && ctype_xdigit($token)) {
            return [
                'valid'   => true,
                'message' => 'Token criptográfico de 64 caracteres válido.',
            ];
        }

        // 4. Token alfanumérico seguro general
        if (strlen($token) >= 8) {
            return [
                'valid'   => true,
                'message' => 'Token con formato válido.',
            ];
        }

        return [
            'valid'   => false,
            'error'   => 'FORMATO_TOKEN_INVALIDO',
            'message' => 'El token no tiene un formato válido.',
        ];
    }

    /**
     * Convierte una carga útil JSON de Laravel a un sobre XML SOAP con WS-Security.
     * Realiza validación del token antes de generar el XML:
     *   - Si el token es correcto y coincide, genera el sobre XML con toda la información.
     *   - Si el token es inválido o no coincide, genera un SOAP Fault o lanza excepción.
     *
     * @param string|array $jsonData JSON string o arreglo asociativo de Laravel
     * @param string $operation Nombre de la operación SOAP (ej. consultarAlquilerPorId, registrarAlquiler)
     * @param array $options Opciones de cabecera y espacio de nombres:
     *                       - namespace: string (por defecto InsertUserSOAP)
     *                       - username: string (para WS-Security, por defecto admin)
     *                       - password: string (para WS-Security)
     *                       - token: string (token específico a inyectar y validar)
     *                       - expected_token: string (token que debe coincidir obligatoriamente)
     *                       - require_token: bool (exigir token válido para generar el XML)
     *                       - throw_on_error: bool (lanzar excepción en vez de XML Fault)
     * @return string Sobre XML SOAP completo y formateado
     * @throws InvalidArgumentException Si falla la validación y throw_on_error es true
     */
    public static function jsonToSoapXml(string|array $jsonData, string $operation, array $options = []): string
    {
        $payload = is_string($jsonData) ? json_decode($jsonData, true) : $jsonData;
        if (!is_array($payload)) {
            $payload = [];
        }

        // Extraer token de las opciones o del cuerpo del JSON
        $token = $options['token'] ?? ($payload['token'] ?? ($payload['auth_token'] ?? null));
        $expectedToken = $options['expected_token'] ?? null;
        $requireToken  = $options['require_token'] ?? ($expectedToken !== null);
        $pdo           = $options['pdo'] ?? null;

        // Validar token si se exige o si se especificó expected_token
        if ($requireToken || $expectedToken !== null || !empty($options['validate_token_db'])) {
            $tokenCheck = self::validateToken($token, $expectedToken, $pdo);

            if (!$tokenCheck['valid']) {
                $errorMsg = 'Acceso denegado en Adaptador JSON->XML: ' . ($tokenCheck['message'] ?? 'Token inválido.');

                if (!empty($options['throw_on_error'])) {
                    throw new \InvalidArgumentException($errorMsg, 401);
                }

                // Generar sobre SOAP Fault indicando el rechazo por token
                return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/">
   <soapenv:Body>
      <soapenv:Fault>
         <faultcode>SOAP-ENV:Client.Authentication</faultcode>
         <faultstring>{$errorMsg}</faultstring>
         <detail>
            <error>{$tokenCheck['error']}</error>
            <token_recibido>{$token}</token_recibido>
         </detail>
      </soapenv:Fault>
   </soapenv:Body>
</soapenv:Envelope>
XML;
            }
        }

        $namespace = $options['namespace'] ?? self::DEFAULT_NAMESPACE;
        $username  = $options['username'] ?? 'admin';
        $password  = $options['password'] ?? ($token ?: 'admin123');

        // Construir Cabecera de Seguridad (WS-Security UsernameToken con Token)
        $headerXml = '';
        if ($username || $token || $password) {
            $tokenTag = $token ? "\n            <token>" . htmlspecialchars((string)$token, ENT_XML1, 'UTF-8') . "</token>" : '';
            $headerXml = <<<XML
   <soapenv:Header>
      <wsse:Security>
         <wsse:UsernameToken>
            <wsse:Username>{$username}</wsse:Username>
            <wsse:Password>{$password}</wsse:Password>{$tokenTag}
         </wsse:UsernameToken>
      </wsse:Security>
   </soapenv:Header>
XML;
        } else {
            $headerXml = "   <soapenv:Header/>";
        }

        // Si el token estaba en el payload JSON pero no forma parte de los parámetros de la operación, removerlo
        $bodyPayload = $payload;
        if (isset($bodyPayload['token']) && !in_array($operation, ['actualizarUsuario', 'seleccionarUsuario'])) {
            unset($bodyPayload['token']);
        }

        // Construir el cuerpo con los parámetros de la operación
        $bodyXml = self::arrayToXmlElements($bodyPayload);

        $envelope = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"
                  xmlns:wsse="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd"
                  xmlns:ins="{$namespace}">
{$headerXml}
   <soapenv:Body>
      <ins:{$operation}>
{$bodyXml}
      </ins:{$operation}>
   </soapenv:Body>
</soapenv:Envelope>
XML;

        return trim($envelope);
    }

    /**
     * Acceso rápido para validar que el token provisto en el JSON de Laravel coincida
     * con el esperado antes de construir el sobre XML SOAP con toda la información.
     *
     * @param string|array $jsonData JSON o arreglo
     * @param string $operation Operación SOAP
     * @param string|null $expectedToken Token esperado
     * @param array $options Opciones adicionales
     * @return string XML SOAP generado
     */
    public static function jsonToSoapXmlWithTokenValidation(string|array $jsonData, string $operation, ?string $expectedToken = null, array $options = []): string
    {
        $options['expected_token'] = $expectedToken;
        $options['require_token']  = true;
        return self::jsonToSoapXml($jsonData, $operation, $options);
    }

    /**
     * Convierte de manera recursiva un DOMNode a valores estructurados en PHP (arrays/escalares).
     */
    protected static function domNodeToValue(\DOMXPath $xpath, \DOMNode $node): mixed
    {
        $elementChildren = [];
        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_ELEMENT_NODE) {
                $elementChildren[] = $child;
            }
        }

        // Nodo hoja: retorna el texto con tipado seguro
        if (empty($elementChildren)) {
            $val = trim($node->nodeValue ?? '');
            if ($val === '') {
                return null;
            }

            // Identificadores, teléfonos y códigos se conservan como string
            $tagName = strtolower($node->localName ?? '');
            if (str_contains($tagName, 'documento') || str_contains($tagName, 'telefono') || str_contains($tagName, 'codigo')) {
                return $val;
            }

            // Evitar perder ceros iniciales (ej. códigos o números postales)
            if (preg_match('/^0\d+$/', $val)) {
                return $val;
            }

            if (is_numeric($val)) {
                return str_contains($val, '.') ? (float) $val : (int) $val;
            }
            if (strcasecmp($val, 'true') === 0) {
                return true;
            }
            if (strcasecmp($val, 'false') === 0) {
                return false;
            }
            return $val;
        }

        // Detección de arreglos SOAP (items repetidos o <item>)
        $isList = true;
        foreach ($elementChildren as $child) {
            if ($child->localName !== 'item' && $child->localName !== $elementChildren[0]->localName) {
                $isList = false;
                break;
            }
        }

        // Si son múltiples elementos con la misma etiqueta, o etiquetas <item>, se estructura como lista indexada
        if ($isList && count($elementChildren) > 1) {
            $list = [];
            foreach ($elementChildren as $child) {
                $list[] = self::domNodeToValue($xpath, $child);
            }
            return $list;
        }

        // Estructura asociativa
        $result = [];
        foreach ($elementChildren as $child) {
            $key = $child->localName;
            $val = self::domNodeToValue($xpath, $child);

            if (isset($result[$key])) {
                // Si la clave ya existe, convertir a arreglo de elementos
                if (!is_array($result[$key]) || !array_is_list($result[$key])) {
                    $result[$key] = [$result[$key]];
                }
                $result[$key][] = $val;
            } else {
                $result[$key] = $val;
            }
        }

        return $result;
    }

    /**
     * Convierte un arreglo asociativo a elementos XML con sangría.
     */
    protected static function arrayToXmlElements(array $data, int $indent = 9): string
    {
        $spaces = str_repeat(' ', $indent);
        $xml = '';

        foreach ($data as $key => $value) {
            $cleanKey = preg_replace('/[^a-zA-Z0-9_\-]/', '', (string)$key);
            if (is_array($value)) {
                if (array_is_list($value)) {
                    foreach ($value as $item) {
                        if (is_array($item)) {
                            $xml .= "{$spaces}<item>\n" . self::arrayToXmlElements($item, $indent + 3) . "\n{$spaces}</item>\n";
                        } else {
                            $safeVal = htmlspecialchars((string)$item, ENT_XML1, 'UTF-8');
                            $xml .= "{$spaces}<item>{$safeVal}</item>\n";
                        }
                    }
                } else {
                    $xml .= "{$spaces}<{$cleanKey}>\n" . self::arrayToXmlElements($value, $indent + 3) . "\n{$spaces}</{$cleanKey}>\n";
                }
            } elseif ($value === null) {
                $xml .= "{$spaces}<{$cleanKey}/>\n";
            } else {
                $safeVal = htmlspecialchars((string)$value, ENT_XML1, 'UTF-8');
                $xml .= "{$spaces}<{$cleanKey}>{$safeVal}</{$cleanKey}>\n";
            }
        }

        return rtrim($xml, "\n");
    }
}

// ==========================================================
// Funciones globales procedimentales (Helpers de acceso rápido)
// ==========================================================

if (!function_exists('soap_xml_to_json')) {
    /**
     * Transforma una respuesta XML SOAP a JSON para Laravel.
     */
    function soap_xml_to_json(string $soapXml, bool $pretty = true, array $options = []): string
    {
        return SoapAdapter::xmlToJson($soapXml, $pretty, $options);
    }
}

if (!function_exists('soap_xml_to_json_with_token')) {
    /**
     * Valida el token del SOAP y transforma la respuesta XML a JSON si coincide.
     */
    function soap_xml_to_json_with_token(string $soapXml, ?string $expectedToken = null, array $options = []): string
    {
        return SoapAdapter::xmlToJsonWithTokenValidation($soapXml, $expectedToken, $options);
    }
}

if (!function_exists('soap_xml_to_array')) {
    /**
     * Transforma una respuesta XML SOAP a arreglo asociativo de PHP.
     */
    function soap_xml_to_array(string $soapXml, array $options = []): array
    {
        return SoapAdapter::xmlToArray($soapXml, $options);
    }
}

if (!function_exists('json_to_soap_xml')) {
    /**
     * Transforma una carga útil JSON de Laravel a un sobre XML SOAP listo para emitir.
     */
    function json_to_soap_xml(string|array $jsonData, string $operation, array $options = []): string
    {
        return SoapAdapter::jsonToSoapXml($jsonData, $operation, $options);
    }
}

if (!function_exists('json_to_soap_xml_with_token')) {
    /**
     * Valida el token del JSON de Laravel y genera el sobre XML SOAP si coincide.
     */
    function json_to_soap_xml_with_token(string|array $jsonData, string $operation, ?string $expectedToken = null, array $options = []): string
    {
        return SoapAdapter::jsonToSoapXmlWithTokenValidation($jsonData, $operation, $expectedToken, $options);
    }
}
