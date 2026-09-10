<?php
// ==========================================================
// PROYECTO: Sistema de Alquiler de Bicicletas / Servicios SOAP
// MÓDULO: Lógica de Servicios - Entidad Usuario Protegida con WS-Security
// ARCHIVO: services/UserService.php
// ==========================================================

require_once __DIR__ . '/../token/ws_security.php';
require_once __DIR__ . '/../helpers/utf8_helper.php';

/**
 * Función auxiliar para retornar '-1' compatible con NuSOAP
 * tanto para tipos simples (xsd:string) como complejos (tns:UserData / tns:UserArray).
 */
if (!function_exists('user_soap_error')) {
    function user_soap_error() {
        return class_exists('soapval') ? new soapval('return', 'xsd:string', '-1') : -1;
    }
}

/**
 * 1. Insertar Usuario (PROTEGIDO POR WS-SECURITY)
 * Valida la cabecera WS-Security. Si falta o es incorrecta, se bloquea con -1.
 * Encripta la contraseña mediante password_hash ($password, PASSWORD_DEFAULT) (Bcrypt).
 *
 * @param array $data ['user_name', 'lastname', 'doc_type_id', 'num_doc', 'address', 'phone', 'password']
 * @return string Mensaje de confirmación o -1 ante error/autenticación fallida.
 */
function InsertUserService($data) {
    global $pdo;

    // Validación obligatoria de WS-Security en cabecera
    if (!wsse_authenticate()) {
        return "-1";
    }

    if (!$pdo) {
        return "-1";
    }

    // Normalizar a UTF-8
    $data = function_exists('utf8_converter') ? utf8_converter($data) : $data;

    // Validación de estructura
    if (!is_array($data) || empty($data)) {
        return "-1";
    }

    // Validación de campos obligatorios (incluyendo contraseña)
    if (
        !isset($data['user_name']) || trim($data['user_name']) === '' ||
        !isset($data['lastname']) || trim($data['lastname']) === '' ||
        !isset($data['doc_type_id']) || !is_numeric($data['doc_type_id']) || intval($data['doc_type_id']) <= 0 ||
        !isset($data['num_doc']) || trim($data['num_doc']) === '' ||
        !isset($data['password']) || trim($data['password']) === ''
    ) {
        return "-1";
    }

    try {
        // Encriptar la contraseña mediante Bcrypt (password_hash)
        $hashedPassword = password_hash(trim($data['password']), PASSWORD_DEFAULT);

        $sql = "INSERT INTO user (user_name, lastname, doc_type_id, num_doc, address, phone, password, created_date)
                VALUES (:user_name, :lastname, :doc_type_id, :num_doc, :address, :phone, :password, NOW())";

        $stmt = $pdo->prepare($sql);
        $user_name   = trim($data['user_name']);
        $lastname    = trim($data['lastname']);
        $doc_type_id = intval($data['doc_type_id']);
        $num_doc     = trim($data['num_doc']);
        $address     = isset($data['address']) ? trim($data['address']) : '';
        $phone       = isset($data['phone']) ? trim($data['phone']) : '';

        $stmt->bindParam(':user_name', $user_name);
        $stmt->bindParam(':lastname', $lastname);
        $stmt->bindParam(':doc_type_id', $doc_type_id);
        $stmt->bindParam(':num_doc', $num_doc);
        $stmt->bindParam(':address', $address);
        $stmt->bindParam(':phone', $phone);
        $stmt->bindParam(':password', $hashedPassword);

        $stmt->execute();
        return "Se ha guardado correctamente";

    } catch (PDOException $e) {
        return "-1";
    }
}

/**
 * 2. Actualizar Usuario (PROTEGIDO POR WS-SECURITY)
 * Valida la cabecera WS-Security. Si falta o es incorrecta, se bloquea con -1.
 * Si se envía una nueva contraseña, la actualiza encriptada con password_hash.
 *
 * @param array $data ['id', 'user_name', 'lastname', 'doc_type_id', 'num_doc', 'address', 'phone', 'password' (opcional)]
 * @return string Mensaje de confirmación o -1 en caso de fallo.
 */
function UpdateUserService($data) {
    global $pdo;

    // Validación obligatoria de WS-Security en cabecera
    if (!wsse_authenticate()) {
        return "-1";
    }

    if (!$pdo) {
        return "-1";
    }

    $data = function_exists('utf8_converter') ? utf8_converter($data) : $data;

    if (!is_array($data) || empty($data)) {
        return "-1";
    }

    // Validación de ID y campos obligatorios
    if (
        !isset($data['id']) || !is_numeric($data['id']) || intval($data['id']) <= 0 ||
        !isset($data['user_name']) || trim($data['user_name']) === '' ||
        !isset($data['lastname']) || trim($data['lastname']) === '' ||
        !isset($data['doc_type_id']) || !is_numeric($data['doc_type_id']) || intval($data['doc_type_id']) <= 0 ||
        !isset($data['num_doc']) || trim($data['num_doc']) === ''
    ) {
        return "-1";
    }

    try {
        $id = intval($data['id']);

        // Verificar existencia previa del usuario
        $check = $pdo->prepare("SELECT id FROM user WHERE id = :id");
        $check->execute(array(':id' => $id));
        if ($check->rowCount() === 0) {
            return "-1";
        }

        // Si se incluye contraseña, encriptarla con Bcrypt
        $updatePassSql = "";
        $hashedPassword = null;
        if (isset($data['password']) && trim($data['password']) !== '') {
            $hashedPassword = password_hash(trim($data['password']), PASSWORD_DEFAULT);
            $updatePassSql = ", password = :password";
        }

        $sql = "UPDATE user SET
                    user_name   = :user_name,
                    lastname    = :lastname,
                    doc_type_id = :doc_type_id,
                    num_doc     = :num_doc,
                    address     = :address,
                    phone       = :phone
                    $updatePassSql
                WHERE id = :id";

        $stmt = $pdo->prepare($sql);
        $user_name   = trim($data['user_name']);
        $lastname    = trim($data['lastname']);
        $doc_type_id = intval($data['doc_type_id']);
        $num_doc     = trim($data['num_doc']);
        $address     = isset($data['address']) ? trim($data['address']) : '';
        $phone       = isset($data['phone']) ? trim($data['phone']) : '';

        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':user_name', $user_name);
        $stmt->bindParam(':lastname', $lastname);
        $stmt->bindParam(':doc_type_id', $doc_type_id);
        $stmt->bindParam(':num_doc', $num_doc);
        $stmt->bindParam(':address', $address);
        $stmt->bindParam(':phone', $phone);

        if ($hashedPassword !== null) {
            $stmt->bindParam(':password', $hashedPassword);
        }

        $stmt->execute();
        return "Usuario actualizado correctamente";

    } catch (PDOException $e) {
        return "-1";
    }
}

/**
 * 3. Eliminar Usuario (PROTEGIDO POR WS-SECURITY)
 * Valida la cabecera WS-Security. Si falta o es incorrecta, se bloquea con -1.
 *
 * @param int|array $id ID del usuario
 * @return string Mensaje de confirmación o -1 en caso de fallo.
 */
function DeleteUserService($id) {
    global $pdo;

    // Validación obligatoria de WS-Security en cabecera
    if (!wsse_authenticate()) {
        return "-1";
    }

    if (!$pdo) {
        return "-1";
    }

    if (is_array($id)) {
        $id = $id['id'] ?? null;
    }

    // Validación de ID numérico y mayor que 0
    if (empty($id) || !is_numeric($id) || intval($id) <= 0) {
        return "-1";
    }

    try {
        $userId = intval($id);

        // Verificar si el usuario existe antes de borrar
        $check = $pdo->prepare("SELECT id FROM user WHERE id = :id");
        $check->execute(array(':id' => $userId));
        if ($check->rowCount() === 0) {
            return "-1";
        }

        $stmt = $pdo->prepare("DELETE FROM user WHERE id = :id");
        $stmt->bindParam(':id', $userId);
        $stmt->execute();

        return "Usuario eliminado correctamente";

    } catch (PDOException $e) {
        return "-1";
    }
}

/**
 * 4. Seleccionar Usuario por ID (PROTEGIDO POR WS-SECURITY)
 * Valida la cabecera WS-Security. Si falta o es incorrecta, se bloquea retornando -1.
 * Seguridad: Excluye de la respuesta las contraseñas y tokens.
 *
 * @param int|array $id ID del usuario
 * @return array|soapval Datos del usuario o -1 si falla / no existe.
 */
function SelectUserService($id) {
    global $pdo;

    // Validación obligatoria de WS-Security en cabecera
    if (!wsse_authenticate()) {
        return user_soap_error();
    }

    if (!$pdo) {
        return user_soap_error();
    }

    if (is_array($id)) {
        $id = $id['id'] ?? null;
    }

    // Validación de ID
    if (empty($id) || !is_numeric($id) || intval($id) <= 0) {
        return user_soap_error();
    }

    try {
        // Exclusión estricta de 'password', 'token' y 'token_date' por seguridad
        $stmt = $pdo->prepare("SELECT id, user_name, lastname, doc_type_id, num_doc, address, phone, created_date FROM user WHERE id = :id");
        $stmt->execute(array(':id' => intval($id)));
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return user_soap_error();
        }

        return array(
            'id'           => (int)$user['id'],
            'user_name'    => (string)$user['user_name'],
            'lastname'     => (string)$user['lastname'],
            'doc_type_id'  => (int)$user['doc_type_id'],
            'num_doc'      => (string)$user['num_doc'],
            'address'      => (string)($user['address'] ?? ''),
            'phone'        => (string)($user['phone'] ?? ''),
            'created_date' => (string)($user['created_date'] ?? '')
        );

    } catch (PDOException $e) {
        return user_soap_error();
    }
}

/**
 * 5. Listar Todos los Usuarios (PROTEGIDO POR WS-SECURITY)
 * Valida la cabecera WS-Security. Si falta o es incorrecta, se bloquea retornando -1.
 * Seguridad: Excluye de la lista las contraseñas y tokens.
 *
 * @param mixed $param Parámetro opcional
 * @return array|soapval Lista de usuarios o -1 si está vacía / falla.
 */
function ListUsersService($param = null) {
    global $pdo;

    // Validación obligatoria de WS-Security en cabecera
    if (!wsse_authenticate()) {
        return user_soap_error();
    }

    if (!$pdo) {
        return user_soap_error();
    }

    try {
        // Exclusión estricta de 'password', 'token' y 'token_date'
        $stmt = $pdo->query("SELECT id, user_name, lastname, doc_type_id, num_doc, address, phone, created_date FROM user ORDER BY id ASC");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($users)) {
            return user_soap_error();
        }

        $result = array();
        foreach ($users as $u) {
            $result[] = array(
                'id'           => (int)$u['id'],
                'user_name'    => (string)$u['user_name'],
                'lastname'     => (string)$u['lastname'],
                'doc_type_id'  => (int)$u['doc_type_id'],
                'num_doc'      => (string)$u['num_doc'],
                'address'      => (string)($u['address'] ?? ''),
                'phone'        => (string)($u['phone'] ?? ''),
                'created_date' => (string)($u['created_date'] ?? '')
            );
        }

        return $result;

    } catch (PDOException $e) {
        return user_soap_error();
    }
}

// ==========================================================
// Alias descriptivos en Español para interoperabilidad
// ==========================================================
function insertarUsuario($data) {
    return InsertUserService($data);
}

function actualizarUsuario($data) {
    return UpdateUserService($data);
}

function eliminarUsuario($id) {
    return DeleteUserService($id);
}

function seleccionarUsuario($id) {
    return SelectUserService($id);
}

function listarUsuarios($param = null) {
    return ListUsersService($param);
}
