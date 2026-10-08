<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../helpers/SoapAdapter.php';

class SoapAdapterTest extends TestCase
{
    /**
     * Token criptográfico de 64 caracteres de prueba
     */
    private const TOKEN_VALIDO = 'a1b2c3d4e5f60718293a4b5c6d7e8f90123456789abcdef0123456789abcdef0';
    private const TOKEN_ERRONEO = 'ffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffff';

    /**
     * XML SOAP con cabecera de autenticación que contiene el token.
     */
    private function getSampleSoapXmlWithToken(string $token): string
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/"
                   xmlns:xsd="http://www.w3.org/2001/XMLSchema"
                   xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
                   xmlns:tns="InsertUserSOAP"
                   xmlns:wsse="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd">
  <SOAP-ENV:Header>
    <wsse:Security>
      <wsse:UsernameToken>
        <wsse:Username>admin</wsse:Username>
        <usuario>admin</usuario>
        <token>{$token}</token>
        <rol>ADMIN</rol>
        <permisos>CREAR, CONSULTAR, ACTUALIZAR, ELIMINAR (ADMIN)</permisos>
      </wsse:UsernameToken>
    </wsse:Security>
  </SOAP-ENV:Header>
  <SOAP-ENV:Body>
    <tns:consultarAlquilerPorIdResponse>
      <return xsi:type="tns:AlquilerData">
        <id xsi:type="xsd:int">1</id>
        <bicicleta_codigo xsi:type="xsd:string">BIC-001</bicicleta_codigo>
        <bicicleta_tipo xsi:type="xsd:string">Montaña</bicicleta_tipo>
        <bicicleta_tarifa xsi:type="xsd:decimal">15000.00</bicicleta_tarifa>
        <cliente_documento xsi:type="xsd:string">1001234567</cliente_documento>
        <cliente_nombre xsi:type="xsd:string">Carlos Pérez</cliente_nombre>
        <horas xsi:type="xsd:int">3</horas>
        <total xsi:type="xsd:decimal">45000.00</total>
        <estado xsi:type="xsd:string">Finalizado</estado>
      </return>
    </tns:consultarAlquilerPorIdResponse>
  </SOAP-ENV:Body>
</SOAP-ENV:Envelope>
XML;
    }

    /**
     * Test de conversión: JSON (Laravel) a XML SOAP básico.
     */
    public function test_convierte_json_laravel_a_xml_soap(): void
    {
        $jsonInput = json_encode([
            'id' => 1,
        ]);

        $soapXml = \SoapAdapter::jsonToSoapXml($jsonInput, 'consultarAlquilerPorId', [
            'namespace' => 'InsertUserSOAP',
            'username'  => 'admin',
            'password'  => 'admin123',
        ]);

        $this->assertStringContainsString('<soapenv:Envelope', $soapXml);
        $this->assertStringContainsString('<wsse:Username>admin</wsse:Username>', $soapXml);
        $this->assertStringContainsString('<ins:consultarAlquilerPorId>', $soapXml);
        $this->assertStringContainsString('<id>1</id>', $soapXml);
    }

    /**
     * Test de conversión: XML SOAP a JSON de Laravel.
     */
    public function test_convierte_xml_soap_a_json_laravel(): void
    {
        $soapXml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:tns="InsertUserSOAP">
  <SOAP-ENV:Body>
    <tns:consultarAlquilerPorIdResponse>
      <return xsi:type="tns:AlquilerData">
        <id xsi:type="xsd:int">1</id>
        <bicicleta_codigo xsi:type="xsd:string">BIC-001</bicicleta_codigo>
        <bicicleta_tarifa xsi:type="xsd:decimal">15000.00</bicicleta_tarifa>
        <horas xsi:type="xsd:int">3</horas>
        <total xsi:type="xsd:decimal">45000.00</total>
        <estado xsi:type="xsd:string">Finalizado</estado>
      </return>
    </tns:consultarAlquilerPorIdResponse>
  </SOAP-ENV:Body>
</SOAP-ENV:Envelope>
XML;

        $jsonOutput = \SoapAdapter::xmlToJson($soapXml);
        $data = json_decode($jsonOutput, true);

        $this->assertTrue($data['success']);
        $this->assertEquals(1, $data['data']['id']);
        $this->assertEquals('BIC-001', $data['data']['bicicleta_codigo']);
        $this->assertEquals(15000.00, $data['data']['bicicleta_tarifa']);
        $this->assertEquals(3, $data['data']['horas']);
        $this->assertEquals(45000.00, $data['data']['total']);
        $this->assertEquals('Finalizado', $data['data']['estado']);
    }

    /**
     * Test de conversión: SOAP Fault a JSON de error estructurado.
     */
    public function test_convierte_soap_fault_a_json_error(): void
    {
        $faultXml = <<<XML
<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/">
  <SOAP-ENV:Body>
    <SOAP-ENV:Fault>
      <faultcode>SOAP-ENV:Client</faultcode>
      <faultstring>El cliente solicitado no existe en la base de datos.</faultstring>
    </SOAP-ENV:Fault>
  </SOAP-ENV:Body>
</SOAP-ENV:Envelope>
XML;

        $jsonOutput = \SoapAdapter::xmlToJson($faultXml);
        $data = json_decode($jsonOutput, true);

        $this->assertFalse($data['success']);
        $this->assertEquals('SOAP_FAULT', $data['error']);
        $this->assertEquals('El cliente solicitado no existe en la base de datos.', $data['message']);
    }

    /**
     * VALIDACIÓN 1 (XML -> JSON):
     * Si el token entregado por el SOAP coincide con el esperado,
     * el adaptador genera el JSON y MUESTRA TODA LA INFORMACIÓN.
     */
    public function test_xml_a_json_muestra_toda_la_informacion_si_token_coincide(): void
    {
        $soapXml = $this->getSampleSoapXmlWithToken(self::TOKEN_VALIDO);

        // Se invoca pasando el token esperado
        $jsonResult = \SoapAdapter::xmlToJsonWithTokenValidation($soapXml, self::TOKEN_VALIDO);
        $response = json_decode($jsonResult, true);

        // Verificaciones
        $this->assertTrue($response['success'], 'Debe retornar success: true cuando el token coincide');
        $this->assertTrue($response['authenticated'], 'Debe estar autenticado');
        $this->assertEquals(self::TOKEN_VALIDO, $response['token']);
        $this->assertEquals('admin', $response['usuario']);
        $this->assertEquals('ADMIN', $response['rol']);

        // Toda la información debe estar presente en el campo data
        $this->assertNotNull($response['data']);
        $this->assertEquals(1, $response['data']['id']);
        $this->assertEquals('BIC-001', $response['data']['bicicleta_codigo']);
        $this->assertEquals('Montaña', $response['data']['bicicleta_tipo']);
        $this->assertEquals(15000.00, $response['data']['bicicleta_tarifa']);
        $this->assertEquals('1001234567', $response['data']['cliente_documento']);
        $this->assertEquals('Carlos Pérez', $response['data']['cliente_nombre']);
        $this->assertEquals(3, $response['data']['horas']);
        $this->assertEquals(45000.00, $response['data']['total']);
        $this->assertEquals('Finalizado', $response['data']['estado']);
    }

    /**
     * VALIDACIÓN 2 (XML -> JSON):
     * Si el token entregado por el SOAP NO coincide con el esperado,
     * el adaptador BLOQUEA el acceso y NO muestra la información.
     */
    public function test_xml_a_json_bloquea_informacion_si_token_no_coincide(): void
    {
        $soapXml = $this->getSampleSoapXmlWithToken(self::TOKEN_VALIDO);

        // Se envía un token esperado incorrecto
        $jsonResult = \SoapAdapter::xmlToJsonWithTokenValidation($soapXml, self::TOKEN_ERRONEO);
        $response = json_decode($jsonResult, true);

        // Verificaciones de bloqueo
        $this->assertFalse($response['success'], 'Debe retornar success: false');
        $this->assertFalse($response['authenticated'], 'No debe estar autenticado');
        $this->assertEquals('TOKEN_NO_COINCIDE', $response['error']);
        $this->assertNull($response['data'], 'El campo data debe ser null para proteger la información');
        $this->assertStringContainsString('no coincide', $response['message']);
    }

    /**
     * VALIDACIÓN 3 (XML -> JSON):
     * Si se exige token pero el SOAP no lo incluye, bloquea la información.
     */
    public function test_xml_a_json_bloquea_si_falta_token(): void
    {
        $soapXmlSinToken = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/">
  <SOAP-ENV:Body>
    <consultarAlquilerResponse>
      <return><id>1</id><bicicleta_codigo>BIC-001</bicicleta_codigo></return>
    </consultarAlquilerResponse>
  </SOAP-ENV:Body>
</SOAP-ENV:Envelope>
XML;

        $jsonResult = \SoapAdapter::xmlToJson($soapXmlSinToken, true, ['require_token' => true]);
        $response = json_decode($jsonResult, true);

        $this->assertFalse($response['success']);
        $this->assertFalse($response['authenticated']);
        $this->assertEquals('TOKEN_VACIO', $response['error']);
        $this->assertNull($response['data']);
    }

    /**
     * VALIDACIÓN 4 (JSON -> XML):
     * Si el token provisto en el JSON de Laravel coincide con el esperado,
     * el adaptador construye el sobre XML SOAP con WS-Security y TODA la información.
     */
    public function test_json_a_xml_construye_sobre_con_toda_la_informacion_si_token_coincide(): void
    {
        $payloadLaravel = [
            'idAlquiler'     => 1,
            'montoPenalidad' => 5000.00,
            'token'          => self::TOKEN_VALIDO,
        ];

        $soapXml = \SoapAdapter::jsonToSoapXmlWithTokenValidation(
            $payloadLaravel,
            'generarCobroSoap',
            self::TOKEN_VALIDO,
            ['username' => 'admin']
        );

        // Verificaciones del XML generado
        $this->assertStringContainsString('<soapenv:Envelope', $soapXml);
        $this->assertStringContainsString('<wsse:Security>', $soapXml);
        $this->assertStringContainsString('<token>' . self::TOKEN_VALIDO . '</token>', $soapXml);
        $this->assertStringContainsString('<ins:generarCobroSoap>', $soapXml);
        $this->assertStringContainsString('<idAlquiler>1</idAlquiler>', $soapXml);
        $this->assertStringContainsString('<montoPenalidad>5000</montoPenalidad>', $soapXml);
    }

    /**
     * VALIDACIÓN 5 (JSON -> XML):
     * Si el token provisto en el JSON de Laravel NO coincide con el esperado,
     * el adaptador BLOQUEA la generación normal y retorna un sobre SOAP Fault.
     */
    public function test_json_a_xml_bloquea_y_genera_fault_si_token_no_coincide(): void
    {
        $payloadLaravel = [
            'idAlquiler'     => 1,
            'token'          => self::TOKEN_ERRONEO, // Token incorrecto
        ];

        $soapXml = \SoapAdapter::jsonToSoapXmlWithTokenValidation(
            $payloadLaravel,
            'generarCobroSoap',
            self::TOKEN_VALIDO // Token esperado diferente
        );

        // Verificaciones de bloqueo mediante SOAP Fault
        $this->assertStringContainsString('<soapenv:Fault>', $soapXml);
        $this->assertStringContainsString('SOAP-ENV:Client.Authentication', $soapXml);
        $this->assertStringContainsString('TOKEN_NO_COINCIDE', $soapXml);
        $this->assertStringNotContainsString('<ins:generarCobroSoap>', $soapXml);
    }
}
