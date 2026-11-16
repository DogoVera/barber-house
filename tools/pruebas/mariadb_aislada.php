<?php
/**
 * Ejecuta integración con una instancia MariaDB descartable de XAMPP en Windows.
 * Uso: php tools/pruebas/mariadb_aislada.php
 * No inicia servicios ni usa mysql/data. Todo se crea bajo Temp/opencode y se
 * elimina al cerrar. Reutiliza los ejecutables ya instalados, sin dependencias.
 */
if (PHP_SAPI !== 'cli' || PHP_OS_FAMILY !== 'Windows') {
    exit("Este lanzador requiere PHP CLI de XAMPP en Windows.\n");
}
set_time_limit(270);
$raiz = dirname(__DIR__, 2);
$binarios = dirname(PHP_BINARY, 2) . '/mysql/bin';
$padre = sys_get_temp_dir() . '/opencode';
$directorio = $padre . '/barber_house_mariadb_' . bin2hex(random_bytes(6));
$procesos = array();
$creado = FALSE;
$limpio = FALSE;
$salida_final = 1;

/** Inicia sin shell intermedio; registra el proceso antes de esperar su salida. */
function iniciar_proceso($argumentos, $nombre, $entorno = NULL)
{
    global $procesos, $directorio, $raiz;
    $proceso = proc_open($argumentos, array(
        0 => array('pipe', 'r'),
        1 => array('file', $directorio . '/' . $nombre . '.log', 'a'),
        2 => array('file', $directorio . '/' . $nombre . '.error.log', 'a'),
    ), $pipes, $raiz, $entorno);
    if (!is_resource($proceso)) {
        throw new RuntimeException('No se pudo iniciar ' . $nombre);
    }
    fclose($pipes[0]);
    $procesos[] = $proceso;
    return $proceso;
}

/** Espera acotada; si vence, shutdown mata exclusivamente los procesos propios. */
function esperar_proceso($proceso, $segundos)
{
    $fin = microtime(TRUE) + $segundos;
    do {
        $estado = proc_get_status($proceso);
        if (!$estado['running']) {
            return $estado['exitcode'];
        }
        usleep(100000);
    } while (microtime(TRUE) < $fin);
    throw new RuntimeException('Timeout de un proceso de pruebas.');
}

function limpiar_instancia()
{
    global $procesos, $directorio, $creado, $limpio;
    if ($limpio) {
        return;
    }
    $limpio = TRUE;
    foreach (array_reverse($procesos) as $proceso) {
        $estado = proc_get_status($proceso);
        if ($estado['running']) {
            exec('taskkill /PID ' . (int) $estado['pid'] . ' /T /F 2>&1', $salida, $codigo);
        }
        proc_close($proceso);
    }
    if ($creado && is_dir($directorio)) {
        $iterador = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directorio, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterador as $entrada) {
            if ($entrada->isDir()) {
                rmdir($entrada->getPathname());
            } else {
                unlink($entrada->getPathname());
            }
        }
        rmdir($directorio);
        echo "Instancia MariaDB aislada detenida y carpeta de datos temporal eliminada.\n";
    }
}
register_shutdown_function('limpiar_instancia');

try {
    if (!is_dir($padre) || !is_file($binarios . '/mysql_install_db.exe') || !is_file($binarios . '/mysqld.exe')) {
        throw new RuntimeException('No se encontraron el temporal aprobado o los ejecutables MariaDB de XAMPP.');
    }
    if (file_exists($directorio)) {
        throw new RuntimeException('El directorio temporal debe ser nuevo.');
    }
    mkdir($directorio);
    $creado = TRUE;
    $socket = stream_socket_server('tcp://127.0.0.1:0', $codigo, $mensaje);
    if (!$socket) {
        throw new RuntimeException($mensaje);
    }
    $puerto = (int) substr(strrchr(stream_socket_get_name($socket, FALSE), ':'), 1);
    fclose($socket);
    $instalador = iniciar_proceso(array(
        $binarios . '/mysql_install_db.exe', '--datadir=' . $directorio . '/datos', '--port=' . $puerto,
    ), 'inicializacion');
    if (esperar_proceso($instalador, 45) !== 0) {
        throw new RuntimeException('Falló la inicialización de MariaDB.');
    }
    iniciar_proceso(array(
        $binarios . '/mysqld.exe', '--no-defaults', '--basedir=' . dirname($binarios),
        '--datadir=' . $directorio . '/datos', '--port=' . $puerto, '--bind-address=127.0.0.1',
        '--console', '--skip-log-bin', '--innodb-buffer-pool-size=32M', '--innodb-log-file-size=8M',
        '--innodb-lock-wait-timeout=5', '--max-connections=20', '--default-time-zone=-03:00',
    ), 'mariadb');
    $listo = FALSE;
    for ($intento = 0; $intento < 100; $intento++) {
        $socket = @fsockopen('127.0.0.1', $puerto, $codigo, $mensaje, 0.1);
        if ($socket) {
            fclose($socket);
            $listo = TRUE;
            break;
        }
        usleep(100000);
    }
    if (!$listo) {
        throw new RuntimeException('MariaDB temporal no inició dentro del timeout.');
    }
    echo "Instancia MariaDB independiente lista en el puerto $puerto.\n";
    $entorno = getenv();
    $entorno['BH_TEST_MYSQL_PORT'] = (string) $puerto;
    $integracion = iniciar_proceso(array(PHP_BINARY, $raiz . '/tools/pruebas/integracion.php'), 'integracion', $entorno);
    $salida_final = esperar_proceso($integracion, 190);
    echo file_get_contents($directorio . '/integracion.log');
    echo file_get_contents($directorio . '/integracion.error.log');
} catch (Throwable $error) {
    echo 'ERROR: ' . $error->getMessage() . "\n";
    foreach (glob($directorio . '/*.log') as $log) {
        echo basename($log) . ":\n" . file_get_contents($log) . "\n";
    }
}
limpiar_instancia();
exit($salida_final === 0 ? 0 : 1);
