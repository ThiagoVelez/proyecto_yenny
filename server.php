<?php
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
@ini_set('display_errors', '0');
/**
 * ==========================================================
 * PROYECTO: Servidor SOAP Modular
 * ARCHIVO: server.php
 * DESCRIPCIÓN: Punto de entrada principal del Servidor SOAP.
 * Carga modularmente las configuraciones, tipos, servicios y registros.
 * ==========================================================
 */

// 0. Utilidades y configuración de codificación UTF-8
require_once __DIR__ . '/helpers/utf8_helper.php';
require_once __DIR__ . '/helpers/exceptions.php';

// 1. Configuración del Servidor SOAP ($server, $namespace)
require_once __DIR__ . '/config/soap_config.php';

// 2 y 3. Configuración y Conexión a Base de Datos ($pdo)
require_once __DIR__ . '/config/database.php';

// 4. Definición de tipos complejos (addComplexType)
require_once __DIR__ . '/types/soap_types.php';

// 6. Funciones que procesan y guardan en MySQL (Lógica de Servicios)
require_once __DIR__ . '/token/token.php';
require_once __DIR__ . '/token/ws_security.php';
require_once __DIR__ . '/services/AuthService.php';
require_once __DIR__ . '/services/BicicletaService.php';
require_once __DIR__ . '/services/ClienteService.php';
require_once __DIR__ . '/services/AlquilerService.php';
require_once __DIR__ . '/services/UserService.php';

// 5. Registro de operaciones del servicio SOAP ($server->register)
require_once __DIR__ . '/routes/soap_register.php';
require_once __DIR__ . '/helpers/wsdl_enhancer.php';

// Si se solicita el WSDL (?wsdl), enriquecerlo con las definiciones de cabecera de seguridad
if (isset($_GET['wsdl']) || (isset($_SERVER['QUERY_STRING']) && strcasecmp($_SERVER['QUERY_STRING'], 'wsdl') === 0)) {
    header("Content-Type: text/xml; charset=UTF-8");
    $rawWsdl = $server->wsdl->serialize();
    echo enrich_wsdl_with_security_header($rawWsdl);
    exit();
}

// 7. Procesar y responder a la solicitud SOAP
$POST_DATA = file_get_contents("php://input");
$GLOBALS['RAW_POST_DATA'] = $POST_DATA;
$server->service($POST_DATA);
exit();
