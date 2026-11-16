<?php
/**
 * Verificación estática del renombrado, sin conectar a MySQL ni abrir servidores.
 * Ejecutar: php tools/pruebas/verificar_codigo.php
 * Revisa sintaxis, nombres CI3, rutas, modelos/vistas cargados y separación MVC.
 */
if (PHP_SAPI !== 'cli') {
    exit("Ejecutar desde consola.\n");
}

$raiz = dirname(__DIR__, 2);
$total = 0;
$fallos = 0;

/** Cuenta una comprobación; solo detalla los fallos para mantener el informe legible. */
function verificar($condicion, $descripcion)
{
    global $total, $fallos;
    $total++;
    if (!$condicion) {
        $fallos++;
        echo "FALLA $descripcion\n";
    }
}

$archivos = array($raiz . '/index.php');
foreach (array('application', 'tools/pruebas') as $carpeta) {
    $iterador = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($raiz . '/' . $carpeta, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterador as $archivo) {
        $ruta = str_replace('\\', '/', $archivo->getPathname());
        if ($archivo->isFile() && $archivo->getExtension() === 'php'
            && strpos($ruta, '/logs/') === FALSE && strpos($ruta, '/cache/') === FALSE) {
            $archivos[] = $archivo->getPathname();
        }
    }
}

foreach ($archivos as $archivo) {
    $salida = array();
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($archivo) . ' 2>&1', $salida, $codigo);
    verificar($codigo === 0, 'Sintaxis PHP: ' . $archivo . ' ' . implode(' ', $salida));
}
echo 'PHP: ' . count($archivos) . " archivos revisados con php -l.\n";

$salida = array();
exec('node --check ' . escapeshellarg($raiz . '/assets/js/interfaz_barberia.js') . ' 2>&1', $salida, $codigo);
verificar($codigo === 0, 'Sintaxis JavaScript: ' . implode(' ', $salida));
echo "JavaScript: node --check ejecutado.\n";

// Solo se cargan declaraciones: no se instancia CI3 ni se accede a la base.
define('BASEPATH', $raiz . '/system/');
class CI_Controller {}
class CI_Model {}
require $raiz . '/application/core/MY_Controller.php';
foreach (array('models', 'controllers') as $carpeta) {
    foreach (glob($raiz . '/application/' . $carpeta . '/*.php') as $archivo) {
        require $archivo;
        $clase = pathinfo($archivo, PATHINFO_FILENAME);
        verificar(class_exists($clase, FALSE), 'Clase igual al archivo: ' . $archivo);
        $reflexion = new ReflectionClass($clase);
        verificar($reflexion->getName() === $clase, 'Mayúsculas de clase CI3: ' . $clase);
    }
}

require $raiz . '/application/config/routes.php';
$rutas = 0;
foreach ($route as $origen => $destino) {
    if ($origen === '404_override' || $origen === 'translate_uri_dashes') {
        continue;
    }
    $partes = explode('/', $destino);
    $clase = ucfirst($partes[0]);
    $metodo = $partes[1] ?? 'index';
    $existe = is_file($raiz . '/application/controllers/' . $clase . '.php')
        && method_exists($clase, $metodo);
    verificar($existe, 'Destino de ruta ' . $origen . ': ' . $destino);
    if ($existe) {
        $reflexion = new ReflectionMethod($clase, $metodo);
        verificar($reflexion->isPublic(), 'Método público de ruta: ' . $destino);
    }
    $rutas++;
}
echo "Rutas: $rutas destinos revisados, incluidos los alias anteriores.\n";

$vistas = 0;
$llamadas = 0;
foreach ($archivos as $archivo) {
    $ruta = str_replace('\\', '/', $archivo);
    if (strpos($ruta, '/application/') === FALSE) {
        continue;
    }
    $contenido = file_get_contents($archivo);
    preg_match_all('/\$this->(\w+_model)->(\w+)\s*\(/', $contenido, $modelos, PREG_SET_ORDER);
    foreach ($modelos as $modelo) {
        verificar(method_exists($modelo[1], $modelo[2]), 'Llamada ' . $modelo[1] . '::' . $modelo[2] . ' en ' . $archivo);
        $llamadas++;
    }

    preg_match_all('/(?:->render|->view)\(\s*\x27([^\x27]+)\x27/', $contenido, $cargas, PREG_SET_ORDER);
    foreach ($cargas as $carga) {
        verificar(is_file($raiz . '/application/views/' . $carga[1] . '.php'), 'Vista ' . $carga[1] . ' en ' . $archivo);
        $vistas++;
    }
    preg_match_all('/\x27(\w+_model)\x27/', $contenido, $cargas_modelos, PREG_SET_ORDER);
    foreach ($cargas_modelos as $carga) {
        verificar(class_exists($carga[1], FALSE), 'Modelo cargado ' . $carga[1]);
    }

    if (strpos($ruta, '/models/') === FALSE && strpos($ruta, '/config/') === FALSE) {
        verificar(!preg_match('/\$this->db\b|(?:->query|->simple_query)\s*\(/', $contenido), 'Sin consultas fuera de modelos: ' . $archivo);
    }
}
echo "Referencias: $llamadas llamadas a modelos y $vistas cargas literales de vistas revisadas.\n";

$sql = file_get_contents($raiz . '/database/barber_house.sql');
verificar(!preg_match('/^\s*DROP\s+(DATABASE|TABLE)\b/im', $sql), 'SQL sin borrado de bases/tablas');
verificar(count(glob($raiz . '/database/*.sql')) === 1, 'Instalación SQL única');
verificar(is_file($raiz . '/application/helpers/barberia_helper.php'), 'Helper propio renombrado');
echo "$total verificaciones estáticas, $fallos fallos.\n";
exit($fallos === 0 ? 0 : 1);
