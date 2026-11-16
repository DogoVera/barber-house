<?php
/** Enrutador exclusivo del servidor PHP de integración; Apache usa .htaccess. */
if (PHP_SAPI !== 'cli-server' || getenv('CI_ENV') !== 'test') {
    http_response_code(404);
    exit;
}

$raiz = dirname(__DIR__, 2);
$ruta = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$archivo = realpath($raiz . $ruta);
$recursos = realpath($raiz . '/assets') . DIRECTORY_SEPARATOR;
if ($archivo && strpos($archivo, $recursos) === 0 && is_file($archivo)) {
    return FALSE;
}

// Mantiene base_url en la raíz aunque la petición tenga varios segmentos.
// En Windows cli-server no siempre copia las variables de entorno a $_SERVER.
// Fijar el entorno antes del front controller impide cargar la conexión normal.
$_SERVER['CI_ENV'] = 'test';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = $raiz . '/index.php';
require $raiz . '/index.php';
