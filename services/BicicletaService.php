<?php
// ==========================================================
// 6.1 Servicios de Bicicleta (con Excepciones Controladas)
// ==========================================================

/**
 * Operación SOAP: registrarBicicleta
 * Criterios: Almacena en MySQL, código debe ser único, tarifa válida.
 * Manejo de Excepciones Controladas: ValidationException, BusinessRuleException, DatabaseException.
 */
function registrarBicicleta($data) {
    global $pdo;

    try {
        $data = function_exists('utf8_converter') ? utf8_converter($data) : $data;

        // 1. Validaciones de cliente (fail-fast)
        if (empty($data['codigo']) || empty($data['tipo'])) {
            throw new ValidationException("Código y tipo son campos obligatorios.", "CAMPOS_OBLIGATORIOS_FALTANTES");
        }

        // Criterio y Retroalimentación: Validar tarifa como valor numérico mayor o igual a cero
        if (!isset($data['tarifa']) || $data['tarifa'] === '' || !is_numeric($data['tarifa']) || floatval($data['tarifa']) < 0) {
            throw new ValidationException("La tarifa es obligatoria y debe ser un valor numérico mayor o igual a 0.", "TARIFA_INVALIDA");
        }

        $tarifa = number_format(round(floatval($data['tarifa']), 2), 2, '.', '');

        // 2. Validación de disponibilidad de base de datos
        if (!$pdo) {
            throw new DatabaseException("La conexión a la base de datos no está disponible.");
        }

        // 3. Regla de negocio: El código de bicicleta debe ser único
        $check = $pdo->prepare("SELECT id FROM bicicletas WHERE codigo = :codigo");
        $check->bindParam(':codigo', $data['codigo']);
        $check->execute();
        if ($check->fetch()) {
            throw new BusinessRuleException("Ya existe una bicicleta con el código '" . $data['codigo'] . "'.", "CODIGO_BICICLETA_DUPLICADO");
        }

        $sql = "INSERT INTO bicicletas (codigo, tipo, tarifa, estado, created_at)
                VALUES (:codigo, :tipo, :tarifa, :estado, NOW())";
        
        $stmt = $pdo->prepare($sql);
        $estado = !empty($data['estado']) ? $data['estado'] : 'Disponible';

        $stmt->bindParam(':codigo', $data['codigo']);
        $stmt->bindParam(':tipo', $data['tipo']);
        $stmt->bindParam(':tarifa', $tarifa);
        $stmt->bindParam(':estado', $estado);

        $stmt->execute();
        return "Se ha registrado la bicicleta exitosamente con código '" . $data['codigo'] . "'.";

    } catch (Throwable $e) {
        return handle_service_exception($e, 'registrarBicicleta');
    }
}

/**
 * Operación SOAP: consultarBicicleta
 * Criterio: Debe poder consultarse una bicicleta determinada por su código.
 * Manejo de Excepciones Controladas: NotFoundException, ValidationException.
 */
function consultarBicicleta($codigo) {
    global $pdo;

    try {
        $codigo = function_exists('utf8_converter') ? utf8_converter($codigo) : $codigo;
        if (empty($codigo)) {
            throw new ValidationException("Debe proporcionar un código de bicicleta para la consulta.", "CODIGO_REQUERIDO");
        }

        if (!$pdo) {
            throw new DatabaseException("La conexión a la base de datos no está disponible.");
        }

        $stmt = $pdo->prepare("SELECT id, codigo, tipo, tarifa, estado, created_at FROM bicicletas WHERE codigo = :codigo");
        $stmt->bindParam(':codigo', $codigo);
        $stmt->execute();
        $bici = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$bici) {
            throw new NotFoundException("No se encontró ninguna bicicleta con el código '" . $codigo . "'.", "BICICLETA_NO_ENCONTRADA");
        }

        return array(
            'id'         => (int)$bici['id'],
            'codigo'     => (string)$bici['codigo'],
            'tipo'       => (string)$bici['tipo'],
            'tarifa'     => number_format((float)$bici['tarifa'], 2, '.', ''),
            'estado'     => (string)$bici['estado'],
            'created_at' => (string)$bici['created_at']
        );

    } catch (Throwable $e) {
        return handle_service_exception($e, 'consultarBicicleta');
    }
}

/**
 * Operación SOAP: listarBicicletas
 * Criterio: Debe existir una operación para listar bicicletas en formato XML.
 * Manejo de Excepciones Controladas: DatabaseException.
 */
function listarBicicletas() {
    global $pdo;

    try {
        if (!$pdo) {
            throw new DatabaseException("La conexión a la base de datos no está disponible.");
        }

        $stmt = $pdo->query("SELECT id, codigo, tipo, tarifa, estado, created_at FROM bicicletas ORDER BY id ASC");
        $bicis = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $result = array();
        foreach ($bicis as $b) {
            $result[] = array(
                'id'         => (int)$b['id'],
                'codigo'     => (string)$b['codigo'],
                'tipo'       => (string)$b['tipo'],
                'tarifa'     => number_format((float)$b['tarifa'], 2, '.', ''),
                'estado'     => (string)$b['estado'],
                'created_at' => (string)$b['created_at']
            );
        }
        return $result;

    } catch (Throwable $e) {
        return handle_service_exception($e, 'listarBicicletas');
    }
}

/**
 * Operación SOAP: actualizarBicicleta
 * Criterio: Debe poder modificarse tipo, tarifa o estado.
 * Manejo de Excepciones Controladas: ValidationException, NotFoundException.
 */
function actualizarBicicleta($data) {
    global $pdo;

    try {
        $data = function_exists('utf8_converter') ? utf8_converter($data) : $data;

        if (empty($data['codigo'])) {
            throw new ValidationException("Debe proporcionar el código de la bicicleta a actualizar.", "CODIGO_REQUERIDO");
        }

        // Validación de tarifa numérica si se proporciona
        $tarifa = null;
        if (isset($data['tarifa']) && $data['tarifa'] !== '') {
            if (!is_numeric($data['tarifa']) || floatval($data['tarifa']) < 0) {
                throw new ValidationException("La tarifa debe ser un valor numérico mayor o igual a 0.", "TARIFA_INVALIDA");
            }
            $tarifa = number_format(round(floatval($data['tarifa']), 2), 2, '.', '');
        }

        if (!$pdo) {
            throw new DatabaseException("La conexión a la base de datos no está disponible.");
        }

        // Verificar existencia
        $stmt = $pdo->prepare("SELECT id, tipo, tarifa, estado FROM bicicletas WHERE codigo = :codigo");
        $stmt->bindParam(':codigo', $data['codigo']);
        $stmt->execute();
        $bici = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$bici) {
            throw new NotFoundException("No existe una bicicleta con el código '" . $data['codigo'] . "'.", "BICICLETA_NO_ENCONTRADA");
        }

        // Mantener valores previos si no se envían nuevos
        $tipo = !empty($data['tipo']) ? $data['tipo'] : $bici['tipo'];
        $estado = !empty($data['estado']) ? $data['estado'] : $bici['estado'];
        if ($tarifa === null) {
            $tarifa = number_format((float)$bici['tarifa'], 2, '.', '');
        }

        $sql = "UPDATE bicicletas SET tipo = :tipo, tarifa = :tarifa, estado = :estado, updated_at = NOW() WHERE codigo = :codigo";
        $update = $pdo->prepare($sql);
        $update->bindParam(':tipo', $tipo);
        $update->bindParam(':tarifa', $tarifa);
        $update->bindParam(':estado', $estado);
        $update->bindParam(':codigo', $data['codigo']);

        $update->execute();
        return "Bicicleta '" . $data['codigo'] . "' actualizada correctamente. Tipo: $tipo, Tarifa: $$tarifa, Estado: $estado.";

    } catch (Throwable $e) {
        return handle_service_exception($e, 'actualizarBicicleta');
    }
}
