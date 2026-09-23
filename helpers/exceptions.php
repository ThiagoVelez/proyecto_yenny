<?php
/**
 * ==========================================================
 * PROYECTO: Servidor SOAP Modular
 * MÓDULO: Arquitectura de Excepciones Controladas
 * ARCHIVO: helpers/exceptions.php
 * DESCRIPCIÓN: Define la jerarquía de excepciones del dominio
 * y el despachador estándar de fallos SOAP (soap_fault)
 * conforme a la especificación W3C y NuSOAP.
 * ==========================================================
 */

/**
 * Clase base abstracta/general para excepciones del servicio web
 */
class BaseServiceException extends Exception {
    protected $faultCode; // 'Client' (SOAP-ENV:Client) o 'Server' (SOAP-ENV:Server)
    protected $detail;

    public function __construct($message, $faultCode = 'Client', $detail = '', $code = 0, Throwable $previous = null) {
        parent::__construct($message, $code, $previous);
        $this->faultCode = $faultCode;
        $this->detail = $detail;
    }

    public function getFaultCode() {
        return $this->faultCode;
    }

    public function getDetail() {
        return $this->detail;
    }
}

/**
 * Excepción de validación (datos requeridos faltantes, tipos inválidos, tarifa < 0, etc.)
 * Origen: Client
 */
class ValidationException extends BaseServiceException {
    public function __construct($message, $detail = 'VALIDATION_ERROR', $code = 400, Throwable $previous = null) {
        parent::__construct($message, 'Client', $detail, $code, $previous);
    }
}

/**
 * Excepción de recurso no encontrado (bicicleta, cliente, alquiler o usuario no existe)
 * Origen: Client
 */
class NotFoundException extends BaseServiceException {
    public function __construct($message, $detail = 'NOT_FOUND', $code = 404, Throwable $previous = null) {
        parent::__construct($message, 'Client', $detail, $code, $previous);
    }
}

/**
 * Excepción de regla de negocio violada (ej. bicicleta ocupada/no disponible, código duplicado)
 * Origen: Client
 */
class BusinessRuleException extends BaseServiceException {
    public function __construct($message, $detail = 'BUSINESS_RULE_VIOLATION', $code = 422, Throwable $previous = null) {
        parent::__construct($message, 'Client', $detail, $code, $previous);
    }
}

/**
 * Excepción de autenticación y seguridad (cabecera WS-Security ausente/inválida o Token expirado)
 * Origen: Client
 */
class AuthenticationException extends BaseServiceException {
    public function __construct($message = 'Fallo de autenticación: Credenciales o token de seguridad inválidos.', $detail = 'AUTHENTICATION_FAILED', $code = 401, Throwable $previous = null) {
        parent::__construct($message, 'Client', $detail, $code, $previous);
    }
}

/**
 * Excepción controlada para fallos internos de base de datos o conexión
 * Origen: Server
 */
class DatabaseException extends BaseServiceException {
    public function __construct($message = 'Error interno en el servidor de base de datos.', $detail = 'DATABASE_ERROR', $code = 500, Throwable $previous = null) {
        parent::__construct($message, 'Server', $detail, $code, $previous);
    }
}

/**
 * Manejador global de excepciones controladas para NuSOAP.
 * Transforma cualquier excepción capturada en un objeto soap_fault W3C nativo.
 *
 * @param Throwable $e Excepción capturada
 * @param string $actor Nombre de la operación/método SOAP (opcional)
 * @return soap_fault Objeto serializable como <SOAP-ENV:Fault>
 */
function handle_service_exception(Throwable $e, $actor = '') {
    if ($e instanceof BaseServiceException) {
        return new soap_fault($e->getFaultCode(), $actor, $e->getMessage(), $e->getDetail());
    }

    if ($e instanceof PDOException) {
        // Registrar el error real internamente para diagnóstico seguro
        error_log("Database Error [PDO] en $actor: " . $e->getMessage());
        return new soap_fault(
            'Server',
            $actor,
            'Ocurrió un error interno en la base de datos al procesar la solicitud.',
            'DATABASE_ERROR'
        );
    }

    // Excepciones imprevistas de PHP
    error_log("Unhandled Exception en $actor: " . $e->getMessage());
    return new soap_fault(
        'Server',
        $actor,
        'Error interno inesperado en el servidor: ' . $e->getMessage(),
        'INTERNAL_SERVER_ERROR'
    );
}
