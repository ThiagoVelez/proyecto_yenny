# Proyecto Corte 2: Sistema API REST de Cobros y Penalidades (Alquiler de Bicicletas)

## 1. Resumen y Contexto del Problema

El presente proyecto implementa un microservicio basado en una API RESTful encargado de gestionar los cobros y penalidades asociados a los alquileres de bicicletas procesados por el sistema SOAP previo.

El principio rector del sistema es el **desacoplamiento modular**: la API REST no accede de manera directa a las tablas de alquileres en la base de datos. Toda validación de existencia, cálculo de duración, tarifas y estado del alquiler se realiza consumiendo el servicio Web SOAP mediante peticiones protegidas con el estándar WS-Security.

---

## 2. Mapa de Entregables del Proyecto

Los cuatro entregables solicitados por la cátedra se encuentran organizados y disponibles en el repositorio:

| Entregable | Ubicación / Archivo | Descripción |
|---|---|---|
| **1. Código Fuente** | `laravel/app/` y `server.php` | API REST completa (Controladores, Modelos, Rutas) y Cliente de integración SOAP (`SoapAlquilerService.php`). |
| **2. Scripts de Base de Datos** | `database/database.sql` (Sección 8) | Definición DDL de las tablas `cobros` y `pago_cobros` con llaves foráneas, índices y restricciones CHECK. |
| **3. Colección de Pruebas** | `proyecto_corte2_cobros_pagos.postman_collection.json` | Colección Postman v2.1 con los 5 endpoints principales configurados y listos para ejecutar. |
| **4. Documentación y Guía** | `ENTREGABLES_CORTE2.md` | Arquitectura de integración, especificación de endpoints y guía de ejecución paso a paso. |

---

## 3. Arquitectura de Integración (REST -> SOAP)

### Diagrama de Flujo

```
+-------------------------------------------------------------+
|                     Cliente (Postman / Frontend)            |
+-------------------------------------------------------------+
                              |
               [HTTP JSON: POST /api/cobros]
                              v
+-------------------------------------------------------------+
|                API REST (Microservicio Laravel)             |
|                                                             |
| 1. Recibe idAlquiler y montoPenalidad                       |
| 2. Valida datos de entrada (Request Validation)             |
| 3. Invoca SoapAlquilerService (Cliente SOAP PHP)            |
+-------------------------------------------------------------+
                              |
     [HTTP XML / WS-Security: consultarAlquilerPorId]
                              v
+-------------------------------------------------------------+
|                   Servidor Web SOAP (NuSOAP)                |
|                                                             |
| 1. Valida WS-Security (<usuario> y <token>)                 |
| 2. Consulta datos del alquiler en MySQL (Bicicleta/Cliente) |
| 3. Retorna XML con tarifa, fechas y total calculado         |
+-------------------------------------------------------------+
                              |
                     [Respuesta SOAP XML]
                              v
+-------------------------------------------------------------+
|                API REST (Procesamiento y Persistencia)      |
|                                                             |
| 4. Parsea XML recibido y extrae monto base                  |
| 5. Suma montoBase + montoPenalidad = montoTotal             |
| 6. Persiste registro en la tabla `cobros`                   |
| 7. Retorna JSON 201 Created al cliente                      |
+-------------------------------------------------------------+
```

### Reglas de Integración Cumplidas
- **Desacoplamiento Estricto:** La API REST desconoce la estructura de las tablas internas de alquileres.
- **Autenticación en Header:** Cada llamada SOAP emitida por Laravel viaja con la cabecera WS-Security requerida (`<wsse:UsernameToken>`).
- **Validación de Existencia:** Si el servicio SOAP reporta que el alquiler no existe o está inactivo, la API REST interrumpe el flujo y responde código HTTP 404/422.

---

## 4. Estructura de Base de Datos

### Tabla: `cobros`
- `id` (INT, PK, AUTO_INCREMENT): Identificador único del cobro.
- `idAlquiler` (INT, NOT NULL): Identificador del alquiler validado vía SOAP.
- `montoBase` (DECIMAL(10,2), NOT NULL): Tarifa base calculada por el tiempo de alquiler.
- `montoPenalidad` (DECIMAL(10,2), NOT NULL, DEFAULT 0.00): Recargo por retraso o daños.
- `montoTotal` (DECIMAL(10,2), NOT NULL): Suma de `montoBase` + `montoPenalidad`.
- `estado` (ENUM('PENDIENTE', 'PAGADO', 'CANCELADO'), DEFAULT 'PENDIENTE'): Estado actual del cobro.
- `fechaEmision` (DATETIME, NOT NULL, DEFAULT CURRENT_TIMESTAMP): Fecha y hora de generación.

### Tabla: `pago_cobros`
- `id` (INT, PK, AUTO_INCREMENT): Identificador único del pago.
- `idCobro` (INT, NOT NULL, FK -> `cobros.id`): Cobro al cual se aplica el abono.
- `fechaPago` (DATETIME, NOT NULL, DEFAULT CURRENT_TIMESTAMP): Momento del abono.
- `metodoPago` (ENUM('TARJETA', 'TRANSFERENCIA', 'EFECTIVO'), NOT NULL): Medio de pago.
- `montoPagado` (DECIMAL(10,2), NOT NULL): Valor del abono.
- *Regla de Negocio:* Al registrar abonos, si la suma acumulada de pagos iguala o supera el `montoTotal`, el cobro pasa automáticamente a estado `PAGADO`.

---

## 5. Especificación de Endpoints RESTful

URL Base: `http://127.0.0.1:8001`

### 1. Generar Cobro
- **Método / Ruta:** `POST /api/cobros`
- **Request Body:**
  ```json
  {
    "idAlquiler": 1,
    "montoPenalidad": 5000.00
  }
  ```
- **Respuesta (201 Created):**
  ```json
  {
    "message": "Cobro generado exitosamente tras validar el alquiler en SOAP",
    "cobro": {
      "id": 1,
      "idAlquiler": 1,
      "montoBase": "20000.00",
      "montoPenalidad": "5000.00",
      "montoTotal": "25000.00",
      "estado": "PENDIENTE",
      "fechaEmision": "2026-10-06 17:30:00"
    }
  }
  ```

### 2. Consultar Cobro por ID
- **Método / Ruta:** `GET /api/cobros/1`
- **Respuesta (200 OK):**
  ```json
  {
    "cobro": {
      "id": 1,
      "idAlquiler": 1,
      "montoBase": "20000.00",
      "montoPenalidad": "5000.00",
      "montoTotal": "25000.00",
      "estado": "PENDIENTE",
      "totalPagado": "0.00",
      "saldoPendiente": "25000.00",
      "pagos": []
    }
  }
  ```

### 3. Consultar Cobros por ID de Alquiler
- **Método / Ruta:** `GET /api/cobros/alquiler/1`
- **Respuesta (200 OK):** Retorna el historial de cobros vinculados a ese alquiler.

### 4. Registrar Pago de Cobro
- **Método / Ruta:** `POST /api/pagos`
- **Request Body:**
  ```json
  {
    "idCobro": 1,
    "metodoPago": "TARJETA",
    "montoPagado": 25000.00
  }
  ```
- **Respuesta (201 Created):**
  ```json
  {
    "message": "Pago registrado exitosamente. El cobro ha sido saldado en su totalidad.",
    "pago": {
      "id": 1,
      "idCobro": 1,
      "metodoPago": "TARJETA",
      "montoPagado": "25000.00",
      "fechaPago": "2026-10-06 17:35:00"
    },
    "estadoCobro": "PAGADO",
    "saldoPendiente": "0.00"
  }
  ```

### 5. Consultar Pagos de un Cobro
- **Método / Ruta:** `GET /api/pagos/cobro/1`
- **Respuesta (200 OK):** Retorna el desglose de todos los abonos realizados.

---

## 6. Guía Rápida de Ejecución

### Requisitos Previos
- PHP 8.2 o superior con extensiones `pdo_mysql`, `curl`, `mbstring`.
- MySQL en `127.0.0.1:3306` con la base de datos `sistema_bicicletas`.

### Paso 1: Iniciar el Servidor SOAP
En una terminal en la raíz del proyecto:
```bash
php -S 127.0.0.1:8000 server.php
```

### Paso 2: Iniciar la API REST (Laravel)
En una segunda terminal, ingresar a la carpeta `laravel` y ejecutar:
```bash
cd laravel
php artisan serve --port=8001
```

### Paso 3: Validar con Postman o Pruebas Automatizadas
- **Opción A (Postman):** Importar el archivo `proyecto_corte2_cobros_pagos.postman_collection.json` y disparar las peticiones en orden.
- **Opción B (Pruebas Automatizadas):**
  ```bash
  cd laravel
  php artisan test
  ```
  La suite ejecutará 11 pruebas de integración y validación con aserciones automáticas al 100%.
