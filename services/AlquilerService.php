<?php
// ==========================================================
// 6.3 Servicios de Alquiler
// ==========================================================

/**
 * Operación SOAP: registrarAlquiler
 * Criterios:
 * 1. Solo pueden alquilarse bicicletas disponibles.
 * 2. Una bicicleta alquilada debe cambiar automáticamente de estado.
 * 3. Cada alquiler debe quedar relacionado con un cliente.
 */
function registrarAlquiler($data) {
    global $pdo;

    if (!$pdo) {
        return "Error: La conexión a la base de datos no está disponible.";
    }

    try {
        if (empty($data['codigo_bicicleta']) || empty($data['documento_cliente'])) {
            return "Error: Debe proporcionar el código de la bicicleta y el documento del cliente.";
        }

        // 1. Validar que la bicicleta exista y verificar disponibilidad
        $stmtBici = $pdo->prepare("SELECT id, codigo, estado, tarifa FROM bicicletas WHERE codigo = :codigo");
        $stmtBici->bindParam(':codigo', $data['codigo_bicicleta']);
        $stmtBici->execute();
        $bici = $stmtBici->fetch(PDO::FETCH_ASSOC);

        if (!$bici) {
            return "Error: La bicicleta '" . $data['codigo_bicicleta'] . "' no existe.";
        }

        // Criterio: Solo pueden alquilarse bicicletas disponibles
        if ($bici['estado'] !== 'Disponible') {
            return "Error: La bicicleta '" . $data['codigo_bicicleta'] . "' NO está disponible (Estado actual: " . $bici['estado'] . ").";
        }

        // 2. Validar que el cliente exista (Criterio: Cada alquiler debe quedar relacionado con un cliente)
        $stmtCli = $pdo->prepare("SELECT id, documento, nombre FROM clientes WHERE documento = :documento");
        $stmtCli->bindParam(':documento', $data['documento_cliente']);
        $stmtCli->execute();
        $cliente = $stmtCli->fetch(PDO::FETCH_ASSOC);

        if (!$cliente) {
            return "Error: El cliente con documento '" . $data['documento_cliente'] . "' no existe. Debe registrarlo primero.";
        }

        // 3. Registrar el alquiler en la base de datos
        $sqlAlq = "INSERT INTO alquileres (bicicleta_id, cliente_id, fecha_inicio, estado, created_at)
                   VALUES (:bici_id, :cli_id, NOW(), 'Activo', NOW())";
        $stmtAlq = $pdo->prepare($sqlAlq);
        $stmtAlq->bindParam(':bici_id', $bici['id']);
        $stmtAlq->bindParam(':cli_id', $cliente['id']);
        $stmtAlq->execute();

        // 4. Criterio: Una bicicleta alquilada debe cambiar automáticamente de estado
        $sqlBici = "UPDATE bicicletas SET estado = 'Alquilada', updated_at = NOW() WHERE id = :id";
        $updateBici = $pdo->prepare($sqlBici);
        $updateBici->bindParam(':id', $bici['id']);
        $updateBici->execute();

        return "Alquiler registrado exitosamente. Cliente: " . $cliente['nombre'] . " (Doc: " . $cliente['documento'] . ") | Bicicleta: " . $bici['codigo'] . " (" . $bici['estado'] . " -> Alquilada).";

    } catch (PDOException $e) {
        return "Error: " . $e->getMessage();
    }
}

/**
 * Operación SOAP: consultarAlquileres
 * Criterio: Consultar todos los alquileres con datos del cliente y bicicleta.
 */
function consultarAlquileres() {
    global $pdo;

    if (!$pdo) {
        return function_exists('json_utf8_response')
            ? json_utf8_response(["status" => "error", "mensaje" => "Error de conexión a la base de datos."])
            : json_encode(["status" => "error", "mensaje" => "Error de conexión a la base de datos."]);
    }

    try {
        $sql = "SELECT a.id, 
                       b.codigo AS bicicleta_codigo, 
                       b.tipo AS bicicleta_tipo, 
                       b.tarifa AS bicicleta_tarifa,
                       c.documento AS cliente_documento, 
                       c.nombre AS cliente_nombre, 
                       c.telefono AS cliente_telefono,
                       a.fecha_inicio, 
                       a.fecha_fin, 
                       a.total, 
                       a.estado
                FROM alquileres a
                INNER JOIN bicicletas b ON a.bicicleta_id = b.id
                INNER JOIN clientes c ON a.cliente_id = c.id
                ORDER BY a.id DESC";
        $stmt = $pdo->query($sql);
        $alquileres = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return function_exists('json_utf8_response')
            ? json_utf8_response(["status" => "success", "data" => $alquileres])
            : json_encode(["status" => "success", "data" => $alquileres], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    } catch (PDOException $e) {
        return function_exists('json_utf8_response')
            ? json_utf8_response(["status" => "error", "mensaje" => $e->getMessage()])
            : json_encode(["status" => "error", "mensaje" => $e->getMessage()]);
    }
}

/**
 * Operación SOAP adicional: finalizarAlquiler
 * Permite cerrar el alquiler, liquidar monto y volver la bicicleta a 'Disponible'
 */
function finalizarAlquiler($codigo_bicicleta) {
    global $pdo;

    if (!$pdo) {
        return "Error: La conexión a la base de datos no está disponible.";
    }

    try {
        // 1. Buscar la bicicleta
        $stmtBici = $pdo->prepare("SELECT id, tarifa FROM bicicletas WHERE codigo = :codigo");
        $stmtBici->bindParam(':codigo', $codigo_bicicleta);
        $stmtBici->execute();
        $bici = $stmtBici->fetch(PDO::FETCH_ASSOC);

        if (!$bici) {
            return "Error: La bicicleta '$codigo_bicicleta' no existe.";
        }

        // 2. Buscar el alquiler activo
        $stmtAlq = $pdo->prepare("SELECT id, fecha_inicio FROM alquileres WHERE bicicleta_id = :bici_id AND estado = 'Activo' ORDER BY id DESC LIMIT 1");
        $stmtAlq->bindParam(':bici_id', $bici['id']);
        $stmtAlq->execute();
        $alquiler = $stmtAlq->fetch(PDO::FETCH_ASSOC);

        if (!$alquiler) {
            return "Error: No se encontró ningún alquiler activo para la bicicleta '$codigo_bicicleta'.";
        }

        // 3. Calcular horas y total a pagar (mínimo 1 hora)
        $inicio = new DateTime($alquiler['fecha_inicio']);
        $fin = new DateTime();
        $diff = $inicio->diff($fin);
        $horas = ($diff->days * 24) + $diff->h + ($diff->i > 0 ? 1 : 0);
        if ($horas < 1) {
            $horas = 1;
        }

        $total = $horas * floatval($bici['tarifa']);

        // 4. Actualizar el alquiler a 'Finalizado'
        $sqlAlq = "UPDATE alquileres SET fecha_fin = NOW(), total = :total, estado = 'Finalizado', updated_at = NOW() WHERE id = :id";
        $updateAlq = $pdo->prepare($sqlAlq);
        $updateAlq->bindParam(':total', $total);
        $updateAlq->bindParam(':id', $alquiler['id']);
        $updateAlq->execute();

        // 5. Liberar la bicicleta (estado = 'Disponible')
        $sqlBici = "UPDATE bicicletas SET estado = 'Disponible', updated_at = NOW() WHERE id = :id";
        $updateBici = $pdo->prepare($sqlBici);
        $updateBici->bindParam(':id', $bici['id']);
        $updateBici->execute();

        return "Alquiler finalizado con éxito. Horas cobradas: $horas. Total a pagar: $" . number_format($total, 2) . ". Bicicleta disponible nuevamente.";

    } catch (PDOException $e) {
        return "Error: " . $e->getMessage();
    }
}
