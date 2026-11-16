<?php
defined('BASEPATH') OR exit('No direct script access allowed');

// Sesiones y logs aislados, fuera de application/cache y application/logs.
$directorio_prueba = getenv('BH_TEST_DIRECTORY');
if (!$directorio_prueba) {
    $directorio_prueba = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'opencode'
        . DIRECTORY_SEPARATOR . 'barber_house_test';
}
$config['sess_save_path'] = $directorio_prueba . DIRECTORY_SEPARATOR . 'sesiones';
$config['log_path'] = $directorio_prueba . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR;
$config['log_threshold'] = 1;
