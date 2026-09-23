<?php
// ==========================================================
// 2. Configuración de Base de Datos
// ==========================================================
$host = "127.0.0.1";
$port = "3306";
$dbname = "sistema_bicicletas";
$username = "root";
$password = "";

$dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";

// ==========================================================
// 3. Conexión PDO con manejo de excepciones
// ==========================================================
try {
    $pdo = new PDO($dsn, $username, $password, array(
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
    ));
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET NAMES 'utf8mb4'");
} catch (PDOException $e) {
    error_log("Fallo de conexión a base de datos: " . $e->getMessage());
    $pdo = null;
}
