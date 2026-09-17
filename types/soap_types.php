<?php
// ==========================================================
// 4. Definición de tipos complejos (addComplexType)
// ==========================================================

// Tipo complejo: Registrar Bicicleta
$server->wsdl->addComplexType(
    'InsertBicicleta',
    'complexType',
    'struct',
    'all',
    '',
    array(
        'codigo' => array('name' => 'codigo', 'type' => 'xsd:string'),
        'tipo'   => array('name' => 'tipo',   'type' => 'xsd:string'),
        'tarifa' => array('name' => 'tarifa', 'type' => 'xsd:string'),
        'estado' => array('name' => 'estado', 'type' => 'xsd:string')
    )
);

// Tipo complejo: Actualizar Bicicleta (modificar tipo, tarifa o estado)
$server->wsdl->addComplexType(
    'UpdateBicicleta',
    'complexType',
    'struct',
    'all',
    '',
    array(
        'codigo' => array('name' => 'codigo', 'type' => 'xsd:string'),
        'tipo'   => array('name' => 'tipo',   'type' => 'xsd:string'),
        'tarifa' => array('name' => 'tarifa', 'type' => 'xsd:string'),
        'estado' => array('name' => 'estado', 'type' => 'xsd:string')
    )
);

// Tipo complejo: Datos de Bicicleta (BicicletaData)
$server->wsdl->addComplexType(
    'BicicletaData',
    'complexType',
    'struct',
    'all',
    '',
    array(
        'id'         => array('name' => 'id',         'type' => 'xsd:int'),
        'codigo'     => array('name' => 'codigo',     'type' => 'xsd:string'),
        'tipo'       => array('name' => 'tipo',       'type' => 'xsd:string'),
        'tarifa'     => array('name' => 'tarifa',     'type' => 'xsd:string'),
        'estado'     => array('name' => 'estado',     'type' => 'xsd:string'),
        'created_at' => array('name' => 'created_at', 'type' => 'xsd:string')
    )
);

// Tipo complejo: Arreglo de Bicicletas para listar (BicicletaArray)
$server->wsdl->addComplexType(
    'BicicletaArray',
    'complexType',
    'array',
    '',
    'SOAP-ENC:Array',
    array(),
    array(
        array('ref' => 'SOAP-ENC:arrayType', 'wsdl:arrayType' => 'tns:BicicletaData[]')
    ),
    'tns:BicicletaData'
);

// Tipo complejo: Registrar Cliente
$server->wsdl->addComplexType(
    'InsertCliente',
    'complexType',
    'struct',
    'all',
    '',
    array(
        'documento' => array('name' => 'documento', 'type' => 'xsd:string'),
        'nombre'    => array('name' => 'nombre',    'type' => 'xsd:string'),
        'telefono'  => array('name' => 'telefono',  'type' => 'xsd:string')
    )
);

// Tipo complejo: Datos de Cliente (ClienteData)
$server->wsdl->addComplexType(
    'ClienteData',
    'complexType',
    'struct',
    'all',
    '',
    array(
        'id'         => array('name' => 'id',         'type' => 'xsd:int'),
        'documento'  => array('name' => 'documento',  'type' => 'xsd:string'),
        'nombre'     => array('name' => 'nombre',     'type' => 'xsd:string'),
        'telefono'   => array('name' => 'telefono',   'type' => 'xsd:string'),
        'created_at' => array('name' => 'created_at', 'type' => 'xsd:string')
    )
);

// Tipo complejo: Arreglo de Clientes para listar (ClienteArray)
$server->wsdl->addComplexType(
    'ClienteArray',
    'complexType',
    'array',
    '',
    'SOAP-ENC:Array',
    array(),
    array(
        array('ref' => 'SOAP-ENC:arrayType', 'wsdl:arrayType' => 'tns:ClienteData[]')
    ),
    'tns:ClienteData'
);

// Tipo complejo: Registrar Alquiler
$server->wsdl->addComplexType(
    'InsertAlquiler',
    'complexType',
    'struct',
    'all',
    '',
    array(
        'codigo_bicicleta'  => array('name' => 'codigo_bicicleta',  'type' => 'xsd:string'),
        'documento_cliente' => array('name' => 'documento_cliente', 'type' => 'xsd:string')
    )
);

// Tipo complejo: Datos de Alquiler (AlquilerData)
$server->wsdl->addComplexType(
    'AlquilerData',
    'complexType',
    'struct',
    'all',
    '',
    array(
        'id'                => array('name' => 'id',                'type' => 'xsd:int'),
        'bicicleta_codigo'  => array('name' => 'bicicleta_codigo',  'type' => 'xsd:string'),
        'bicicleta_tipo'    => array('name' => 'bicicleta_tipo',    'type' => 'xsd:string'),
        'bicicleta_tarifa'  => array('name' => 'bicicleta_tarifa',  'type' => 'xsd:string'),
        'cliente_documento' => array('name' => 'cliente_documento', 'type' => 'xsd:string'),
        'cliente_nombre'    => array('name' => 'cliente_nombre',    'type' => 'xsd:string'),
        'cliente_telefono'  => array('name' => 'cliente_telefono',  'type' => 'xsd:string'),
        'fecha_inicio'      => array('name' => 'fecha_inicio',      'type' => 'xsd:string'),
        'fecha_fin'         => array('name' => 'fecha_fin',         'type' => 'xsd:string'),
        'total'             => array('name' => 'total',             'type' => 'xsd:string'),
        'estado'            => array('name' => 'estado',            'type' => 'xsd:string')
    )
);

// Tipo complejo: Arreglo de Alquileres para listar (AlquilerArray)
$server->wsdl->addComplexType(
    'AlquilerArray',
    'complexType',
    'array',
    '',
    'SOAP-ENC:Array',
    array(),
    array(
        array('ref' => 'SOAP-ENC:arrayType', 'wsdl:arrayType' => 'tns:AlquilerData[]')
    ),
    'tns:AlquilerData'
);

// ==========================================================
// Tipos complejos: Entidad Usuario (CRUD completo)
// ==========================================================

// Tipo complejo: Insertar Usuario (InsertUser)
$server->wsdl->addComplexType(
    'InsertUser',
    'complexType',
    'struct',
    'all',
    '',
    array(
        'user_name'   => array('name' => 'user_name',   'type' => 'xsd:string'),
        'lastname'    => array('name' => 'lastname',    'type' => 'xsd:string'),
        'doc_type_id' => array('name' => 'doc_type_id', 'type' => 'xsd:int'),
        'num_doc'     => array('name' => 'num_doc',     'type' => 'xsd:string'),
        'address'     => array('name' => 'address',     'type' => 'xsd:string'),
        'phone'       => array('name' => 'phone',       'type' => 'xsd:string'),
        'password'    => array('name' => 'password',    'type' => 'xsd:string')
    )
);

// Tipo complejo: Actualizar Usuario (UpdateUser)
$server->wsdl->addComplexType(
    'UpdateUser',
    'complexType',
    'struct',
    'all',
    '',
    array(
        'id'          => array('name' => 'id',          'type' => 'xsd:int'),
        'user_name'   => array('name' => 'user_name',   'type' => 'xsd:string'),
        'lastname'    => array('name' => 'lastname',    'type' => 'xsd:string'),
        'doc_type_id' => array('name' => 'doc_type_id', 'type' => 'xsd:int'),
        'num_doc'     => array('name' => 'num_doc',     'type' => 'xsd:string'),
        'address'     => array('name' => 'address',     'type' => 'xsd:string'),
        'phone'       => array('name' => 'phone',       'type' => 'xsd:string'),
        'password'    => array('name' => 'password',    'type' => 'xsd:string')
    )
);

// Tipo complejo: Datos de Usuario para consulta individual (UserData)
$server->wsdl->addComplexType(
    'UserData',
    'complexType',
    'struct',
    'all',
    '',
    array(
        'id'           => array('name' => 'id',           'type' => 'xsd:int'),
        'user_name'    => array('name' => 'user_name',    'type' => 'xsd:string'),
        'lastname'     => array('name' => 'lastname',     'type' => 'xsd:string'),
        'doc_type_id'  => array('name' => 'doc_type_id',  'type' => 'xsd:int'),
        'num_doc'      => array('name' => 'num_doc',      'type' => 'xsd:string'),
        'address'      => array('name' => 'address',      'type' => 'xsd:string'),
        'phone'        => array('name' => 'phone',        'type' => 'xsd:string'),
        'created_date' => array('name' => 'created_date', 'type' => 'xsd:string')
    )
);

// Tipo complejo: Arreglo de Usuarios para listar (UserArray)
$server->wsdl->addComplexType(
    'UserArray',
    'complexType',
    'array',
    '',
    'SOAP-ENC:Array',
    array(),
    array(
        array('ref' => 'SOAP-ENC:arrayType', 'wsdl:arrayType' => 'tns:UserData[]')
    ),
    'tns:UserData'
);

