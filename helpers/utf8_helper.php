<?php
/**
 * ==========================================================
 * PROYECTO: Servidor SOAP Modular
 * MÓDULO: Helpers / Utilidades de Codificación
 * ARCHIVO: helpers/utf8_helper.php
 * DESCRIPCIÓN: Funciones y configuración para admitir caracteres especiales,
 * acentos, tildes (á, é, í, ó, ú) y la letra ñ en todo el sistema.
 * ==========================================================
 */

// Configurar codificación interna en PHP a UTF-8
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}
if (function_exists('mb_http_output')) {
    mb_http_output('UTF-8');
}

/**
 * Convierte recursivamente cualquier string o arreglo a UTF-8 válido.
 * Corrige problemas de doble codificación o caracteres mal interpretados.
 *
 * @param mixed $data Arreglo, string u objeto a normalizar
 * @return mixed Datos normalizados en UTF-8
 */
function utf8_converter($data) {
    if (is_string($data)) {
        // Detectar y normalizar a UTF-8 limpio
        return mb_convert_encoding($data, 'UTF-8', mb_detect_encoding($data, 'UTF-8, ISO-8859-1, Windows-1252', true) ?: 'UTF-8');
    }
    if (is_array($data)) {
        $result = array();
        foreach ($data as $key => $value) {
            $utf8_key = is_string($key) ? utf8_converter($key) : $key;
            $result[$utf8_key] = utf8_converter($value);
        }
        return $result;
    }
    if (is_object($data)) {
        $data = (array) $data;
        return (object) utf8_converter($data);
    }
    return $data;
}

/**
 * Genera una cadena JSON preservando caracteres especiales UTF-8 sin escapar (sin \u00e1, etc.)
 *
 * @param mixed $data Datos a serializar
 * @param int $flags Opciones de json_encode
 * @return string JSON formateado en UTF-8
 */
function json_utf8_response($data, $flags = 0) {
    $clean_data = utf8_converter($data);
    $default_flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT;
    return json_encode($clean_data, $default_flags | $flags);
}
