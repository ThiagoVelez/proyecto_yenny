<?php
// ==========================================================
// PROYECTO: Servidor SOAP Modular
// MÓDULO: Sistema de Token Criptográfico (Especificación de Clase)
// ARCHIVO: token/token.php
// ==========================================================

/**
 * Generación del Token Criptográfico:
 * Implementa exactamente la función indicada en la diapositiva de la profesora:
 * $token = bin2hex(random_bytes(32));
 * 
 * random_bytes(32) genera 32 bytes criptográficamente y 
 * bin2hex() los convierte en una cadena legible de 64 caracteres hexadecimales.
 *
 * @return string Token de 64 caracteres hexadecimales
 */
function generate_crypto_token() {
    $token = bin2hex(random_bytes(32));
    return $token;
}

/**
 * Valida si un token existe y se encuentra activo en la base de datos.
 *
 * @param PDO $pdo Conexión a la base de datos
 * @param string $token Token de 64 caracteres
 * @return array|false Retorna los datos del usuario si el token es válido y activo, o false si no
 */
function validate_token_in_db($pdo, $token) {
    if (!$pdo || empty($token) || strlen($token) !== 64) {
        return false;
    }

    try {
        $stmt = $pdo->prepare("SELECT id, user_name, token, token_date FROM user WHERE token = :token LIMIT 1");
        $stmt->execute(array(':token' => trim($token)));
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && !empty($user['token'])) {
            return $user;
        }

        return false;
    } catch (PDOException $e) {
        return false;
    }
}
