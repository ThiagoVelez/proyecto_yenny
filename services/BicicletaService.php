<?php
// ==========================================================
// 6.1 Servicios de Bicicleta
// ==========================================================

/**
 * Operación SOAP: registrarBicicleta
 * Criterios: Almacena en MySQL, código debe ser único.
 */
function registrarBicicleta($data) {
    global $pdo;

    if (!$pdo) {
        return "Error: La conexión a la base de datos no está disponible.";
    }

    try {
        $data = function_exists('utf8_converter') ? utf8_converter($data) : $data;

        if (empty($data['codigo']) || empty($data['tipo']) || empty($data['tarifa'])) {
            return "Error: Código, tipo y tarifa son campos obligatorios.";
        }

        // Criterio: El código de bicicleta debe ser único
        $check = $pdo->prepare("SELECT id FROM bicicletas WHERE codigo = :codigo");
        $check->bindParam(':codigo', $data['codigo']);
        $check->execute();
        if ($check->fetch()) {
            return "Error: Ya existe una bicicleta con el código '" . $data['codigo'] . "'.";
        }

        $sql = "INSERT INTO bicicletas (codigo, tipo, tarifa, estado, created_at)
                VALUES (:codigo, :tipo, :tarifa, :estado, NOW())";
        
        $stmt = $pdo->prepare($sql);
        $estado = !empty($data['estado']) ? $data['estado'] : 'Disponible';

        $stmt->bindParam(':codigo', $data['codigo']);
        $stmt->bindParam(':tipo', $data['tipo']);
        $stmt->bindParam(':tarifa', $data['tarifa']);
        $stmt->bindParam(':estado', $estado);

        $stmt->execute();
        return "Se ha registrado la bicicleta exitosamente con código '" . $data['codigo'] . "'.";

    } catch (PDOException $e) {
        return "Error: " . $e->getMessage();
    }
}

/**
 * Operación SOAP: consultarBicicleta
 * Criterio: Debe poder consultarse una bicicleta determinada por su código.
 */
function consultarBicicleta($codigo) {
    global $pdo;

    if (!$pdo) {
        return "Error: La conexión a la base de datos no está disponible.";
    }

    try {
        $codigo = function_exists('utf8_converter') ? utf8_converter($codigo) : $codigo;
        $stmt = $pdo->prepare("SELECT id, codigo, tipo, tarifa, estado, created_at FROM bicicletas WHERE codigo = :codigo");
        $stmt->bindParam(':codigo', $codigo);
        $stmt->execute();
        $bici = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$bici) {
            return function_exists('json_utf8_response')
                ? json_utf8_response(["status" => "error", "mensaje" => "No se encontró ninguna bicicleta con el código '$codigo'."])
                : json_encode(["status" => "error", "mensaje" => "No se encontró ninguna bicicleta con el código '$codigo'."]);
        }

        return function_exists('json_utf8_response')
            ? json_utf8_response(["status" => "success", "data" => $bici])
            : json_encode(["status" => "success", "data" => $bici], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    } catch (PDOException $e) {
        return function_exists('json_utf8_response')
            ? json_utf8_response(["status" => "error", "mensaje" => $e->getMessage()])
            : json_encode(["status" => "error", "mensaje" => $e->getMessage()]);
    }
}

/**
 * Operación SOAP: listarBicicletas
 * Criterio: Debe existir una operación para listar bicicletas.
 */
function listarBicicletas() {
    global $pdo;

    if (!$pdo) {
        return function_exists('json_utf8_response')
            ? json_utf8_response(["status" => "error", "mensaje" => "Error de conexión a la base de datos."])
            : json_encode(["status" => "error", "mensaje" => "Error de conexión a la base de datos."]);
    }

    try {
        $stmt = $pdo->query("SELECT id, codigo, tipo, tarifa, estado, created_at FROM bicicletas ORDER BY id ASC");
        $bicis = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return function_exists('json_utf8_response')
            ? json_utf8_response(["status" => "success", "data" => $bicis])
            : json_encode(["status" => "success", "data" => $bicis], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    } catch (PDOException $e) {
        return function_exists('json_utf8_response')
            ? json_utf8_response(["status" => "error", "mensaje" => $e->getMessage()])
            : json_encode(["status" => "error", "mensaje" => $e->getMessage()]);
    }
}

/**
 * Operación SOAP: actualizarBicicleta
 * Criterio: Debe poder modificarse tipo, tarifa o estado.
 */
function actualizarBicicleta($data) {
    global $pdo;

    if (!$pdo) {
        return "Error: La conexión a la base de datos no está disponible.";
    }

    try {
        $data = function_exists('utf8_converter') ? utf8_converter($data) : $data;

        if (empty($data['codigo'])) {
            return "Error: Debe proporcionar el código de la bicicleta a actualizar.";
        }

        // Verificar existencia
        $stmt = $pdo->prepare("SELECT id, tipo, tarifa, estado FROM bicicletas WHERE codigo = :codigo");
        $stmt->bindParam(':codigo', $data['codigo']);
        $stmt->execute();
        $bici = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$bici) {
            return "Error: No existe una bicicleta con el código '" . $data['codigo'] . "'.";
        }

        // Mantener valores previos si no se envían nuevos
        $tipo = !empty($data['tipo']) ? $data['tipo'] : $bici['tipo'];
        $tarifa = !empty($data['tarifa']) ? $data['tarifa'] : $bici['tarifa'];
        $estado = !empty($data['estado']) ? $data['estado'] : $bici['estado'];

        $sql = "UPDATE bicicletas SET tipo = :tipo, tarifa = :tarifa, estado = :estado, updated_at = NOW() WHERE codigo = :codigo";
        $update = $pdo->prepare($sql);
        $update->bindParam(':tipo', $tipo);
        $update->bindParam(':tarifa', $tarifa);
        $update->bindParam(':estado', $estado);
        $update->bindParam(':codigo', $data['codigo']);

        $update->execute();
        return "Bicicleta '" . $data['codigo'] . "' actualizada correctamente. Tipo: $tipo, Tarifa: $$tarifa, Estado: $estado.";

    } catch (PDOException $e) {
        return "Error: " . $e->getMessage();
    }
}

