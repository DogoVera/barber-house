<?php
/**
 * Integración HTTP + MariaDB, sin dependencias externas al XAMPP.
 * Uso: php tools/pruebas/integracion.php
 * Importa el instalador en una base de nombre aleatorio y prueba la aplicación
 * real con dos servidores locales (el segundo permite peticiones concurrentes).
 * Nunca selecciona, importa, actualiza ni elimina la base barber_house.
 */
if (PHP_SAPI !== 'cli') {
    exit("Ejecutar desde consola.\n");
}
set_time_limit(180); // En Windows limita también el tiempo real; shutdown limpia al vencer.
date_default_timezone_set('America/Argentina/Buenos_Aires');

$raiz = dirname(__DIR__, 2);
define('BASEPATH', $raiz . '/system/');
define('APPPATH', $raiz . '/application/');
define('ENVIRONMENT', 'test');
require $raiz . '/application/config/test/database.php';
$credenciales = $db['default'];
$nombre_base = 'barber_house_prueba_' . bin2hex(random_bytes(6));
$padre_temporal = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'opencode';
$temporal = $padre_temporal . DIRECTORY_SEPARATOR . $nombre_base;
$conexion = NULL;
$base_creada = FALSE;
$servidores = array();
$total = 0;
$fallos = 0;
$limite = microtime(TRUE) + 150;
$limpiado = FALSE;
$directorio_creado = FALSE;

/** Comprueba una condición real; detiene el flujo para evitar fallos en cascada. */
function comprobar($condicion, $descripcion)
{
    global $total, $fallos;
    $total++;
    if (!$condicion) {
        $fallos++;
        throw new RuntimeException($descripcion);
    }
}

/** Elimina solo el directorio propio creado por esta ejecución, nunca carpetas del proyecto. */
function borrar_temporales($directorio)
{
    if (!is_dir($directorio)) {
        return;
    }
    foreach (new DirectoryIterator($directorio) as $entrada) {
        if ($entrada->isDot()) {
            continue;
        }
        if ($entrada->isDir() && !$entrada->isLink()) {
            borrar_temporales($entrada->getPathname());
        } else {
            unlink($entrada->getPathname());
        }
    }
    rmdir($directorio);
}

/** Cierra servidores/conexiones y elimina base, cookies, sesiones y logs aun si falla una prueba. */
function limpiar_integracion()
{
    global $servidores, $conexion, $nombre_base, $base_creada, $temporal, $limpiado, $directorio_creado, $fallos;
    if ($limpiado) {
        return;
    }
    $limpiado = TRUE;
    foreach ($servidores as $servidor) {
        $estado = proc_get_status($servidor);
        if ($estado['running']) {
            if (PHP_OS_FAMILY === 'Windows') {
                // proc_open usa un array (sin cmd intermedio): este PID pertenece al servidor propio.
                exec('taskkill /PID ' . (int) $estado['pid'] . ' /T /F 2>&1', $salida, $codigo);
            } else {
                proc_terminate($servidor);
            }
        }
        proc_close($servidor);
    }
    if ($conexion instanceof mysqli) {
        try {
            if ($base_creada && preg_match('/^barber_house_prueba_[a-f0-9]{12}$/D', $nombre_base)) {
                $conexion->query('DROP DATABASE `' . $nombre_base . '`');
                $restantes = $conexion->query("SHOW DATABASES LIKE '$nombre_base'")->num_rows;
                if ($restantes !== 0) {
                    $fallos++;
                    echo "ERROR: quedó la base temporal.\n";
                } else {
                    echo "Base temporal eliminada.\n";
                }
            }
            $conexion->close();
        } catch (Throwable $error) {
            $fallos++;
            echo 'ERROR al limpiar la base temporal: ' . $error->getMessage() . "\n";
        }
    }
    if ($directorio_creado) {
        borrar_temporales($temporal);
        if (is_dir($temporal)) {
            $fallos++;
            echo "ERROR: quedaron archivos temporales.\n";
        } else {
            echo "Servidores, cookies, sesiones y logs de prueba limpiados.\n";
        }
    }
}
register_shutdown_function('limpiar_integracion');

/** Devuelve una fila de la base temporal seleccionada; SQL exclusivo del runner de pruebas. */
function fila($consulta)
{
    global $conexion;
    return $conexion->query($consulta)->fetch_assoc();
}

/** Obtiene el próximo puerto libre asignado por el sistema; no utiliza el Apache existente. */
function puerto_libre()
{
    $socket = stream_socket_server('tcp://127.0.0.1:0', $codigo, $mensaje);
    if (!$socket) {
        throw new RuntimeException($mensaje);
    }
    $direccion = stream_socket_get_name($socket, FALSE);
    fclose($socket);
    return (int) substr(strrchr($direccion, ':'), 1);
}

/** Crea una petición con cookies aisladas por usuario y timeouts de conexión/respuesta. */
function preparar_peticion($ruta, $campos, $sesion, $numero_servidor = 0)
{
    global $urls, $temporal, $limite;
    if (microtime(TRUE) > $limite) {
        throw new RuntimeException('Tiempo máximo de integración agotado.');
    }
    $curl = curl_init($urls[$numero_servidor] . '/' . ltrim($ruta, '/'));
    $cookies = $temporal . '/' . $sesion . '.cookies';
    curl_setopt_array($curl, array(
        CURLOPT_RETURNTRANSFER => TRUE,
        CURLOPT_HEADER => TRUE,
        CURLOPT_FOLLOWLOCATION => FALSE,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT => 12,
        CURLOPT_COOKIEFILE => $cookies,
        CURLOPT_COOKIEJAR => $cookies,
    ));
    if ($campos !== NULL) {
        curl_setopt($curl, CURLOPT_POST, TRUE);
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($campos));
    }
    return $curl;
}

/** Separa cabeceras y cuerpo para comprobar estados, redirecciones y HTML/JSON. */
function respuesta($curl, $contenido)
{
    $error = curl_error($curl);
    if ($contenido === FALSE) {
        throw new RuntimeException('Petición HTTP fallida: ' . $error);
    }
    $longitud = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
    $resultado = array(
        'codigo' => curl_getinfo($curl, CURLINFO_HTTP_CODE),
        // CI3 usa 307 para GET y 303 para POST bajo HTTP/1.1; 302 bajo HTTP/1.0.
        'redireccion' => in_array(curl_getinfo($curl, CURLINFO_HTTP_CODE), array(302, 303, 307), TRUE),
        'cabeceras' => substr($contenido, 0, $longitud),
        'cuerpo' => substr($contenido, $longitud),
    );
    curl_close($curl);
    return $resultado;
}

function peticion($ruta, $campos = NULL, $sesion = 'encargado', $numero_servidor = 0)
{
    global $ultima_respuesta;
    $curl = preparar_peticion($ruta, $campos, $sesion, $numero_servidor);
    $ultima_respuesta = respuesta($curl, curl_exec($curl));
    return $ultima_respuesta;
}

/** Renueva el token como un navegador antes de cada formulario; CSRF queda habilitado. */
function campos_con_csrf($campos, $sesion)
{
    $pagina = peticion('inicio', NULL, $sesion);
    if ($pagina['redireccion']) {
        $pagina = peticion('login', NULL, $sesion);
    }
    preg_match('/name="bh_csrf"\s+value="([^"]+)"/', $pagina['cuerpo'], $token);
    comprobar(isset($token[1]), 'El formulario debe incluir un token CSRF válido');
    $campos['bh_csrf'] = $token[1];
    return $campos;
}

function enviar_formulario($ruta, $campos, $sesion = 'encargado')
{
    return peticion($ruta, campos_con_csrf($campos, $sesion), $sesion);
}

/** Verifica el acceso real con las credenciales de demostración importadas. */
function iniciar_sesion_prueba($usuario, $sesion)
{
    $respuesta = enviar_formulario('login', array('username' => $usuario, 'password' => '123456'), $sesion);
    comprobar($respuesta['redireccion'] && strpos($respuesta['cabeceras'], '/inicio') !== FALSE, 'Login de ' . $usuario);
}

try {
    comprobar(extension_loaded('mysqli') && extension_loaded('curl'), 'Se requieren mysqli y curl del XAMPP');
    comprobar(is_dir($padre_temporal), 'Debe existir el directorio temporal aprobado: ' . $padre_temporal);
    comprobar(!file_exists($temporal), 'La carpeta temporal debe ser nueva');
    mkdir($temporal);
    $directorio_creado = TRUE;
    mkdir($temporal . '/sesiones');
    mkdir($temporal . '/logs');

    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $conexion = mysqli_init();
    $conexion->options(MYSQLI_OPT_CONNECT_TIMEOUT, 5);
    $conexion->options(MYSQLI_OPT_READ_TIMEOUT, 10);
    // Conexión SIN base seleccionada; no hay lectura ni escritura sobre barber_house.
    $conexion->real_connect($credenciales['hostname'], $credenciales['username'], $credenciales['password'], NULL, $credenciales['port'] ?? 3306);
    $conexion->set_charset('utf8mb4');
    comprobar($conexion->query("SHOW DATABASES LIKE '$nombre_base'")->num_rows === 0, 'Nombre temporal sin datos previos');

    $sql = file_get_contents($raiz . '/database/barber_house.sql');
    $sql = preg_replace('/^CREATE DATABASE barber_house /m', 'CREATE DATABASE `' . $nombre_base . '` ', $sql, 1, $creaciones);
    $sql = preg_replace('/^USE barber_house;/m', 'USE `' . $nombre_base . '`;', $sql, 1, $selecciones);
    comprobar($creaciones === 1 && $selecciones === 1, 'Sustitución exacta del destino del instalador');
    comprobar(!preg_match('/^\s*DROP\s+DATABASE\b/im', $sql), 'El instalador no elimina bases');
    $base_creada = TRUE;
    $conexion->multi_query($sql);
    do {
        $resultado = $conexion->store_result();
        if ($resultado) {
            $resultado->free();
        }
        if (!$conexion->more_results()) {
            break;
        }
        $conexion->next_result();
    } while (TRUE);
    comprobar(fila('SELECT DATABASE() nombre')['nombre'] === $nombre_base, 'Solo la base temporal quedó seleccionada');
    comprobar($conexion->query('SHOW TABLES')->num_rows === 13, 'Importación completa: 13 tablas');
    comprobar((int) fila('SELECT @@foreign_key_checks activo')['activo'] === 1, 'Relaciones activas durante la importación');
    comprobar((int) fila('SELECT COUNT(*) total FROM users')['total'] === 2, 'Dos usuarios de demostración');
    comprobar((int) fila('SELECT COUNT(*) total FROM barber_schedules')['total'] === 18, 'Jornadas iniciales completas');
    comprobar((int) fila('SELECT COUNT(*) total FROM payment_methods')['total'] === 3, 'Efectivo, transferencia y tarjeta importados');
    echo 'MariaDB ' . $conexion->server_info . ': importación única correcta en ' . $nombre_base . ".\n";

    $entorno = getenv();
    $entorno['CI_ENV'] = 'test';
    $entorno['BH_TEST_DATABASE'] = $nombre_base;
    $entorno['BH_TEST_DIRECTORY'] = $temporal;
    $urls = array();
    for ($numero = 0; $numero < 2; $numero++) {
        $puerto = puerto_libre();
        $urls[] = 'http://127.0.0.1:' . $puerto;
        $proceso = proc_open(array(
            PHP_BINARY, '-d', 'max_execution_time=20', '-S', '127.0.0.1:' . $puerto,
            '-t', $raiz, $raiz . '/tools/pruebas/enrutador.php',
        ), array(
            0 => array('pipe', 'r'),
            1 => array('file', $temporal . '/servidor_' . $numero . '.log', 'a'),
            2 => array('file', $temporal . '/servidor_' . $numero . '.error.log', 'a'),
        ), $pipes, $raiz, $entorno);
        comprobar(is_resource($proceso), 'Servidor de prueba iniciado');
        fclose($pipes[0]);
        $servidores[] = $proceso;
        $listo = FALSE;
        for ($intento = 0; $intento < 30; $intento++) {
            $socket = @fsockopen('127.0.0.1', $puerto, $codigo, $mensaje, 0.1);
            if ($socket) {
                fclose($socket);
                $listo = TRUE;
                break;
            }
            usleep(100000);
        }
        comprobar($listo, 'Servidor listo dentro del timeout');
    }

    comprobar(peticion('', NULL, 'anonimo')['redireccion'], 'Entrada raíz resuelve el controlador predeterminado');
    comprobar(peticion('clientes', NULL, 'anonimo')['redireccion'], 'Sin sesión redirige al acceso');
    $incorrecto = enviar_formulario('login', array('username' => 'encargado', 'password' => 'incorrecta'), 'anonimo');
    comprobar($incorrecto['codigo'] === 200 && strpos($incorrecto['cuerpo'], 'Usuario o contraseña incorrectos.') !== FALSE, 'Rechaza clave incorrecta');
    iniciar_sesion_prueba('encargado', 'encargado');
    iniciar_sesion_prueba('recepcion', 'recepcion');

    // Todos los destinos configurados se ejercitan por HTTP con datos demo válidos.
    require $raiz . '/application/config/routes.php';
    $rutas_probadas = 0;
    foreach ($route as $origen => $destino) {
        if (in_array($origen, array('default_controller', '404_override', 'translate_uri_dashes'), TRUE)) {
            continue;
        }
        $ruta = str_replace(array('(:num)', '(:any)'), '1', $origen);
        $respuesta = peticion($ruta);
        comprobar(in_array($respuesta['codigo'], array(200, 302, 303, 307, 405), TRUE), 'Ruta sin 404/500: ' . $ruta . ' (' . $respuesta['codigo'] . ')');
        comprobar(!preg_match('/PHP Error|Severity:|Fatal error|Unable to load/i', $respuesta['cuerpo']), 'Modelos/vistas cargados en ' . $ruta);
        $rutas_probadas++;
    }
    echo "Rutas HTTP: $rutas_probadas rutas actuales y alias anteriores comprobados.\n";

    foreach (array('inicio', 'clientes', 'turnos', 'cobros') as $ruta) {
        comprobar(peticion($ruta, NULL, 'recepcion')['codigo'] === 200, 'Recepcionista accede a ' . $ruta);
    }
    foreach (array('peluqueros', 'servicios', 'usuarios', 'egresos', 'contabilidad', 'formas-pago', 'cobros/editar/1', 'accounting', 'users') as $ruta) {
        comprobar(peticion($ruta, NULL, 'recepcion')['codigo'] === 403, 'Recepcionista no administra ' . $ruta);
    }
    foreach (array('servicios/guardar', 'peluqueros/guardar', 'usuarios/guardar', 'egresos/guardar', 'formas-pago/guardar', 'cobros/actualizar/1', 'cobros/eliminar/1', 'cobros/restaurar/1') as $ruta) {
        comprobar(enviar_formulario($ruta, array(), 'recepcion')['codigo'] === 403, 'Permiso backend por POST: ' . $ruta);
    }
    comprobar(peticion('clientes/guardar', array('first_name' => 'Sin token'))['codigo'] === 403, 'CSRF sigue activo');

    // Navegación y recursos: se siguen los enlaces reales generados, no las rutas físicas de vistas.
    $enlaces = array();
    foreach (array('inicio', 'clientes', 'clientes/ver/1', 'peluqueros', 'servicios', 'turnos?date=', 'turnos/ver/1', 'cobros', 'egresos', 'usuarios', 'contabilidad') as $ruta) {
        $pagina = peticion($ruta);
        $dom = new DOMDocument();
        @$dom->loadHTML('<?xml encoding="utf-8" ?>' . $pagina['cuerpo']);
        foreach (array('a' => 'href', 'link' => 'href', 'script' => 'src', 'img' => 'src') as $etiqueta => $atributo) {
            foreach ($dom->getElementsByTagName($etiqueta) as $elemento) {
                $url = $elemento->getAttribute($atributo);
                if (strpos($url, $urls[0] . '/') === 0) {
                    $enlaces[substr($url, strlen($urls[0]) + 1)] = TRUE;
                }
            }
        }
    }
    foreach ($enlaces as $ruta => $valor) {
        comprobar(in_array(peticion($ruta)['codigo'], array(200, 302, 303, 307), TRUE), 'Enlace/recurso generado: ' . $ruta);
    }
    echo 'Navegación: ' . count($enlaces) . " enlaces/recursos locales comprobados.\n";

    // Formularios de clientes: obligatorios, alta, edición, baja y restauración.
    comprobar(enviar_formulario('clientes/guardar', array(), 'recepcion')['codigo'] === 200, 'Cliente inválido vuelve al formulario');
    $cliente = array('first_name' => 'Evaluación', 'last_name' => "O'Connor", 'phone' => '264123456', 'notes' => 'Prefiere tarde');
    comprobar(enviar_formulario('clientes/guardar', $cliente, 'recepcion')['redireccion'], 'Alta de cliente por Recepcionista');
    $cliente_id = (int) fila("SELECT id FROM clients WHERE first_name='Evaluación'")['id'];
    comprobar($cliente_id > 0, 'Cliente guardado en MySQL con caracteres españoles');
    comprobar(enviar_formulario('clientes/guardar', $cliente)['codigo'] === 200, 'Evita duplicar cliente');
    $cliente['notes'] = 'Observación corregida';
    enviar_formulario('clientes/actualizar/' . $cliente_id, $cliente);
    comprobar(fila("SELECT notes FROM clients WHERE id=$cliente_id")['notes'] === $cliente['notes'], 'Edición de cliente persistida');
    enviar_formulario('clientes/eliminar/' . $cliente_id, array());
    comprobar(fila("SELECT deleted_at FROM clients WHERE id=$cliente_id")['deleted_at'] !== NULL, 'Baja lógica de cliente');
    enviar_formulario('clientes/restaurar/' . $cliente_id, array());
    comprobar(fila("SELECT deleted_at FROM clients WHERE id=$cliente_id")['deleted_at'] === NULL, 'Restauración de cliente');

    // Catálogo y peluqueros de prueba: jornadas todos los días para independizarse del calendario.
    $servicio = array('name' => 'Servicio de evaluación', 'price' => '1250.50', 'duration_minutes' => '30', 'active' => '1');
    comprobar(enviar_formulario('servicios/guardar', $servicio)['redireccion'], 'Alta de servicio');
    $servicio_id = (int) fila("SELECT id FROM services WHERE name='Servicio de evaluación'")['id'];
    $peluquero = array('full_name' => 'Peluquero de evaluación', 'phone' => '264987654', 'commission_percent' => '35', 'active' => '1', 'service_ids' => array($servicio_id, 2));
    for ($dia = 1; $dia <= 7; $dia++) {
        $peluquero['day_' . $dia] = '1';
        $peluquero['start_' . $dia] = '09:00';
        $peluquero['end_' . $dia] = '18:00';
    }
    comprobar(enviar_formulario('peluqueros/guardar', $peluquero)['redireccion'], 'Alta de peluquero, horarios y servicios');
    $peluquero_id = (int) fila("SELECT id FROM barbers WHERE full_name='Peluquero de evaluación'")['id'];
    comprobar((int) fila("SELECT COUNT(*) total FROM barber_schedules WHERE barber_id=$peluquero_id")['total'] === 7, 'Guarda las siete jornadas');
    $peluquero['full_name'] = 'Suplente de evaluación';
    enviar_formulario('peluqueros/guardar', $peluquero);
    $suplente_id = (int) fila("SELECT id FROM barbers WHERE full_name='Suplente de evaluación'")['id'];
    enviar_formulario('peluqueros/eliminar/' . $suplente_id, array());
    comprobar(fila("SELECT deleted_at FROM barbers WHERE id=$suplente_id")['deleted_at'] !== NULL, 'Baja lógica de peluquero');
    enviar_formulario('peluqueros/restaurar/' . $suplente_id, array());
    comprobar((int) fila("SELECT active FROM barbers WHERE id=$suplente_id")['active'] === 1, 'Restauración de peluquero');

    $usuario = array('username' => 'evaluacion', 'full_name' => 'Usuario de evaluación', 'password' => '123456', 'role_id' => '1', 'active' => '1');
    comprobar(enviar_formulario('usuarios/guardar', $usuario)['redireccion'], 'Alta de usuario');
    $usuario_id = (int) fila("SELECT id FROM users WHERE username='evaluacion'")['id'];
    comprobar(password_verify('123456', fila("SELECT password_hash FROM users WHERE id=$usuario_id")['password_hash']), 'Contraseña persistida como hash');
    unset($usuario['active']);
    $usuario['password'] = '';
    enviar_formulario('usuarios/actualizar/' . $usuario_id, $usuario);
    $rechazado = enviar_formulario('login', array('username' => 'evaluacion', 'password' => '123456'), 'inactivo');
    comprobar($rechazado['codigo'] === 200 && strpos($rechazado['cuerpo'], 'incorrectos') !== FALSE, 'Usuario inactivo no inicia sesión');
    enviar_formulario('usuarios/eliminar/' . $usuario_id, array());
    comprobar(fila("SELECT deleted_at FROM users WHERE id=$usuario_id")['deleted_at'] !== NULL, 'Baja lógica de usuario');
    enviar_formulario('usuarios/restaurar/' . $usuario_id, array());
    iniciar_sesion_prueba('evaluacion', 'restaurado');

    comprobar(enviar_formulario('formas-pago/guardar', array('name' => 'Débito de evaluación'))['redireccion'], 'Alta de forma de pago');
    enviar_formulario('formas-pago/guardar', array('name' => 'Débito de evaluación'));
    comprobar((int) fila("SELECT COUNT(*) total FROM payment_methods WHERE name='Débito de evaluación'")['total'] === 1, 'Forma de pago no se duplica');
    $hoy = date('Y-m-d');
    $egreso = array('expense_date' => $hoy, 'category_id' => '1', 'concept' => 'Gasto de evaluación', 'amount' => '1234.50');
    comprobar(enviar_formulario('egresos/guardar', $egreso)['redireccion'], 'Alta de egreso');
    $egreso_id = (int) fila("SELECT id FROM expenses WHERE concept='Gasto de evaluación'")['id'];
    $egreso['amount'] = '2000.00';
    enviar_formulario('egresos/actualizar/' . $egreso_id, $egreso);
    comprobar((float) fila("SELECT amount FROM expenses WHERE id=$egreso_id")['amount'] === 2000.0, 'Corrección de egreso');
    enviar_formulario('egresos/anular/' . $egreso_id, array());
    comprobar(fila("SELECT voided_at FROM expenses WHERE id=$egreso_id")['voided_at'] !== NULL, 'Anulación de egreso');
    enviar_formulario('egresos/restaurar/' . $egreso_id, array());
    comprobar(fila("SELECT voided_at FROM expenses WHERE id=$egreso_id")['voided_at'] === NULL, 'Restauración de egreso');
    echo "Formularios: clientes, servicios, peluqueros, usuarios, formas de pago y egresos guardan correctamente.\n";

    $fecha = date('Y-m-d', strtotime('+14 days'));
    $consulta = 'turnos/disponibilidad?' . http_build_query(array('barber_id' => $peluquero_id, 'date' => $fecha, 'service_ids' => array($servicio_id, 2)));
    $disponibilidad = json_decode(peticion($consulta, NULL, 'recepcion')['cuerpo'], TRUE);
    comprobar($disponibilidad['minutes'] === 60 && in_array('09:00', $disponibilidad['slots'], TRUE), 'Disponibilidad combina duraciones de varios servicios');
    $turno = array('client_id' => $cliente_id, 'barber_id' => $peluquero_id, 'date' => $fecha, 'time' => '09:00', 'service_ids' => array($servicio_id, 2), 'status' => 'reserved', 'notes' => 'Turno de evaluación', 'walk_in' => '0');
    comprobar(enviar_formulario('turnos/guardar', $turno, 'recepcion')['redireccion'], 'Alta de turno con varios servicios');
    $turno_id = (int) fila("SELECT id FROM appointments WHERE notes='Turno de evaluación'")['id'];
    comprobar((int) fila("SELECT COUNT(*) total FROM appointment_services WHERE appointment_id=$turno_id")['total'] === 2, 'Relación turno-servicios guardada');
    $disponibilidad = json_decode(peticion($consulta)['cuerpo'], TRUE);
    comprobar(!in_array('09:30', $disponibilidad['slots'], TRUE) && in_array('10:00', $disponibilidad['slots'], TRUE), 'Impide cruce por intervalo y permite turnos contiguos');
    $turno['time'] = '09:30';
    $conflicto = enviar_formulario('turnos/guardar', $turno);
    comprobar($conflicto['codigo'] === 200 && strpos($conflicto['cuerpo'], 'superpone') !== FALSE, 'Superposición rechazada al guardar');
    $turno['time'] = '08:00';
    comprobar(strpos(enviar_formulario('turnos/guardar', $turno)['cuerpo'], 'fuera de los días') !== FALSE, 'Respeta horario laboral');
    enviar_formulario('clientes/eliminar/' . $cliente_id, array());
    comprobar(fila("SELECT deleted_at FROM clients WHERE id=$cliente_id")['deleted_at'] === NULL, 'Protege cliente con reserva futura');

    $cambio_invalido = $peluquero;
    $cambio_invalido['full_name'] = 'Cambio que debe revertirse';
    $cambio_invalido['start_' . date('N', strtotime($fecha))] = '12:00';
    enviar_formulario('peluqueros/actualizar/' . $peluquero_id, $cambio_invalido);
    comprobar(fila("SELECT full_name FROM barbers WHERE id=$peluquero_id")['full_name'] === 'Peluquero de evaluación', 'Rollback completo si nueva jornada invalida reserva');
    comprobar(fila("SELECT start_time FROM barber_schedules WHERE barber_id=$peluquero_id ORDER BY day_of_week LIMIT 1")['start_time'] === '09:00:00', 'Horarios anteriores conservados tras rechazo');

    $servicio['price'] = '2000.00';
    $servicio['duration_minutes'] = '60';
    enviar_formulario('servicios/actualizar/' . $servicio_id, $servicio);
    $turno['time'] = '10:00';
    enviar_formulario('turnos/actualizar/' . $turno_id, $turno);
    $snapshot = fila("SELECT price_snapshot, duration_snapshot FROM appointment_services WHERE appointment_id=$turno_id AND service_id=$servicio_id");
    comprobar((float) $snapshot['price_snapshot'] === 1250.5 && (int) $snapshot['duration_snapshot'] === 30, 'Edición conserva precio y duración históricos');
    comprobar(substr(fila("SELECT end_at FROM appointments WHERE id=$turno_id")['end_at'], 11) === '11:00:00', 'El intervalo usa la duración histórica');
    $reasignar = array('appointment_id' => $turno_id, 'barber_id' => $suplente_id, 'date' => $fecha, 'time' => '11:00');
    comprobar(enviar_formulario('peluqueros/reasignar/' . $peluquero_id, $reasignar)['redireccion'], 'Reasignación del turno');
    comprobar((int) fila("SELECT barber_id FROM appointments WHERE id=$turno_id")['barber_id'] === $suplente_id, 'Destino de reasignación persistido');

    // Cobrar un importe distinto al de lista conserva el porcentaje aplicado al momento.
    $cobro = array('appointment_id' => $turno_id, 'amount' => '10000.00', 'payment_method_id' => '1', 'notes' => 'Cobro de evaluación');
    comprobar(enviar_formulario('cobros/guardar', $cobro, 'recepcion')['redireccion'], 'Recepcionista registra el cobro');
    $pago = fila("SELECT * FROM payments WHERE appointment_id=$turno_id");
    $cobro_id = (int) $pago['id'];
    comprobar((float) $pago['commission_percent_snapshot'] === 35.0 && (float) $pago['commission_amount'] === 3500.0, 'Comisión histórica calculada sobre importe real');
    comprobar(fila("SELECT status FROM appointments WHERE id=$turno_id")['status'] === 'attended', 'Cobro marca atendido en la misma operación');
    enviar_formulario('cobros/guardar', $cobro);
    comprobar((int) fila("SELECT COUNT(*) total FROM payments WHERE appointment_id=$turno_id")['total'] === 1, 'Cobro/ingreso único por turno');
    enviar_formulario('turnos/estado/' . $turno_id, array('status' => 'cancelled'));
    comprobar(fila("SELECT status FROM appointments WHERE id=$turno_id")['status'] === 'attended', 'No cancela un turno cobrado');
    comprobar(enviar_formulario('turnos/actualizar/' . $turno_id, $turno)['codigo'] === 409, 'No modifica un turno cobrado');
    $peluquero['commission_percent'] = '80';
    enviar_formulario('peluqueros/actualizar/' . $suplente_id, $peluquero);
    $correccion = array('amount' => '14000.00', 'payment_method_id' => '2', 'notes' => 'Importe corregido');
    enviar_formulario('cobros/actualizar/' . $cobro_id, $correccion);
    $pago = fila("SELECT * FROM payments WHERE id=$cobro_id");
    comprobar((float) $pago['commission_percent_snapshot'] === 35.0 && (float) $pago['commission_amount'] === 4900.0, 'Corrección no toma el porcentaje actual');
    enviar_formulario('cobros/eliminar/' . $cobro_id, array());
    comprobar((float) fila("SELECT COALESCE(SUM(amount),0) total FROM payments WHERE voided_at IS NULL AND DATE(paid_at)='$hoy'")['total'] === 0.0, 'Anulado no suma a ingresos');
    enviar_formulario('cobros/restaurar/' . $cobro_id, array());
    $contabilidad = peticion('contabilidad?from=' . $hoy . '&to=' . $hoy)['cuerpo'];
    comprobar(strpos($contabilidad, '$14.000,00') !== FALSE && strpos($contabilidad, '$4.900,00') !== FALSE, 'Liquidación muestra producción y comisión histórica del período');
    comprobar(strpos($contabilidad, '$7.100,00') !== FALSE, 'Resultado = ingresos 14000 - egresos 2000 - comisiones 4900');
    echo "Agenda, disponibilidad, snapshots, reasignación, cobros, comisiones y contabilidad comprobados.\n";

    // Turno cancelado libera disponibilidad; atención espontánea usa el mismo guardado.
    $turno['time'] = '14:00';
    $turno['notes'] = 'Sin reserva de evaluación';
    $turno['walk_in'] = '1';
    $turno['service_ids'] = array($servicio_id);
    enviar_formulario('turnos/guardar', $turno);
    $espontaneo = fila("SELECT * FROM appointments WHERE notes='Sin reserva de evaluación'");
    comprobar((int) $espontaneo['walk_in'] === 1, 'Atención sin turno previo guardada');
    enviar_formulario('turnos/eliminar/' . $espontaneo['id'], array());
    $libres = json_decode(peticion('turnos/disponibilidad?' . http_build_query(array('barber_id' => $peluquero_id, 'date' => $fecha, 'service_ids' => array($servicio_id))))['cuerpo'], TRUE);
    comprobar(in_array('14:00', $libres['slots'], TRUE), 'Turno cancelado no ocupa horario');

    for ($indice = 1; $indice <= 26; $indice++) {
        $conexion->query("INSERT INTO clients(first_name,last_name,phone,created_at,updated_at) VALUES ('Paginar','$indice','264',NOW(),NOW())");
    }
    $pagina = peticion('clientes?q=Paginar&page=1')['cuerpo'];
    $dom = new DOMDocument();
    @$dom->loadHTML($pagina);
    $xpath = new DOMXPath($dom);
    comprobar($xpath->query('//tbody/tr')->length === 25, 'Listado limitado a 25 registros');
    comprobar(strpos($pagina, 'q=Paginar&amp;page=2') !== FALSE || strpos($pagina, 'q=Paginar&page=2') !== FALSE || strpos($pagina, 'q=Paginar&archived=&page=2') !== FALSE, 'Paginación conserva el filtro');
    $segunda = peticion('clientes?q=Paginar&page=2')['cuerpo'];
    @$dom->loadHTML($segunda);
    comprobar((new DOMXPath($dom))->query('//tbody/tr')->length === 1, 'Segunda página muestra el registro restante');

    // Dos peticiones simultáneas sobre el mismo turno, en servidores y sesiones distintos.
    $turno['time'] = '16:00';
    $turno['notes'] = 'Concurrencia de evaluación';
    enviar_formulario('turnos/guardar', $turno);
    $concurrente_id = (int) fila("SELECT id FROM appointments WHERE notes='Concurrencia de evaluación'")['id'];
    iniciar_sesion_prueba('recepcion', 'carrera_cobro');
    iniciar_sesion_prueba('recepcion', 'carrera_estado');
    $datos_cobro = campos_con_csrf(array('appointment_id' => $concurrente_id, 'amount' => '1000', 'payment_method_id' => '1'), 'carrera_cobro');
    $datos_estado = campos_con_csrf(array('status' => 'cancelled'), 'carrera_estado');
    $peticiones = array(
        preparar_peticion('cobros/guardar', $datos_cobro, 'carrera_cobro'),
        preparar_peticion('turnos/estado/' . $concurrente_id, $datos_estado, 'carrera_estado', 1),
    );
    $grupo = curl_multi_init();
    foreach ($peticiones as $curl) {
        curl_multi_add_handle($grupo, $curl);
    }
    do {
        curl_multi_exec($grupo, $activas);
        if ($activas) {
            curl_multi_select($grupo, 0.1);
        }
    } while ($activas && microtime(TRUE) < $limite);
    foreach ($peticiones as $curl) {
        $contenido = curl_multi_getcontent($curl);
        curl_multi_remove_handle($grupo, $curl);
        $resultado = respuesta($curl, $contenido);
        comprobar($resultado['redireccion'], 'Carrera cobro/estado responde sin error de servidor');
    }
    curl_multi_close($grupo);
    $estado_final = fila("SELECT status FROM appointments WHERE id=$concurrente_id")['status'];
    $cantidad_cobros = (int) fila("SELECT COUNT(*) total FROM payments WHERE appointment_id=$concurrente_id")['total'];
    comprobar(($cantidad_cobros === 1 && $estado_final === 'attended') || ($cantidad_cobros === 0 && $estado_final === 'cancelled'), 'Concurrencia conserva el invariante cobro/estado');
    echo "Paginación y carrera real de cobro frente a cancelación comprobadas.\n";

    foreach (glob($temporal . '/logs/*') as $log) {
        comprobar(strpos(file_get_contents($log), 'ERROR -') === FALSE, 'Sin errores CI3 en logs de integración');
    }
    comprobar(enviar_formulario('logout', array(), 'recepcion')['redireccion'], 'Cierre de sesión por POST');
    comprobar(peticion('clientes', NULL, 'recepcion')['redireccion'], 'Sesión cerrada pierde acceso');
    echo "$total verificaciones de integración, 0 fallos.\n";
} catch (Throwable $error) {
    $fallos = max(1, $fallos);
    echo 'FALLA: ' . $error->getMessage() . "\n";
    echo "$total verificaciones alcanzadas, $fallos fallo(s).\n";
    if (isset($ultima_respuesta)) {
        echo 'Última respuesta HTTP: ' . $ultima_respuesta['codigo'] . "\n";
        echo $ultima_respuesta['cabeceras'] . substr($ultima_respuesta['cuerpo'], 0, 2500) . "\n";
    }
    foreach (glob($temporal . '/logs/*') as $log) {
        echo file_get_contents($log) . "\n";
    }
    foreach (glob($temporal . '/servidor_*.error.log') as $log) {
        echo file_get_contents($log) . "\n";
    }
}

limpiar_integracion();
exit($fallos === 0 ? 0 : 1);
