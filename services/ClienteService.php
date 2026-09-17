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
        return array();
    }

    try {
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
    } catch (PDOException $e) {
        return array();
    }
}

// Funciones puente registradas en soap_register.php
function registrarCliente($data) {
    return InsertClienteService($data);
}

function consultarCliente($documento) {
    global $pdo;
    if (!$pdo || empty($documento)) {
        return class_exists('soapval') ? new soapval('return', 'xsd:string', '-1') : -1;
    }
    try {
        $stmt = $pdo->prepare("SELECT id, documento, nombre, telefono, created_at FROM clientes WHERE documento = :doc");
        $stmt->bindParam(':doc', $documento);
        $stmt->execute();
        $cli = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$cli) {
            return class_exists('soapval') ? new soapval('return', 'xsd:string', '-1') : -1;
        }
        return array(
            'id'         => (int)$cli['id'],
            'documento'  => (string)$cli['documento'],
            'nombre'     => (string)$cli['nombre'],
            'telefono'   => (string)$cli['telefono'],
            'created_at' => (string)$cli['created_at']
        );
    } catch (PDOException $e) {
        return class_exists('soapval') ? new soapval('return', 'xsd:string', '-1') : -1;
    }
}

function listarClientes() {
    return ConsultarClientesService();
}


