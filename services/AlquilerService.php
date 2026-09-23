<?php
// ==========================================================
// 6.3 Servicios de Alquiler (con Excepciones Controladas)
// ==========================================================

/**
 * Operación SOAP: registrarAlquiler
 * Criterios:
 * 1. Solo pueden alquilarse bicicletas disponibles.
 * 2. Una bicicleta alquilada debe cambiar automáticamente de estado.
 * 3. Cada alquiler debe quedar relacionado con un cliente.
 * Manejo de Excepciones Controladas: ValidationException, NotFoundException, BusinessRuleException, DatabaseException.
 */
function registrarAlquiler($data) {
    global $pdo;

    try {
        if (empty($data['codigo_bicicleta']) || empty($data['documento_cliente'])) {
            throw new ValidationException("Debe proporcionar el código de la bicicleta y el documento del cliente.", "PARAMETROS_INCOMPLETOS");
        }

        if (!$pdo) {
            throw new DatabaseException("La conexión a la base de datos no está disponible.");
        }

        // 1. Validar que la bicicleta exista y verificar disponibilidad
        $stmtBici = $pdo->prepare("SELECT id, codigo, estado, tarifa FROM bicicletas WHERE codigo = :codigo");
        $stmtBici->bindParam(':codigo', $data['codigo_bicicleta']);
        $stmtBici->execute();
        $bici = $stmtBici->fetch(PDO::FETCH_ASSOC);

        if (!$bici) {
            throw new NotFoundException("La bicicleta '" . $data['codigo_bicicleta'] . "' no existe en el sistema.", "BICICLETA_NO_ENCONTRADA");
        }

        // Criterio: Solo pueden alquilarse bicicletas disponibles
        if ($bici['estado'] !== 'Disponible') {
            throw new BusinessRuleException("La bicicleta '" . $data['codigo_bicicleta'] . "' NO está disponible para alquiler (Estado actual: " . $bici['estado'] . ").", "BICICLETA_NO_DISPONIBLE");
        }

        // 2. Validar que el cliente exista (Criterio: Cada alquiler debe quedar relacionado con un cliente)
        $stmtCli = $pdo->prepare("SELECT id, documento, nombre FROM clientes WHERE documento = :documento");
        $stmtCli->bindParam(':documento', $data['documento_cliente']);
        $stmtCli->execute();
        $cliente = $stmtCli->fetch(PDO::FETCH_ASSOC);

        if (!$cliente) {
            throw new NotFoundException("El cliente con documento '" . $data['documento_cliente'] . "' no existe. Debe registrarlo previamente.", "CLIENTE_NO_ENCONTRADO");
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

    } catch (Throwable $e) {
        return handle_service_exception($e, 'registrarAlquiler');
    }
}

/**
 * Operación SOAP: consultarAlquileres
 * Criterio: Consultar todos los alquileres con datos del cliente y bicicleta.
 * Manejo de Excepciones Controladas: DatabaseException.
 */
function consultarAlquileres() {
    global $pdo;

    try {
        if (!$pdo) {
            throw new DatabaseException("La conexión a la base de datos no está disponible.");
        }

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

        $result = array();
        foreach ($alquileres as $a) {
            $result[] = array(
                'id'                => (int)$a['id'],
                'bicicleta_codigo'  => (string)$a['bicicleta_codigo'],
                'bicicleta_tipo'    => (string)$a['bicicleta_tipo'],
                'bicicleta_tarifa'  => number_format((float)$a['bicicleta_tarifa'], 2, '.', ''),
                'cliente_documento' => (string)$a['cliente_documento'],
                'cliente_nombre'    => (string)$a['cliente_nombre'],
                'cliente_telefono'  => (string)$a['cliente_telefono'],
                'fecha_inicio'      => (string)$a['fecha_inicio'],
                'fecha_fin'         => (string)($a['fecha_fin'] ?? ''),
                'total'             => $a['total'] !== null ? number_format((float)$a['total'], 2, '.', '') : '0.00',
                'estado'            => (string)$a['estado']
            );
        }
        return $result;

    } catch (Throwable $e) {
        return handle_service_exception($e, 'consultarAlquileres');
    }
}

/**
 * Alias de consultarAlquileres para listar en formato XML
 */
function listarAlquileres() {
    return consultarAlquileres();
}

/**
 * Operación SOAP adicional: finalizarAlquiler
 * Permite cerrar el alquiler, liquidar monto y volver la bicicleta a 'Disponible'.
 * Manejo de Excepciones Controladas: ValidationException, NotFoundException, BusinessRuleException, DatabaseException.
 */
function finalizarAlquiler($codigo_bicicleta) {
    global $pdo;

    try {
        if (empty($codigo_bicicleta)) {
            throw new ValidationException("Debe proporcionar el código de la bicicleta a finalizar.", "CODIGO_REQUERIDO");
        }

        if (!$pdo) {
            throw new DatabaseException("La conexión a la base de datos no está disponible.");
        }

        // 1. Buscar la bicicleta
        $stmtBici = $pdo->prepare("SELECT id, tarifa FROM bicicletas WHERE codigo = :codigo");
        $stmtBici->bindParam(':codigo', $codigo_bicicleta);
        $stmtBici->execute();
        $bici = $stmtBici->fetch(PDO::FETCH_ASSOC);

        if (!$bici) {
            throw new NotFoundException("La bicicleta '$codigo_bicicleta' no existe.", "BICICLETA_NO_ENCONTRADA");
        }

        // 2. Buscar el alquiler activo
        $stmtAlq = $pdo->prepare("SELECT id, fecha_inicio FROM alquileres WHERE bicicleta_id = :bici_id AND estado = 'Activo' ORDER BY id DESC LIMIT 1");
        $stmtAlq->bindParam(':bici_id', $bici['id']);
        $stmtAlq->execute();
        $alquiler = $stmtAlq->fetch(PDO::FETCH_ASSOC);

        if (!$alquiler) {
            throw new BusinessRuleException("No se encontró ningún alquiler activo para la bicicleta '$codigo_bicicleta'.", "ALQUILER_NO_ACTIVO");
        }

        // 3. Calcular horas y total a pagar (mínimo 1 hora)
        $inicio = new DateTime($alquiler['fecha_inicio']);
        $fin = new DateTime();
        $diff = $inicio->diff($fin);
        $horas = ($diff->days * 24) + $diff->h + ($diff->i > 0 ? 1 : 0);
        if ($horas < 1) {
            $horas = 1;
        }

        $tarifaBase = floatval($bici['tarifa']);
        $total = round($horas * $tarifaBase, 2);
        $totalDecimal = number_format($total, 2, '.', '');

        // 4. Actualizar el alquiler a 'Finalizado' con precisión decimal
        $sqlAlq = "UPDATE alquileres SET fecha_fin = NOW(), total = :total, estado = 'Finalizado', updated_at = NOW() WHERE id = :id";
        $updateAlq = $pdo->prepare($sqlAlq);
        $updateAlq->bindParam(':total', $totalDecimal);
        $updateAlq->bindParam(':id', $alquiler['id']);
        $updateAlq->execute();

        // 5. Liberar la bicicleta (estado = 'Disponible')
        $sqlBici = "UPDATE bicicletas SET estado = 'Disponible', updated_at = NOW() WHERE id = :id";
        $updateBici = $pdo->prepare($sqlBici);
        $updateBici->bindParam(':id', $bici['id']);
        $updateBici->execute();

        return "Alquiler finalizado con éxito. Horas cobradas: $horas. Total a pagar: $" . number_format($total, 2) . ". Bicicleta disponible nuevamente.";

    } catch (Throwable $e) {
        return handle_service_exception($e, 'finalizarAlquiler');
    }
}
