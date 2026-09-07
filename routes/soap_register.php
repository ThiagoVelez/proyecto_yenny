<?php
// ==========================================================
// 5. Registro de las operaciones del servicio SOAP
// ==========================================================

// ----------------------------------------------------------
// 5.1 OPERACIONES DE BICICLETAS
// ----------------------------------------------------------

// 1. registrarBicicleta()
$server->register(
    'registrarBicicleta',
    array('data' => 'tns:InsertBicicleta'),
    array('return' => 'xsd:string'),
    $namespace,
    false,
    'rpc',
    'encoded',
    'Registra una nueva bicicleta en el sistema (MySQL)'
);

// 2. consultarBicicleta()
$server->register(
    'consultarBicicleta',
    array('codigo' => 'xsd:string'),
    array('return' => 'xsd:string'),
    $namespace,
    false,
    'rpc',
    'encoded',
    'Consulta los datos de una bicicleta determinada por su código'
);

// 3. listarBicicletas()
$server->register(
    'listarBicicletas',
    array(),
    array('return' => 'xsd:string'),
    $namespace,
    false,
    'rpc',
    'encoded',
    'Retorna la lista completa de todas las bicicletas'
);

// 4. actualizarBicicleta()
$server->register(
    'actualizarBicicleta',
    array('data' => 'tns:UpdateBicicleta'),
    array('return' => 'xsd:string'),
    $namespace,
    false,
    'rpc',
    'encoded',
    'Permite modificar tipo, tarifa o estado de una bicicleta'
);

// ----------------------------------------------------------
// 5.2 OPERACIONES DE ALQUILERES
// ----------------------------------------------------------

// 5. registrarAlquiler()
$server->register(
    'registrarAlquiler',
    array('data' => 'tns:InsertAlquiler'),
    array('return' => 'xsd:string'),
    $namespace,
    false,
    'rpc',
    'encoded',
    'Registra un nuevo alquiler validando disponibilidad y actualizando el estado de la bicicleta'
);

// 6. consultarAlquileres()
$server->register(
    'consultarAlquileres',
    array(),
    array('return' => 'xsd:string'),
    $namespace,
    false,
    'rpc',
    'encoded',
    'Consulta todos los alquileres con datos del cliente y bicicleta'
);

// Operación adicional: finalizarAlquiler()
$server->register(
    'finalizarAlquiler',
    array('codigo_bicicleta' => 'xsd:string'),
    array('return' => 'xsd:string'),
    $namespace,
    false,
    'rpc',
    'encoded',
    'Finaliza el alquiler activo, calcula el valor a pagar y deja disponible la bicicleta'
);

// ----------------------------------------------------------
// 5.3 OPERACIONES DE CLIENTES (Para soporte de alquileres)
// ----------------------------------------------------------

$server->register(
    'registrarCliente',
    array('data' => 'tns:InsertCliente'),
    array('return' => 'xsd:string'),
    $namespace,
    false,
    'rpc',
    'encoded',
    'Registra un nuevo cliente'
);

$server->register(
    'consultarCliente',
    array('documento' => 'xsd:string'),
    array('return' => 'xsd:string'),
    $namespace,
    false,
    'rpc',
    'encoded',
    'Consulta un cliente por documento'
);

$server->register(
    'listarClientes',
    array(),
    array('return' => 'xsd:string'),
    $namespace,
    false,
    'rpc',
    'encoded',
    'Lista todos los clientes registrados'
);

// ----------------------------------------------------------
// 5.4 OPERACIONES DE USUARIOS (CRUD COMPLETO)
// ----------------------------------------------------------

// 1. Insertar Usuario
$server->register(
    'InsertUserService',
    array('data' => 'tns:InsertUser'),
    array('return' => 'xsd:string'),
    $namespace,
    false,
    'rpc',
    'encoded',
    'Inserta un nuevo usuario en la base de datos'
);

// Alias en español: insertarUsuario
$server->register(
    'insertarUsuario',
    array('data' => 'tns:InsertUser'),
    array('return' => 'xsd:string'),
    $namespace,
    false,
    'rpc',
    'encoded',
    'Alias para registrar un usuario'
);

// 2. Actualizar Usuario
$server->register(
    'UpdateUserService',
    array('data' => 'tns:UpdateUser'),
    array('return' => 'xsd:string'),
    $namespace,
    false,
    'rpc',
    'encoded',
    'Actualiza los datos de un usuario existente'
);

// Alias en español: actualizarUsuario
$server->register(
    'actualizarUsuario',
    array('data' => 'tns:UpdateUser'),
    array('return' => 'xsd:string'),
    $namespace,
    false,
    'rpc',
    'encoded',
    'Alias para actualizar un usuario'
);

// 3. Eliminar Usuario
$server->register(
    'DeleteUserService',
    array('id' => 'xsd:int'),
    array('return' => 'xsd:string'),
    $namespace,
    false,
    'rpc',
    'encoded',
    'Elimina un usuario por su identificador (ID)'
);

// Alias en español: eliminarUsuario
$server->register(
    'eliminarUsuario',
    array('id' => 'xsd:int'),
    array('return' => 'xsd:string'),
    $namespace,
    false,
    'rpc',
    'encoded',
    'Alias para eliminar un usuario'
);

// 4. Seleccionar Usuario por ID
$server->register(
    'SelectUserService',
    array('id' => 'xsd:int'),
    array('return' => 'tns:UserData'),
    $namespace,
    false,
    'rpc',
    'encoded',
    'Selecciona y retorna los datos de un usuario por su ID'
);

// Alias en español: seleccionarUsuario
$server->register(
    'seleccionarUsuario',
    array('id' => 'xsd:int'),
    array('return' => 'tns:UserData'),
    $namespace,
    false,
    'rpc',
    'encoded',
    'Alias para seleccionar un usuario por ID'
);

// 5. Listar Todos los Usuarios
$server->register(
    'ListUsersService',
    array(),
    array('return' => 'tns:UserArray'),
    $namespace,
    false,
    'rpc',
    'encoded',
    'Retorna la lista completa de todos los usuarios'
);

// Alias en español: listarUsuarios
$server->register(
    'listarUsuarios',
    array(),
    array('return' => 'tns:UserArray'),
    $namespace,
    false,
    'rpc',
    'encoded',
    'Alias para listar todos los usuarios'
);

