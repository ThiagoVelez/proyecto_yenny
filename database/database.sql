-- ==========================================================
-- PROYECTO: Sistema de Alquiler de Bicicletas
-- MÓDULO: Base de Datos
-- ARCHIVO: database/database.sql
-- ==========================================================

-- 1. Creación de la base de datos si no existe
CREATE DATABASE IF NOT EXISTS `sistema_bicicletas` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE `sistema_bicicletas`;

-- ==========================================================
-- 2. Limpieza de tablas previas (respetando integridad referencial)
-- ==========================================================
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `alquileres`;

DROP TABLE IF EXISTS `clientes`;

DROP TABLE IF EXISTS `bicicletas`;

SET FOREIGN_KEY_CHECKS = 1;

-- ==========================================================
-- 3. Entidad: Bicicletas
-- ==========================================================
CREATE TABLE `bicicletas` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(50) NOT NULL UNIQUE COMMENT 'Código único identificador de la bicicleta',
    `tipo` VARCHAR(50) NOT NULL COMMENT 'Ej: Montaña, Urbana, Ruta, Eléctrica',
    `tarifa` DECIMAL(10, 2) NOT NULL COMMENT 'Tarifa base o por hora',
    `estado` ENUM(
        'Disponible',
        'Alquilada',
        'Mantenimiento',
        'Inactiva'
    ) NOT NULL DEFAULT 'Disponible',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ==========================================================
-- 4. Entidad: Clientes
-- ==========================================================
CREATE TABLE `clientes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `documento` VARCHAR(30) NOT NULL UNIQUE COMMENT 'Cédula, DNI o documento de identidad',
    `nombre` VARCHAR(100) NOT NULL,
    `telefono` VARCHAR(20) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ==========================================================
-- 5. Entidad: Alquileres
-- ==========================================================
CREATE TABLE `alquileres` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `bicicleta_id` INT NOT NULL,
    `cliente_id` INT NOT NULL,
    `fecha_inicio` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `fecha_fin` DATETIME NULL,
    `total` DECIMAL(10, 2) NULL COMMENT 'Monto total liquidado',
    `estado` ENUM('Activo', 'Finalizado', 'Cancelado') NOT NULL DEFAULT 'Activo',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

-- Restricciones de Clave Foránea
CONSTRAINT `fk_alquiler_bicicleta` 
        FOREIGN KEY (`bicicleta_id`) 
        REFERENCES `bicicletas` (`id`) 
        ON UPDATE CASCADE 
        ON DELETE RESTRICT,
        
    CONSTRAINT `fk_alquiler_cliente` 
        FOREIGN KEY (`cliente_id`) 
        REFERENCES `clientes` (`id`) 
        ON UPDATE CASCADE 
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================================
-- 6. Datos Iniciales de Prueba (Seed Data)
-- ==========================================================

-- Insertar Bicicletas
INSERT INTO
    `bicicletas` (
        `codigo`,
        `tipo`,
        `tarifa`,
        `estado`
    )
VALUES (
        'BIC-001',
        'Montaña',
        15000.00,
        'Disponible'
    ),
    (
        'BIC-002',
        'Urbana',
        10000.00,
        'Alquilada'
    ),
    (
        'BIC-003',
        'Ruta',
        18000.00,
        'Disponible'
    ),
    (
        'BIC-004',
        'Eléctrica',
        25000.00,
        'Disponible'
    ),
    (
        'BIC-005',
        'Montaña',
        15000.00,
        'Mantenimiento'
    );

-- Insertar Clientes
INSERT INTO
    `clientes` (
        `documento`,
        `nombre`,
        `telefono`
    )
VALUES (
        '1001234567',
        'Carlos Pérez',
        '3001234567'
    ),
    (
        '1007654321',
        'María Gómez',
        '3109876543'
    ),
    (
        '1009876543',
        'Juan Rodríguez',
        '3205554433'
    );

-- Insertar Alquileres de prueba
INSERT INTO
    `alquileres` (
        `bicicleta_id`,
        `cliente_id`,
        `fecha_inicio`,
        `fecha_fin`,
        `total`,
        `estado`
    )
VALUES (
        1,
        1,
        '2026-08-26 10:00:00',
        '2026-08-26 13:00:00',
        45000.00,
        'Finalizado'
    ),
    (
        2,
        2,
        '2026-08-27 08:30:00',
        NULL,
        NULL,
        'Activo'
    );

-- ==========================================================
-- 7. Entidades: Tipos de Documento y Usuarios (CRUD de Usuario)
-- ==========================================================

-- Tabla de Tipos de Documento
CREATE TABLE IF NOT EXISTS `document_type` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `code` VARCHAR(10) NOT NULL UNIQUE COMMENT 'Ej: CC, TI, CE, PAS, NIT',
    `name` VARCHAR(100) NOT NULL COMMENT 'Nombre descriptivo del tipo de documento'
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Tipos de documentos iniciales (usando alias moderno para MySQL 8+)
INSERT INTO
    `document_type` (`id`, `code`, `name`)
VALUES (
        1,
        'CC',
        'Cédula de Ciudadanía'
    ),
    (
        2,
        'TI',
        'Tarjeta de Identidad'
    ),
    (
        3,
        'CE',
        'Cédula de Extranjería'
    ),
    (4, 'PAS', 'Pasaporte'),
    (
        5,
        'NIT',
        'Número de Identificación Tributaria'
    ) AS new
ON DUPLICATE KEY UPDATE
    `name` = new.`name`;

-- Tabla de Usuarios (Fase Token Criptográfico & WS-Security)
CREATE TABLE IF NOT EXISTS `user` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_name` VARCHAR(100) NOT NULL UNIQUE COMMENT 'Nombre del usuario / login',
    `lastname` VARCHAR(100) NOT NULL COMMENT 'Apellido del usuario',
    `doc_type_id` INT NOT NULL COMMENT 'ID del tipo de documento',
    `num_doc` VARCHAR(50) NOT NULL COMMENT 'Número de documento',
    `address` VARCHAR(255) DEFAULT NULL COMMENT 'Dirección de residencia',
    `phone` VARCHAR(30) DEFAULT NULL COMMENT 'Número de teléfono/contacto',
    `password` VARCHAR(255) NOT NULL COMMENT 'Contraseña encriptada (Bcrypt o SHA-256)',
    `token` VARCHAR(64) DEFAULT NULL COMMENT 'Token criptográfico de 64 caracteres hex',
    `token_date` DATETIME DEFAULT NULL COMMENT 'Fecha y hora de generación del token (NOW())',
    `created_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha de registro',
    INDEX `idx_user_token` (`token`),
    INDEX `idx_user_login` (`user_name`),
    CONSTRAINT `fk_user_doc_type` FOREIGN KEY (`doc_type_id`) REFERENCES `document_type` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Usuarios Iniciales con Contraseñas Encriptadas
INSERT INTO `user` (`user_name`, `lastname`, `doc_type_id`, `num_doc`, `address`, `phone`, `password`, `token`, `token_date`)
VALUES
(
    'admin',
    'Sistema',
    1,
    '10101010',
    'Calle 100 # 10-20',
    '3001112233',
    '$2y$10$X86/QeI4rX4B51wB6M0DqONbCskV2Y4/8u5x6n7U8dYh7b2hS6VqG', -- Bcrypt para 'admin123'
    NULL,
    NULL
),
(
    'operador',
    'Técnico',
    1,
    '20202020',
    'Carrera 15 # 45-30',
    '3104445566',
    SHA2('operador123', 256), -- SHA-256 de MySQL para 'operador123'
    NULL,
    NULL
) AS new
ON DUPLICATE KEY UPDATE
    `password` = new.`password`,
    `address`  = new.`address`,
    `phone`    = new.`phone`;