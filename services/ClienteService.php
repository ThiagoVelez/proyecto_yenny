<?php
// ==========================================================
// 6.2 Servicios de Cliente (con Excepciones Controladas)
// ==========================================================

function InsertClienteService($data) {
    global $pdo;

    try {
        $data = function_exists('utf8_converter') ? utf8_converter($data) : $data;

        if (empty($data['documento']) || empty($data['nombre'])) {
            throw new ValidationException("Documento y nombre del cliente son campos obligatorios.", "CAMPOS_OBLIGATORIOS_FALTANTES");
        }

        if (!$pdo) {
            throw new DatabaseException("La conexión a la base de datos no está disponible.");
        }

        // Verificar si el cliente ya existe
        $check = $pdo->prepare("SELECT id FROM clientes WHERE documento = :documento");
        $check->bindParam(':documento', $data['documento']);
        $check->execute();
        if ($check->fetch()) {
            throw new BusinessRuleException("El cliente con documento '" . $data['documento'] . "' ya está registrado.", "DOCUMENTO_CLIENTE_DUPLICADO");
        }

        $sql = "INSERT INTO clientes (documento, nombre, telefono, created_at)
                VALUES (:documento, :nombre, :telefono, NOW())";
        
        $stmt = $pdo->prepare($sql);
        $telefono = isset($data['telefono']) ? $data['telefono'] : '';

        $stmt->bindParam(':documento', $data['documento']);
        $stmt->bindParam(':nombre', $data['nombre']);
        $stmt->bindParam(':telefono', $telefono);

        $stmt->execute();
        return "Se ha guardado el cliente correctamente.";

    } catch (Throwable $e) {
        return handle_service_exception($e, 'registrarCliente');
    }
}

function ConsultarClientesService() {
    global $pdo;

    try {
        if (!$pdo) {
            throw new DatabaseException("La conexión a la base de datos no está disponible.");
        }

        $stmt = $pdo->query("SELECT id, documento, nombre, telefono, created_at FROM clientes ORDER BY nombre ASC");
        $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $result = array();
        foreach ($clientes as $c) {
            $result[] = array(
                'id'         => (int)$c['id'],
                'documento'  => (string)$c['documento'],
                'nombre'     => (string)$c['nombre'],
                'telefono'   => (string)$c['telefono'],
                'created_at' => (string)$c['created_at']
            );
        }
        return $result;

    } catch (Throwable $e) {
        return handle_service_exception($e, 'listarClientes');
    }
}

// Funciones puente registradas en soap_register.php
function registrarCliente($data) {
    return InsertClienteService($data);
}

function consultarCliente($documento) {
    global $pdo;

    try {
        $documento = function_exists('utf8_converter') ? utf8_converter($documento) : $documento;
        if (empty($documento)) {
            throw new ValidationException("Debe proporcionar un documento para la consulta.", "DOCUMENTO_REQUERIDO");
        }

        if (!$pdo) {
            throw new DatabaseException("La conexión a la base de datos no está disponible.");
        }

        $stmt = $pdo->prepare("SELECT id, documento, nombre, telefono, created_at FROM clientes WHERE documento = :doc");
        $stmt->bindParam(':doc', $documento);
        $stmt->execute();
        $cli = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$cli) {
            throw new NotFoundException("No se encontró ningún cliente con el documento '" . $documento . "'.", "CLIENTE_NO_ENCONTRADO");
        }

        return array(
            'id'         => (int)$cli['id'],
            'documento'  => (string)$cli['documento'],
            'nombre'     => (string)$cli['nombre'],
            'telefono'   => (string)$cli['telefono'],
            'created_at' => (string)$cli['created_at']
        );

    } catch (Throwable $e) {
        return handle_service_exception($e, 'consultarCliente');
    }
}

function listarClientes() {
    return ConsultarClientesService();
}
