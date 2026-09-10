<?php
// ==========================================================
// PROYECTO: Servidor SOAP Modular
// MÓDULO: Lógica de Servicios - Autenticación Criptográfica
// ARCHIVO: services/AuthService.php
// ==========================================================

require_once __DIR__ . '/../token/token.php';
require_once __DIR__ . '/../token/ws_security.php';

/**
 * 1. Servicio LoginService
 * - Recibe user_name y password.
 * - Verifica la contraseña encriptada (Bcrypt y SHA-256 de MySQL).
 * - Genera el token con random_bytes(32) mediante generate_crypto_token().
 * - Lo almacena en la tabla user junto con la fecha y hora (NOW()).
 * - Devuelve el token de 64 caracteres al cliente (o -1 si falla).
 *
 * @param string|array $user_name Nombre de usuario o arreglo de datos
 * @param string|null  $password  Contraseña en texto plano
 * @return string Token criptográfico de 64 caracteres o -1 si falla
 */
function LoginService($user_name, $password = null) {
    global $pdo;

    if (!$pdo) {
        return "-1";
    }

    if (is_array($user_name)) {
        $password = $user_name['password'] ?? ($user_name['pass'] ?? $password);
        $user_name = $user_name['user_name'] ?? ($user_name['username'] ?? '');
    }

    $user_name = trim((string)$user_name);
    $password  = (string)$password;

    if ($user_name === '' || $password === '') {
        return "-1";
    }

    try {
        $stmt = $pdo->prepare("SELECT id, user_name, password FROM user WHERE user_name = :user_name LIMIT 1");
        $stmt->execute(array(':user_name' => $user_name));
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return "-1";
        }

        // Verificar contraseña con soporte Bcrypt (password_verify) y SHA-256 de MySQL (SHA2)
        if (!verify_user_password($password, $user['password'])) {
            return "-1";
        }

        // Generar token: $token = bin2hex(random_bytes(32));
        $token = generate_crypto_token();

        // Almacenar en la tabla user junto con la fecha y hora (NOW())
        $updateStmt = $pdo->prepare("UPDATE user SET token = :token, token_date = NOW() WHERE id = :id");
        $updateStmt->execute(array(
            ':token' => $token,
            ':id'    => $user['id']
        ));

        return $token;

    } catch (PDOException $e) {
        return "-1";
    }
}

/**
 * 2. Servicio ValidateTokenService
 * Valida si un token existe y está activo en la base de datos.
 *
 * @param string|array $token Token de 64 caracteres
 * @return string "1" si es válido y activo, o "-1" si no existe / es inválido
 */
function ValidateTokenService($token) {
    global $pdo;

    if (!$pdo) {
        return "-1";
    }

    if (is_array($token)) {
        $token = $token['token'] ?? null;
    }

    $token = trim((string)$token);

    if ($token === '' || strlen($token) !== 64) {
        return "-1";
    }

    try {
        $user = validate_token_in_db($pdo, $token);
        return $user ? "1" : "-1";
    } catch (PDOException $e) {
        return "-1";
    }
}

// ==========================================================
// Alias en español para interoperabilidad
// ==========================================================
function login($user_name, $password = null) {
    return LoginService($user_name, $password);
}

function validarToken($token) {
    return ValidateTokenService($token);
}
