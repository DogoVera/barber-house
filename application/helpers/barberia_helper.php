<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * HELPER: barberia. Funciones de apoyo para vistas y controladores.
 *
 * Acá se concentran tareas pequeñas y repetidas: escapar HTML, formatear
 * dinero, traducir estados y validar formatos simples.
 * Se carga desde config/autoload.php. No consulta MySQL; los nombres de
 * funciones se conservan para reconocer fácilmente su uso en formularios.
 */

/** Escapa texto antes de mostrarlo en HTML. */
function h($value)
{
    return html_escape((string) $value);
}

/** Formatea un importe con estilo argentino. */
function money($value)
{
    return '$' . number_format((float) $value, 2, ',', '.');
}

/** Estados válidos de un turno. */
function status_options()
{
    return array(
        'reserved'  => 'Reservado',
        'attended'  => 'Atendido',
        'cancelled' => 'Cancelado',
        'no_show'   => 'Ausente',
    );
}

/** Nombre legible del estado. */
function status_label($status)
{
    $labels = status_options();
    return isset($labels[$status]) ? $labels[$status] : ucfirst($status);
}

/** Clase visual usada por los badges de estado. */
function status_class($status)
{
    $classes = array(
        'reserved'  => 'green',
        'attended'  => 'blue',
        'cancelled' => 'red',
        'no_show'   => 'gold',
    );

    return isset($classes[$status]) ? $classes[$status] : 'neutral';
}

/** Nombre del perfil del usuario. */
function role_label($code)
{
    return $code === 'encargado' ? 'Encargado' : 'Recepcionista';
}

/** 1=Lunes ... 7=Domingo, igual que date('N') y barber_schedules. */
function day_name($number)
{
    $days = array(
        1 => 'Lunes',
        2 => 'Martes',
        3 => 'Miércoles',
        4 => 'Jueves',
        5 => 'Viernes',
        6 => 'Sábado',
        7 => 'Domingo',
    );

    return isset($days[$number]) ? $days[$number] : '';
}

/** Mensaje flash de la operación anterior. */
function flash_message()
{
    $CI =& get_instance();

    if ($message = $CI->session->flashdata('success')) {
        return '<div class="alert success">' . h($message) . '</div>';
    }

    if ($message = $CI->session->flashdata('error')) {
        return '<div class="alert error">' . h($message) . '</div>';
    }

    return '';
}

/** Agrega la fecha de modificación para evitar caché vieja de CSS/JS. */
function asset_url($path)
{
    $file = FCPATH . $path;
    $version = file_exists($file) ? filemtime($file) : time();
    return base_url($path) . '?v=' . $version;
}

/**
 * URL de una página conservando los filtros vigentes.
 *
 * Un valor vacío se conserva porque, por ejemplo, date= significa
 * "historial completo" en la agenda.
 */
function page_url($route, $page, $filters = array())
{
    $filters['page'] = (int) $page;

    $query = array_filter($filters, function ($value) {
        return $value !== NULL;
    });

    return site_url($route) . '?' . http_build_query($query);
}

/** Fecha real con formato YYYY-MM-DD. */
function valid_day($value)
{
    if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value)) {
        return FALSE;
    }

    $date = DateTime::createFromFormat('!Y-m-d', $value);
    return $date && $date->format('Y-m-d') === $value;
}

/** Hora HH:MM entre 00:00 y 23:59. */
function valid_clock($value)
{
    return is_string($value)
        && preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/D', $value) === 1;
}

/** Importe con hasta dos decimales. */
function valid_money($value, $positive = FALSE)
{
    return is_scalar($value)
        && preg_match('/^\d{1,10}(?:\.\d{1,2})?$/D', (string) $value) === 1
        && (!$positive || (float) $value > 0);
}

/** Porcentaje de 0 a 100. */
function valid_percent($value)
{
    return is_scalar($value)
        && preg_match('/^\d{1,3}(?:\.\d{1,2})?$/D', (string) $value) === 1
        && (float) $value <= 100;
}
