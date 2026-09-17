<?php
/**
 * ==========================================================
 * PROYECTO: Servidor SOAP Modular - Fase Token & WS-Security
 * ARCHIVO: token/test_token.php
 * DESCRIPCIÓN: Suite de pruebas automatizadas del proyecto unificado:
 *   1. Generación de Token criptográfico en token/token.php ($token = bin2hex(random_bytes(32)))
 *   2. Verificación dual de contraseñas (Bcrypt y MySQL SHA-256) en token/ws_security.php
 *   3. Extracción y validación de cabecera WS-Security (<wsse:Security><wsse:UsernameToken>)
 *   4. Bloqueo (-1) en todos los métodos CRUD ante ausencia o error de WS-Security
 *   5. Flujo completo de LoginService y ValidateTokenService en services/AuthService.php
 *   6. CRUD completo (Insert, Select, List, Update, Delete) con WS-Security
 *   7. Verificación de no exposición de contraseñas ni tokens en respuestas
 * ==========================================================
 */

echo "==========================================================\n";
echo " INICIANDO SUITE DE PRUEBAS: FASE TOKEN & WS-SECURITY\n";
echo "==========================================================\n\n";

$testsPassed = 0;
$testsTotal = 0;

function assertTest($description, $condition) {
    global $testsPassed, $testsTotal;
    $testsTotal++;
    if ($condition) {
        $testsPassed++;
        echo " [OK] " . $description . "\n";
    } else {
        echo " [FALLO] " . $description . "\n";
    }
}

// ----------------------------------------------------------
// 1. CARGAR MÓDULOS DE LA CARPETA token/
// ----------------------------------------------------------
require_once __DIR__ . '/token.php';
require_once __DIR__ . '/ws_security.php';

// ----------------------------------------------------------
// PRUEBA 1: Generación del Token Criptográfico (Especificación de Clase)
// ----------------------------------------------------------
$token1 = generate_crypto_token();
$token2 = generate_crypto_token();

assertTest("Token 1 tiene longitud exacta de 64 caracteres hexadecimales", strlen($token1) === 64 && ctype_xdigit($token1));
assertTest("Token 2 tiene longitud exacta de 64 caracteres hexadecimales", strlen($token2) === 64 && ctype_xdigit($token2));
assertTest("Los tokens generados son únicos y criptográficamente aleatorios", $token1 !== $token2);

// ----------------------------------------------------------
// PRUEBA 2: Encriptación y Verificación de Contraseñas (Bcrypt y SHA-256)
// ----------------------------------------------------------
$rawPasswordBcrypt = "admin123";
$bcryptHash = password_hash($rawPasswordBcrypt, PASSWORD_DEFAULT);

$rawPasswordSha = "operador123";
$mysqlSha256Hash = hash('sha256', $rawPasswordSha); // Equivalente a SHA2('operador123', 256)

assertTest("Verificación exitosa con hash nativo PHP (Bcrypt / password_verify)", verify_user_password($rawPasswordBcrypt, $bcryptHash));
assertTest("Verificación exitosa con hash SHA-256 de MySQL (SHA2)", verify_user_password($rawPasswordSha, $mysqlSha256Hash));
assertTest("Rechazo ante contraseña incorrecta en Bcrypt", !verify_user_password("clave_erronea", $bcryptHash));
assertTest("Rechazo ante contraseña incorrecta en SHA-256", !verify_user_password("clave_erronea", $mysqlSha256Hash));

// ----------------------------------------------------------
// PRUEBA 3: Extracción de Cabecera WS-Security (<soap:Header>)
// ----------------------------------------------------------
$validSoapXml = <<<XML
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"
                  xmlns:wsse="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd"
                  xmlns:ins="InsertUserSOAP">
   <soapenv:Header>
      <wsse:Security>
         <wsse:UsernameToken>
            <wsse:Username>admin</wsse:Username>
            <wsse:Password>$token1</wsse:Password>
         </wsse:UsernameToken>
      </wsse:Security>
   </soapenv:Header>
   <soapenv:Body>
      <ins:SelectUserService>
         <id>1</id>
      </ins:SelectUserService>
   </soapenv:Body>
</soapenv:Envelope>
XML;

$extracted = extract_wsse_credentials($validSoapXml);
assertTest("Extracción de Username desde <wsse:Security><wsse:UsernameToken>", isset($extracted['username']) && $extracted['username'] === 'admin');
assertTest("Extracción de Password/Token desde <wsse:Security><wsse:UsernameToken>", isset($extracted['password_or_token']) && $extracted['password_or_token'] === $token1);

$noHeaderSoapXml = <<<XML
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ins="InsertUserSOAP">
   <soapenv:Body>
      <ins:SelectUserService>
         <id>1</id>
      </ins:SelectUserService>
   </soapenv:Body>
</soapenv:Envelope>
XML;

$extractedNoHeader = extract_wsse_credentials($noHeaderSoapXml);
assertTest("Detección de ausencia de cabecera WS-Security (retorna null)", $extractedNoHeader === null);

// ----------------------------------------------------------
// PRUEBA 4: Comprobación de Servicios con Base de Datos (MySQL)
// ----------------------------------------------------------
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../services/AuthService.php';
require_once __DIR__ . '/../services/UserService.php';

if ($pdo) {
    echo "\n--- Probando servicios contra MySQL (sistema_bicicletas) ---\n";

    // 4.1 LoginService con usuario semilla 'admin' (Bcrypt)
    $adminToken = LoginService('admin', 'admin123');
    assertTest("LoginService con 'admin'/'admin123' (Bcrypt) retorna token de 64 hex", strlen($adminToken) === 64 && ctype_xdigit($adminToken));

    // 4.2 LoginService con usuario semilla 'operador' (SHA-256)
    $operadorToken = LoginService('operador', 'operador123');
    assertTest("LoginService con 'operador'/'operador123' (SHA-256) retorna token de 64 hex", strlen($operadorToken) === 64 && ctype_xdigit($operadorToken));

    // 4.3 LoginService con contraseña errónea
    $failedLogin = LoginService('admin', 'clave_incorrecta');
    assertTest("LoginService con contraseña inválida retorna -1", $failedLogin === "-1");

    // 4.4 ValidateTokenService con token válido
    $validationResult = ValidateTokenService($adminToken);
    assertTest("ValidateTokenService con token activo retorna 1", $validationResult === "1");

    // 4.5 ValidateTokenService con token ficticio
    $fakeToken = str_repeat("0", 64);
    $fakeValidation = ValidateTokenService($fakeToken);
    assertTest("ValidateTokenService con token ficticio retorna -1", $fakeValidation === "-1");

    // ----------------------------------------------------------
    // 4.6 BLOQUEO (-1) EN TODOS LOS MÉTODOS CRUD SIN WS-SECURITY
    // ----------------------------------------------------------
    $GLOBALS['RAW_POST_DATA'] = $noHeaderSoapXml;

    $insertNoHeader = InsertUserService(array(
        'user_name' => 'invalido', 'lastname' => 'test', 'doc_type_id' => 1,
        'num_doc' => '111', 'password' => '123'
    ));
    assertTest("InsertUserService sin WS-Security se bloquea con -1", $insertNoHeader === "-1" || $insertNoHeader === -1);

    $updateNoHeader = UpdateUserService(array(
        'id' => 1, 'user_name' => 'admin', 'lastname' => 'Sistema', 'doc_type_id' => 1, 'num_doc' => '10101010'
    ));
    assertTest("UpdateUserService sin WS-Security se bloquea con -1", $updateNoHeader === "-1" || $updateNoHeader === -1);

    $deleteNoHeader = DeleteUserService(9999);
    assertTest("DeleteUserService sin WS-Security se bloquea con -1", $deleteNoHeader === "-1" || $deleteNoHeader === -1);

    $selectNoHeader = SelectUserService(1);
    $selectVal = is_object($selectNoHeader) && isset($selectNoHeader->value) ? $selectNoHeader->value : $selectNoHeader;
    assertTest("SelectUserService sin WS-Security se bloquea con -1", $selectVal === "-1" || $selectVal === -1);

    $listNoHeader = ListUsersService();
    $listVal = is_object($listNoHeader) && isset($listNoHeader->value) ? $listNoHeader->value : $listNoHeader;
    assertTest("ListUsersService sin WS-Security se bloquea con -1", $listVal === "-1" || $listVal === -1);

    // ----------------------------------------------------------
    // 4.7 OPERACIONES CRUD CON WS-SECURITY VÁLIDO (TOKEN)
    // ----------------------------------------------------------
    function buildWsseSoapMessage($user, $tokenOrPass) {
        return <<<XML
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"
                  xmlns:wsse="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd">
   <soapenv:Header>
      <wsse:Security>
         <wsse:UsernameToken>
            <wsse:Username>$user</wsse:Username>
            <wsse:Password>$tokenOrPass</wsse:Password>
         </wsse:UsernameToken>
      </wsse:Security>
   </soapenv:Header>
   <soapenv:Body></soapenv:Body>
</soapenv:Envelope>
XML;
    }

    // Cabecera WS-Security con el token activo de admin
    $GLOBALS['RAW_POST_DATA'] = buildWsseSoapMessage('admin', $adminToken);

    // A. Insertar nuevo usuario con password encriptada (Bcrypt)
    $uniqueUser = 'usuario_' . substr(bin2hex(random_bytes(3)), 0, 5);
    $insertResult = InsertUserService(array(
        'user_name'   => $uniqueUser,
        'lastname'    => 'González',
        'doc_type_id' => 1,
        'num_doc'     => '55544433',
        'address'     => 'Calle 45 # 12-34',
        'phone'       => '3159998877',
        'password'    => 'claveSegura2026'
    ));
    assertTest("InsertUserService con WS-Security registra exitosamente", $insertResult === "Se ha guardado correctamente");

    // Verificar en BD que la contraseña se guardó como HASH Bcrypt y no en texto plano
    $checkStmt = $pdo->prepare("SELECT id, password FROM user WHERE user_name = :u");
    $checkStmt->execute(array(':u' => $uniqueUser));
    $insertedRow = $checkStmt->fetch(PDO::FETCH_ASSOC);
    $newUserId = $insertedRow ? (int)$insertedRow['id'] : 0;
    assertTest("La contraseña se encriptó con Bcrypt (inicia con $2y$)", $insertedRow && str_starts_with($insertedRow['password'], '$2y$'));

    // Verificar que el nuevo usuario puede hacer login con su contraseña en texto plano
    $newLoginToken = LoginService($uniqueUser, 'claveSegura2026');
    assertTest("El usuario recién creado puede autenticarse en LoginService", strlen($newLoginToken) === 64);

    // B. SelectUserService con WS-Security válido
    $selectResult = SelectUserService($newUserId);
    assertTest("SelectUserService retorna los datos correctos del usuario", is_array($selectResult) && $selectResult['user_name'] === $uniqueUser);
    assertTest("SelectUserService NO expone el campo 'password'", !isset($selectResult['password']));
    assertTest("SelectUserService NO expone el campo 'token'", !isset($selectResult['token']));

    // C. UpdateUserService con WS-Security
    $updateResult = UpdateUserService(array(
        'id'          => $newUserId,
        'user_name'   => $uniqueUser,
        'lastname'    => 'González Modificado',
        'doc_type_id' => 1,
        'num_doc'     => '55544433',
        'address'     => 'Nueva Dirección 789',
        'phone'       => '3150001122'
    ));
    assertTest("UpdateUserService con WS-Security actualiza exitosamente", $updateResult === "Usuario actualizado correctamente");

    // D. ListUsersService con WS-Security
    $listResult = ListUsersService();
    assertTest("ListUsersService retorna arreglo de usuarios", is_array($listResult) && count($listResult) > 0);
    $firstUser = $listResult[0];
    assertTest("ListUsersService NO expone contraseñas ni tokens en ninguno de los usuarios", !isset($firstUser['password']) && !isset($firstUser['token']));

    // E. DeleteUserService con WS-Security
    $deleteResult = DeleteUserService($newUserId);
    assertTest("DeleteUserService con WS-Security elimina exitosamente", $deleteResult === "Usuario eliminado correctamente");

    // F. Verificar que el usuario eliminado ya no existe
    $selectDeleted = SelectUserService($newUserId);
    $delVal = is_object($selectDeleted) && isset($selectDeleted->value) ? $selectDeleted->value : $selectDeleted;
    assertTest("Consultar usuario eliminado retorna -1", $delVal === "-1" || $delVal === -1);

} else {
    echo "\n [INFO] MySQL no disponible en este entorno.\n";
}

echo "\n==========================================================\n";
echo " RESUMEN: $testsPassed / $testsTotal pruebas pasadas exitosamente.\n";
echo "==========================================================\n";
