<?php
/**
 * Verificación rápida de Barber House sin tocar MySQL.
 *
 * Uso desde la raíz del proyecto:
 *   php tools/pruebas/ejecutar.php
 */
if (PHP_SAPI !== 'cli') {
    exit("Este archivo se ejecuta desde consola.\n");
}

$root = dirname(__DIR__, 2);
$total = 0;
$failures = 0;

function check($condition, $description)
{
    global $total, $failures;
    $total++;

    if ($condition) {
        echo "PASA  $description\n";
        return;
    }

    $failures++;
    echo "FALLA $description\n";
}

// Entorno mínimo para cargar el helper fuera de CodeIgniter.
if (!defined('BASEPATH')) {
    define('BASEPATH', $root . '/system/');
}
if (!defined('FCPATH')) {
    define('FCPATH', $root . '/');
}
if (!function_exists('html_escape')) {
    function html_escape($value, $double = TRUE)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8', $double);
    }
}
if (!function_exists('base_url')) {
    function base_url($path = '')
    {
        return 'http://localhost/' . $path;
    }
}
if (!function_exists('site_url')) {
    function site_url($path = '')
    {
        return 'http://localhost/' . ltrim($path, '/');
    }
}

require_once $root . '/application/helpers/barberia_helper.php';

echo "\n=== Helper ===\n";
check(valid_day('2026-10-20'), 'Acepta una fecha real');
check(!valid_day('2026-02-31'), 'Rechaza una fecha inexistente');
check(valid_clock('09:30'), 'Acepta una hora válida');
check(!valid_clock('24:00'), 'Rechaza una hora inválida');
check(valid_money('12000.50', TRUE), 'Acepta un importe positivo');
check(!valid_money('0', TRUE), 'Rechaza cero cuando se exige importe positivo');
check(valid_percent('50'), 'Acepta un porcentaje válido');
check(!valid_percent('101'), 'Rechaza porcentaje mayor a 100');
check(status_label('no_show') === 'Ausente', 'Traduce estado ausente');
check(day_name(1) === 'Lunes', 'Traduce día de la semana');
check(role_label('encargado') === 'Encargado', 'Traduce perfil encargado');
check(money(12345.5) === '$12.345,50', 'Formatea dinero en formato argentino');

echo "\n=== Estructura ===\n";
$required = array(
    'application/controllers/Turnos.php',
    'application/controllers/Cobros.php',
    'application/controllers/Contabilidad.php',
    'application/models/Turno_model.php',
    'application/models/Cobro_model.php',
    'application/views/peluqueros/disponibilidad_peluquero.php',
    'application/views/compartidas/paginacion.php',
    'database/barber_house.sql',
    'docs/REQUERIMIENTOS_V1.md',
    'DATOS_DE_ACCESO.txt',
);
foreach ($required as $file) {
    check(is_file($root . '/' . $file), 'Existe ' . $file);
}

// MVC: las consultas a BD deben permanecer en Models.
$folders_without_sql = array('controllers', 'views', 'core', 'helpers');
$found_db_call = FALSE;
foreach ($folders_without_sql as $folder) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root . '/application/' . $folder, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            if (strpos(file_get_contents($file->getPathname()), '$this->db') !== FALSE) {
                $found_db_call = TRUE;
            }
        }
    }
}
check(!$found_db_call, 'No hay acceso a base de datos fuera de Models');

$requirements = file_get_contents($root . '/docs/REQUERIMIENTOS_V1.md');
preg_match_all('/^\|\s*\d{2}\s*\|/m', $requirements, $matches);
check(count($matches[0]) === 71, 'La documentación contiene los 71 requerimientos');

$sql = file_get_contents($root . '/database/barber_house.sql');
check(strpos($sql, "('Efectivo',1),('Transferencia',1),('Tarjeta',1)") !== FALSE, 'SQL incluye las formas de pago iniciales');
check(strpos($sql, 'price_snapshot') !== FALSE, 'SQL conserva snapshot de precio');
check(strpos($sql, 'commission_percent_snapshot') !== FALSE, 'SQL conserva snapshot de comisión');

echo "\n========================================\n";
echo "$total verificaciones, $failures fallos\n";
exit($failures === 0 ? 0 : 1);
