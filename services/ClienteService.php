<?php
// ==========================================================
// 6.2 Servicios de Cliente
// ==========================================================

function InsertClienteService($data) {
    global $pdo;

    if (!$pdo) {
        return "Error: La conexión a la base de datos no está disponible.";
    }

    try {
        $data = function_exists('utf8_converter') ? utf8_converter($data) : $data;

        // Verificar si el cliente ya existe
        $check = $pdo->prepare("SELECT id FROM clientes WHERE documento = :documento");
        $check->bindParam(':documento', $data['documento']);
        $check->execute();
        if ($check->fetch()) {
            return "Error: El cliente con documento '" . $data['documento'] . "' ya está registrado.";
        }

        $sql = "INSERT INTO clientes (documento, nombre, telefono, created_at)
                VALUES (:documento, :nombre, :telefono, NOW())";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':documento', $data['documento']);
        $stmt->bindParam(':nombre', $data['nombre']);
        $stmt->bindParam(':telefono', $data['telefono']);

        $stmt->execute();
        return "Se ha guardado el cliente correctamente.";

    } catch (PDOException $e) {
        return "Error: " . $e->getMessage();
    }
}

function ConsultarClientesService() {
    global $pdo;

    if (!$pdo) {
        return "Error: La conexión a la base de datos no está disponible.";
    }

    try {
        $stmt = $pdo->query("SELECT id, documento, nombre, telefono, created_at FROM clientes ORDER BY nombre ASC");
        $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return function_exists('json_utf8_response') 
            ? json_utf8_response($clientes) 
            : json_encode($clientes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    } catch (PDOException $e) {
        return "Error: " . $e->getMessage();
    }
}

// Funciones puente registradas en soap_register.php
function registrarCliente($data) {
    return InsertClienteService($data);
}

function consultarCliente($documento) {
    global $pdo;
    if (!$pdo) {
        return -1;
    }
    if (empty($documento)) {
        return -1;
    }
    try {
        $stmt = $pdo->prepare("SELECT id, documento, nombre, telefono, created_at FROM clientes WHERE documento = :doc");
        $stmt->bindParam(':doc', $documento);
        $stmt->execute();
        $cli = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$cli) {
            return -1;
        }
        return function_exists('json_utf8_response') 
            ? json_utf8_response($cli) 
            : json_encode($cli, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    } catch (PDOException $e) {
        return -1;
    }
}

function listarClientes() {
    return ConsultarClientesService();
}


