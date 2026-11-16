<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$route['default_controller'] = 'autenticacion';
$route['404_override'] = '';
$route['translate_uri_dashes'] = FALSE;

// Acceso.
$route['login'] = 'autenticacion/iniciar_sesion';
$route['logout'] = 'autenticacion/cerrar_sesion';
$route['inicio'] = 'panel_principal/index';

// Clientes.
$route['clientes'] = 'clientes/index';
$route['clientes/nuevo'] = 'clientes/nuevo';
$route['clientes/guardar'] = 'clientes/guardar';
$route['clientes/editar/(:num)'] = 'clientes/editar/$1';
$route['clientes/actualizar/(:num)'] = 'clientes/actualizar/$1';
$route['clientes/ver/(:num)'] = 'clientes/ver/$1';
$route['clientes/eliminar/(:num)'] = 'clientes/eliminar/$1';
$route['clientes/restaurar/(:num)'] = 'clientes/restaurar/$1';

// Peluqueros.
$route['peluqueros'] = 'peluqueros/index';
$route['peluqueros/nuevo'] = 'peluqueros/nuevo';
$route['peluqueros/guardar'] = 'peluqueros/guardar';
$route['peluqueros/editar/(:num)'] = 'peluqueros/editar/$1';
$route['peluqueros/actualizar/(:num)'] = 'peluqueros/actualizar/$1';
$route['peluqueros/estado/(:num)'] = 'peluqueros/cambiar_estado/$1';
$route['peluqueros/reasignar/(:num)'] = 'peluqueros/reasignar/$1';
$route['peluqueros/disponibilidad/(:num)'] = 'peluqueros/disponibilidad/$1';
$route['peluqueros/eliminar/(:num)'] = 'peluqueros/eliminar/$1';
$route['peluqueros/restaurar/(:num)'] = 'peluqueros/restaurar/$1';

// Servicios.
$route['servicios'] = 'servicios/index';
$route['servicios/nuevo'] = 'servicios/nuevo';
$route['servicios/guardar'] = 'servicios/guardar';
$route['servicios/editar/(:num)'] = 'servicios/editar/$1';
$route['servicios/actualizar/(:num)'] = 'servicios/actualizar/$1';
$route['servicios/eliminar/(:num)'] = 'servicios/eliminar/$1';
$route['servicios/restaurar/(:num)'] = 'servicios/restaurar/$1';

// Turnos.
$route['turnos'] = 'turnos/index';
$route['turnos/nuevo'] = 'turnos/nuevo';
$route['turnos/sin-reserva'] = 'turnos/sin_reserva';
$route['turnos/disponibilidad'] = 'turnos/disponibilidad';
$route['turnos/guardar'] = 'turnos/guardar';
$route['turnos/ver/(:num)'] = 'turnos/ver/$1';
$route['turnos/editar/(:num)'] = 'turnos/editar/$1';
$route['turnos/actualizar/(:num)'] = 'turnos/actualizar/$1';
$route['turnos/estado/(:num)'] = 'turnos/cambiar_estado/$1';
$route['turnos/eliminar/(:num)'] = 'turnos/eliminar/$1';

// Cobros y formas de pago.
$route['cobros'] = 'cobros/index';
$route['cobros/nuevo/(:num)'] = 'cobros/nuevo/$1';
$route['cobros/guardar'] = 'cobros/guardar';
$route['cobros/editar/(:num)'] = 'cobros/editar/$1';
$route['cobros/actualizar/(:num)'] = 'cobros/actualizar/$1';
$route['cobros/eliminar/(:num)'] = 'cobros/eliminar/$1';
$route['cobros/restaurar/(:num)'] = 'cobros/restaurar/$1';
$route['formas-pago'] = 'cobros/formas_pago';
$route['formas-pago/guardar'] = 'cobros/guardar_forma_pago';

// Contabilidad y egresos.
$route['contabilidad'] = 'contabilidad/index';
$route['egresos'] = 'contabilidad/egresos';
$route['egresos/guardar'] = 'contabilidad/guardar_egreso';
$route['egresos/editar/(:num)'] = 'contabilidad/editar_egreso/$1';
$route['egresos/actualizar/(:num)'] = 'contabilidad/actualizar_egreso/$1';
$route['egresos/anular/(:num)'] = 'contabilidad/anular_egreso/$1';
$route['egresos/restaurar/(:num)'] = 'contabilidad/restaurar_egreso/$1';

// Usuarios.
$route['usuarios'] = 'usuarios/index';
$route['usuarios/nuevo'] = 'usuarios/nuevo';
$route['usuarios/guardar'] = 'usuarios/guardar';
$route['usuarios/editar/(:num)'] = 'usuarios/editar/$1';
$route['usuarios/actualizar/(:num)'] = 'usuarios/actualizar/$1';
$route['usuarios/eliminar/(:num)'] = 'usuarios/eliminar/$1';
$route['usuarios/restaurar/(:num)'] = 'usuarios/restaurar/$1';

// Compatibilidad con las URL directas anteriores de CI3 (ej.: clients/edit/1).
// Son alias al mismo controlador: mantienen permisos, POST, CSRF y validaciones.
$controladores_anteriores = array(
    'auth' => 'autenticacion',
    'dashboard' => 'panel_principal',
    'clients' => 'clientes',
    'barbers' => 'peluqueros',
    'services' => 'servicios',
    'appointments' => 'turnos',
    'payments' => 'cobros',
    'accounting' => 'contabilidad',
    'users' => 'usuarios',
);
$metodos_anteriores = array(
    'auth' => array(
        'login' => 'iniciar_sesion',
        'logout' => 'cerrar_sesion',
    ),
    'clients' => array(
        'create' => 'nuevo',
        'store' => 'guardar',
        'edit' => 'editar',
        'update' => 'actualizar',
        'show' => 'ver',
        'delete' => 'eliminar',
        'restore' => 'restaurar',
    ),
    'barbers' => array(
        'create' => 'nuevo',
        'store' => 'guardar',
        'edit' => 'editar',
        'update' => 'actualizar',
        'availability' => 'disponibilidad',
        'reassign' => 'reasignar',
        'delete' => 'eliminar',
        'restore' => 'restaurar',
    ),
    'services' => array(
        'create' => 'nuevo',
        'store' => 'guardar',
        'edit' => 'editar',
        'update' => 'actualizar',
        'delete' => 'eliminar',
        'restore' => 'restaurar',
    ),
    'appointments' => array(
        'create' => 'nuevo',
        'walkin' => 'sin_reserva',
        'availability' => 'disponibilidad',
        'store' => 'guardar',
        'edit' => 'editar',
        'update' => 'actualizar',
        'show' => 'ver',
        'status' => 'cambiar_estado',
        'delete' => 'eliminar',
    ),
    'payments' => array(
        'create' => 'nuevo',
        'store' => 'guardar',
        'edit' => 'editar',
        'update' => 'actualizar',
        'methods' => 'formas_pago',
        'store_method' => 'guardar_forma_pago',
        'delete' => 'eliminar',
        'restore' => 'restaurar',
    ),
    'accounting' => array(
        'expenses' => 'egresos',
        'store_expense' => 'guardar_egreso',
        'edit_expense' => 'editar_egreso',
        'update_expense' => 'actualizar_egreso',
        'void_expense' => 'anular_egreso',
        'restore_expense' => 'restaurar_egreso',
    ),
    'users' => array(
        'create' => 'nuevo',
        'store' => 'guardar',
        'edit' => 'editar',
        'update' => 'actualizar',
        'delete' => 'eliminar',
        'restore' => 'restaurar',
    ),
);

foreach ($controladores_anteriores as $anterior => $actual) {
    $route[$anterior] = $actual . '/index';
    $route[$anterior . '/index'] = $actual . '/index';

    if (!isset($metodos_anteriores[$anterior])) {
        continue;
    }

    foreach ($metodos_anteriores[$anterior] as $metodo_anterior => $metodo_actual) {
        $requiere_id = in_array($metodo_anterior, array(
            'edit', 'update', 'show', 'delete', 'restore', 'reassign', 'status',
            'edit_expense', 'update_expense', 'void_expense', 'restore_expense',
        ), TRUE);
        if (($anterior === 'payments' && $metodo_anterior === 'create')
            || ($anterior === 'barbers' && $metodo_anterior === 'availability')) {
            $requiere_id = TRUE;
        }

        if ($requiere_id) {
            $route[$anterior . '/' . $metodo_anterior . '/(:any)'] = $actual . '/' . $metodo_actual . '/$1';
        } else {
            $route[$anterior . '/' . $metodo_anterior] = $actual . '/' . $metodo_actual;
        }
    }
}
