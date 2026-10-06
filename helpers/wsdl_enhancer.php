<?php
/**
 * Helper para enriquecer el WSDL con la definición de cabecera de seguridad
 * para que clientes como SoapUI generen automáticamente los campos en el Header.
 */
function enrich_wsdl_with_security_header(string $wsdlXml): string {
    // 1. Agregar tipo complejo SecurityHeader si no existe
    if (!str_contains($wsdlXml, 'name="SecurityHeader"')) {
        $securityType = <<<XML
 <xsd:complexType name="SecurityHeader">
  <xsd:all>
   <xsd:element name="usuario" type="xsd:string"/>
   <xsd:element name="token" type="xsd:string"/>
  </xsd:all>
 </xsd:complexType>
</xsd:schema>
XML;
        $wsdlXml = str_replace('</xsd:schema>', $securityType, $wsdlXml);
    }

    // 2. Agregar mensaje HeaderSecurity si no existe
    if (!str_contains($wsdlXml, 'name="HeaderSecurity"')) {
        $securityMessage = <<<XML
<message name="HeaderSecurity">
  <part name="Security" type="tns:SecurityHeader"/>
</message>
<portType
XML;
        $wsdlXml = str_replace('<portType', $securityMessage, $wsdlXml);
    }

    // 3. Operaciones que requieren Token en el Header
    $protectedOperations = [
        'listarUsuarios',
        'ListUsersService',
        'SelectUserService',
        'seleccionarUsuario',
        'UpdateUserService',
        'actualizarUsuario',
        'DeleteUserService',
        'eliminarUsuario',
        'InsertUserService',
        'insertarUsuario',
        'consultarAlquileres',
        'listarAlquileres',
        'consultarAlquilerPorId',
        'consultarAlquiler',
        'registrarAlquiler',
        'finalizarAlquiler',
        'consultarBicicleta',
        'listarBicicletas',
        'registrarBicicleta',
        'actualizarBicicleta',
        'consultarCliente',
        'listarClientes',
        'registrarCliente',
    ];

    foreach ($protectedOperations as $op) {
        $pattern = '/(<operation name="' . preg_quote($op, '/') . '">\s*<soap:operation[^>]*\/>\s*<input>)(\s*<soap:body)/i';
        $replacement = '$1' . "\n        " . '<soap:header message="tns:HeaderSecurity" part="Security" use="encoded" encodingStyle="http://schemas.xmlsoap.org/soap/encoding/"/>' . '$2';
        $wsdlXml = preg_replace($pattern, $replacement, $wsdlXml);
    }

    return $wsdlXml;
}
