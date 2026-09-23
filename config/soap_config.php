<?php
if (!isset($_SERVER['SERVER_NAME'])) {
    $_SERVER['SERVER_NAME'] = '127.0.0.1';
}
if (!isset($_SERVER['SERVER_PORT'])) {
    $_SERVER['SERVER_PORT'] = '8000';
}
if (!isset($_SERVER['REQUEST_URI'])) {
    $_SERVER['REQUEST_URI'] = '/server.php';
}

require_once __DIR__ . '/../vendor/econea/nusoap/src/nusoap.php';

// ==========================================================
// 1. Configuración del Servidor SOAP
// ==========================================================
$namespace = "InsertUserSOAP";
$server = new soap_server();
$server->configureWSDL('SoapService', $namespace);

// Configuración para admitir caracteres especiales (UTF-8, tildes, ñ)
$server->soap_defencoding = 'UTF-8';
$server->decode_utf8 = false;
$server->xml_encoding = 'UTF-8';
