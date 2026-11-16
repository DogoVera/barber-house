<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
 * Usa las credenciales locales y una base aislada. El runner proporciona un
 * nombre único; la validación impide seleccionar accidentalmente la base real.
 */
require APPPATH . 'config/database.php';
$base_prueba = getenv('BH_TEST_DATABASE');
if ($base_prueba === FALSE || $base_prueba === '') {
    $base_prueba = 'barber_house_test';
}
if (!preg_match('/^barber_house_(?:test|prueba_[a-f0-9]{12})$/D', $base_prueba)) {
    exit('Nombre de base de prueba no permitido.');
}
$db['default']['database'] = $base_prueba;

// El lanzador aislado usa otro puerto; la conexión normal de la aplicación no cambia.
$puerto_prueba = getenv('BH_TEST_MYSQL_PORT');
if ($puerto_prueba !== FALSE && $puerto_prueba !== '') {
    if (!ctype_digit($puerto_prueba) || (int) $puerto_prueba < 1024 || (int) $puerto_prueba > 65535) {
        exit('Puerto de prueba no permitido.');
    }
    $db['default']['port'] = (int) $puerto_prueba;
}
