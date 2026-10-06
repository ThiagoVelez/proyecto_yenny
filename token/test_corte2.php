<?php
/**
 * Test de verificación para consultarAlquilerPorId en el Servidor SOAP
 */
require_once __DIR__ . '/../helpers/utf8_helper.php';
require_once __DIR__ . '/../helpers/exceptions.php';
require_once __DIR__ . '/../config/soap_config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../types/soap_types.php';
require_once __DIR__ . '/token.php';
require_once __DIR__ . '/ws_security.php';
require_once __DIR__ . '/../services/AlquilerService.php';

$validSoapXml = <<<XML
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"
                  xmlns:wsse="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd"
                  xmlns:ins="InsertUserSOAP">
   <soapenv:Header>
      <wsse:Security>
         <wsse:UsernameToken>
            <wsse:Username>admin</wsse:Username>
            <wsse:Password>admin123</wsse:Password>
         </wsse:UsernameToken>
      </wsse:Security>
   </soapenv:Header>
   <soapenv:Body>
      <ins:consultarAlquilerPorId>
         <id>1</id>
      </ins:consultarAlquilerPorId>
   </soapenv:Body>
</soapenv:Envelope>
XML;

$GLOBALS['RAW_POST_DATA'] = $validSoapXml;
$res = consultarAlquilerPorId(1);

echo "TEST consultarAlquilerPorId(1):\n";
if (is_array($res) && isset($res['id']) && $res['id'] === 1) {
    echo "[OK] Alquiler obtenido correctamente:\n";
    echo "  - ID: " . $res['id'] . "\n";
    echo "  - Bicicleta: " . $res['bicicleta_codigo'] . " (" . $res['bicicleta_tipo'] . ")\n";
    echo "  - Tarifa: $" . $res['bicicleta_tarifa'] . "\n";
    echo "  - Cliente: " . $res['cliente_nombre'] . " (" . $res['cliente_documento'] . ")\n";
    echo "  - Estado: " . $res['estado'] . "\n";
} else {
    echo "[FALLO] Respuesta no esperada: ";
    print_r($res);
}
