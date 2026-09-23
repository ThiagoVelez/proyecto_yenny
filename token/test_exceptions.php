<?php
/**
 * Test automatizado para verificar la arquitectura de Excepciones Controladas en SOAP
 */
require_once __DIR__ . '/../helpers/utf8_helper.php';
require_once __DIR__ . '/../helpers/exceptions.php';
require_once __DIR__ . '/../config/soap_config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../types/soap_types.php';
require_once __DIR__ . '/../token/token.php';
require_once __DIR__ . '/../token/ws_security.php';
require_once __DIR__ . '/../services/AuthService.php';
require_once __DIR__ . '/../services/BicicletaService.php';
require_once __DIR__ . '/../services/ClienteService.php';
require_once __DIR__ . '/../services/AlquilerService.php';
require_once __DIR__ . '/../services/UserService.php';
require_once __DIR__ . '/../routes/soap_register.php';

echo "==========================================================\n";
echo " PRUEBAS DE EXCEPCIONES CONTROLADAS (SOAP FAULTS)\n";
echo "==========================================================\n\n";

$passed = 0;
$total = 0;

function assertCondition($name, $cond) {
    global $passed, $total;
    $total++;
    if ($cond) {
        $passed++;
        echo " [OK] $name\n";
    } else {
        echo " [FALLO] $name\n";
    }
}

// 1. Verificación de clases de excepción
$valEx = new ValidationException("Dato requerido faltante", "CODIGO_REQUERIDO");
assertCondition("ValidationException tiene faultCode = 'Client'", $valEx->getFaultCode() === 'Client');
assertCondition("ValidationException tiene detalle correcto", $valEx->getDetail() === 'CODIGO_REQUERIDO');

$nfEx = new NotFoundException("Bicicleta no encontrada", "BICICLETA_NO_ENCONTRADA");
assertCondition("NotFoundException tiene faultCode = 'Client'", $nfEx->getFaultCode() === 'Client');

$brEx = new BusinessRuleException("Bicicleta no disponible", "BICICLETA_NO_DISPONIBLE");
assertCondition("BusinessRuleException tiene faultCode = 'Client'", $brEx->getFaultCode() === 'Client');

$authEx = new AuthenticationException("WS-Security inválido", "AUTH_FAIL");
assertCondition("AuthenticationException tiene faultCode = 'Client'", $authEx->getFaultCode() === 'Client');

$dbEx = new DatabaseException("Error de conexión a BD", "DB_CONN_FAIL");
assertCondition("DatabaseException tiene faultCode = 'Server'", $dbEx->getFaultCode() === 'Server');

// 2. Verificación de serialización en soap_fault
$fault = handle_service_exception($valEx, 'registrarBicicleta');
assertCondition("handle_service_exception retorna instancia de soap_fault", $fault instanceof soap_fault);
$xml = $fault->serialize();
assertCondition("SOAP Fault contiene tag <faultcode>Client</faultcode>", strpos($xml, '<faultcode') !== false && strpos($xml, 'Client') !== false);
assertCondition("SOAP Fault contiene el mensaje de validación", strpos($xml, 'Dato requerido faltante') !== false);

// 3. Simulación de ejecución de servicios con datos erróneos
// 3.1 Registrar bicicleta con tarifa negativa (debe retornar soap_fault)
$faultBici = registrarBicicleta(array(
    'codigo' => 'BICI_ERR_01',
    'tipo'   => 'Ruta',
    'tarifa' => -50.00
));
assertCondition("registrarBicicleta con tarifa negativa retorna soap_fault", $faultBici instanceof soap_fault);
if ($faultBici instanceof soap_fault) {
    $xmlBici = $faultBici->serialize();
    assertCondition("Fallo contiene explicación de tarifa mayor o igual a 0", strpos($xmlBici, 'mayor o igual a 0') !== false);
}

// 3.2 Registrar bicicleta sin código (debe retornar soap_fault)
$faultBiciSinCod = registrarBicicleta(array(
    'codigo' => '',
    'tipo'   => 'Ruta',
    'tarifa' => 15.00
));
assertCondition("registrarBicicleta sin código retorna soap_fault de validación", $faultBiciSinCod instanceof soap_fault);

// 3.3 Registrar alquiler con parámetros vacíos
$faultAlq = registrarAlquiler(array(
    'codigo_bicicleta' => '',
    'documento_cliente' => ''
));
assertCondition("registrarAlquiler sin parámetros retorna soap_fault de validación", $faultAlq instanceof soap_fault);

// 3.4 Consultar bicicleta con código vacío
$faultConsBici = consultarBicicleta('');
assertCondition("consultarBicicleta sin código retorna soap_fault", $faultConsBici instanceof soap_fault);

// 3.5 Consultar cliente con documento vacío
$faultConsCli = consultarCliente('');
assertCondition("consultarCliente sin documento retorna soap_fault", $faultConsCli instanceof soap_fault);

// 3.6 Finalizar alquiler con código vacío
$faultFinAlq = finalizarAlquiler('');
assertCondition("finalizarAlquiler sin código retorna soap_fault", $faultFinAlq instanceof soap_fault);

// 4. Verificación de procesamiento en el servidor SOAP completo (Payload XML)
$soapRequest = <<<XML
<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:SOAP-ENC="http://schemas.xmlsoap.org/soap/encoding/" xmlns:tns="InsertUserSOAP">
   <SOAP-ENV:Body>
      <tns:registrarBicicleta>
         <data>
            <codigo>TEST-XML-FAULT</codigo>
            <tipo>Montaña</tipo>
            <tarifa>-99.99</tarifa>
            <estado>Disponible</estado>
         </data>
      </tns:registrarBicicleta>
   </SOAP-ENV:Body>
</SOAP-ENV:Envelope>
XML;

$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['CONTENT_TYPE'] = 'text/xml; charset=utf-8';
$GLOBALS['RAW_POST_DATA'] = $soapRequest;
$GLOBALS['HTTP_RAW_POST_DATA'] = $soapRequest;

ob_start();
$server->service($soapRequest);
$soapResponse = ob_get_clean();

// echo "RESP: " . substr(strstr($soapResponse, '<SOAP-ENV:Body>'), 0, 400) . "\n";
assertCondition("El servidor SOAP responde con <SOAP-ENV:Fault>", stripos($soapResponse, ':Fault>') !== false || stripos($soapResponse, '<fault>') !== false);
assertCondition("El servidor SOAP indica faultcode Client", stripos($soapResponse, 'Client') !== false);
assertCondition("El servidor SOAP incluye la descripción de la tarifa", stripos($soapResponse, 'tarifa es obligatoria') !== false);

echo "\n==========================================================\n";
echo " RESUMEN: $passed / $total pruebas pasadas exitosamente.\n";
echo "==========================================================\n";
