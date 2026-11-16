<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * CONTROLADOR: Peluqueros
 * Solo Encargado administra datos, horarios, servicios y porcentaje de comisión.
 * Usa Peluquero_model para guardar/archivar/restaurar, Servicio_model para el
 * catálogo y Turno_model para disponibilidad y reasignación de reservas.
 * Valida formularios; los modelos rechazan atómicamente conflictos de agenda.
 */
class Peluqueros extends MY_Controller {

    public function __construct()
    {
        parent::__construct();
        $this->require_manager();
        $this->load->model(array('Peluquero_model', 'Servicio_model', 'Turno_model'));
    }

    /** Lista peluqueros por nombre/teléfono y condición de archivo. */
    public function index()
    {
        $q = trim((string) $this->input->get('q', TRUE));
        $archived = $this->input->get('archived') === '1';

        $this->render('peluqueros/listado_peluqueros', array(
            'barbers' => $this->Peluquero_model->obtener_peluqueros(FALSE, $archived, $q),
            'q' => $q,
            'archived' => $archived,
        ), 'Peluqueros');
    }

    /** Abre el formulario de alta con horarios y servicios sin seleccionar. */
    public function nuevo()
    {
        $this->mostrar_formulario(NULL, array());
    }

    /** Carga el formulario por id; 404 si no existe o está archivado. */
    public function editar($id)
    {
        $barber = $this->Peluquero_model->buscar_peluquero($id);
        if (!$barber) {
            show_404();
        }
        $this->mostrar_formulario($barber, array());
    }

    /** REQ-19: día, jornada, ocupados y horarios disponibles. */
    public function disponibilidad($id)
    {
        $barber = $this->Peluquero_model->buscar_peluquero($id);
        if (!$barber) {
            show_404();
            return;
        }

        $date_input = $this->input->get('date', TRUE);
        $date = is_scalar($date_input) ? (string) $date_input : '';
        if (!valid_day($date)) {
            $date = date('Y-m-d');
        }

        $service_ids = $this->input->get('service_ids');
        $selected = is_array($service_ids) ? $service_ids : array();
        $availability = $this->Turno_model->horarios_disponibles($id, $date, $selected);

        $this->render('peluqueros/disponibilidad_peluquero', array(
            'barber' => $barber,
            'date' => $date,
            'services' => $this->Servicio_model->obtener_servicios(TRUE),
            'selected_services' => $selected,
            'availability' => $availability,
        ), 'Disponibilidad del peluquero');
    }

    /** Prepara el formulario conservando lo enviado si hubo errores. */
    private function mostrar_formulario($barber, $errors, $posted = FALSE)
    {
        if ($posted) {
            $schedules = array();
            foreach ($this->filas_horarios() as $row) {
                if ($row['enabled']) {
                    $schedules[] = array(
                        'day_of_week' => $row['day'],
                        'start_time' => $row['start'] . ':00',
                        'end_time' => $row['end'] . ':00',
                    );
                }
            }

            $posted_services = $this->input->post('service_ids');
            $selected = array_map('intval', is_array($posted_services) ? $posted_services : array());
        } elseif ($barber && !empty($barber['id'])) {
            $schedules = $this->Peluquero_model->consultar_horarios($barber['id']);
            $selected = $this->Peluquero_model->obtener_servicios($barber['id']);
        } else {
            $schedules = array();
            $selected = array();
        }

        $editing = $barber && !empty($barber['id']);
        $this->render('peluqueros/formulario_peluquero', array(
            'barber' => $barber,
            'errors' => $errors,
            'services' => $this->Servicio_model->obtener_servicios(TRUE),
            'schedules' => $schedules,
            'selected_services' => $selected,
        ), $editing ? 'Editar peluquero' : 'Nuevo peluquero');
    }

    /** Lee los siete días usando for, manteniendo la estructura simple. */
    private function filas_horarios()
    {
        $rows = array();

        for ($day = 1; $day <= 7; $day++) {
            $rows[] = array(
                'day' => $day,
                'enabled' => $this->posted('day_' . $day, 0) ? 1 : 0,
                'start' => $this->posted('start_' . $day, '09:00'),
                'end' => $this->posted('end_' . $day, '18:00'),
            );
        }

        return $rows;
    }

    /** Devuelve errores del POST: nombre, porcentaje, servicios activos únicos y jornada válida. */
    private function validar_datos()
    {
        $errors = array();

        if (trim($this->posted('full_name', '')) === '') {
            $errors[] = 'El nombre es obligatorio.';
        }

        if (!valid_percent($this->posted('commission_percent'))) {
            $errors[] = 'El porcentaje debe estar entre 0 y 100.';
        }

        $service_ids = $this->input->post('service_ids');
        $services_array = is_array($service_ids) ? $service_ids : array();
        $unique_ids = array_unique(array_map('intval', $services_array));
        $existing = $this->Servicio_model->obtener_activos($services_array);

        if (!$services_array
            || count($unique_ids) !== count($services_array)
            || count($existing) !== count($unique_ids)) {
            $errors[] = 'Seleccioná servicios activos válidos sin repetir.';
        }

        foreach ($this->filas_horarios() as $row) {
            if (!$row['enabled']) {
                continue;
            }

            if (!valid_clock($row['start'])
                || !valid_clock($row['end'])
                || $row['start'] >= $row['end']) {
                $errors[] = 'Revisá los horarios de ' . day_name($row['day']) . '.';
            }
        }

        return $errors;
    }

    /** Prepara datos personales, porcentaje y actividad desde el POST validado. */
    private function datos_peluquero()
    {
        return array(
            'full_name' => trim($this->posted('full_name', '')),
            'phone' => trim($this->posted('phone', '')),
            'commission_percent' => (float) $this->posted('commission_percent', 0),
            'active' => $this->posted('active', 0) ? 1 : 0,
            'updated_at' => date('Y-m-d H:i:s'),
        );
    }

    /** Registra por POST datos, jornadas y servicios; devuelve al formulario si hay errores. */
    public function guardar()
    {
        $this->require_post();
        $errors = $this->validar_datos();

        if ($errors) {
            $posted = array_merge($this->input->post(NULL, TRUE), array('id' => NULL));
            $this->mostrar_formulario($posted, $errors, TRUE);
            return;
        }

        $data = $this->datos_peluquero();
        $data['created_at'] = date('Y-m-d H:i:s');

        $saved = $this->Peluquero_model->guardar_peluquero(
            NULL,
            $data,
            $this->filas_horarios(),
            $this->input->post('service_ids')
        );

        $this->responder_guardado($saved, 'Peluquero creado.');
    }

    /** Edita por POST; Peluquero_model revierte todo si se invalidaría una reserva futura. */
    public function actualizar($id)
    {
        $this->require_post();
        $barber = $this->Peluquero_model->buscar_peluquero($id);
        if (!$barber) {
            show_404();
        }

        $errors = $this->validar_datos();
        if ($errors) {
            $posted = array_merge($barber, $this->input->post(NULL, TRUE));
            $this->mostrar_formulario($posted, $errors, TRUE);
            return;
        }

        $saved = $this->Peluquero_model->guardar_peluquero(
            $id,
            $this->datos_peluquero(),
            $this->filas_horarios(),
            $this->input->post('service_ids')
        );

        $this->responder_guardado($saved, 'Peluquero actualizado.');
    }

    /** Recibe id/FALSE y texto de éxito; informa last_error del modelo y redirige al listado. */
    private function responder_guardado($saved, $success_message)
    {
        if (!$saved) {
            $this->session->set_flashdata(
                'error',
                $this->Peluquero_model->last_error ?: 'No se pudo guardar el peluquero.'
            );
        } else {
            $this->session->set_flashdata('success', $success_message);
        }

        redirect('peluqueros');
    }

    /** Muestra reservas futuras del peluquero; por POST valida destino/horario y reasigna. */
    public function reasignar($id)
    {
        $barber = $this->Peluquero_model->buscar_peluquero($id);
        if (!$barber) {
            show_404();
        }

        $errors = array();

        if ($this->input->method(TRUE) === 'POST') {
            $appointment_id = (int) $this->posted('appointment_id');
            $new_barber_id = (int) $this->posted('barber_id');
            $date = $this->posted('date');
            $time = $this->posted('time');
            $appointment = $this->Turno_model->buscar_turno($appointment_id);

            if (!$appointment || (int) $appointment['barber_id'] !== (int) $id || $appointment['payment']) {
                $errors[] = 'El turno no corresponde al peluquero o ya fue cobrado.';
            } else {
                $service_ids = array_column($appointment['services'], 'service_id');
                list($ok, $message, $start, $end) = $this->Turno_model->validar_horario(
                    $new_barber_id,
                    $service_ids,
                    $date,
                    $time,
                    $appointment_id
                );

                if (!$ok) {
                    $errors[] = $message;
                } elseif (!$this->Turno_model->reasignar_turno($appointment_id, $new_barber_id, $start, $end)) {
                    $errors[] = $this->Turno_model->last_error;
                } else {
                    $this->session->set_flashdata('success', 'Turno reasignado.');
                    redirect('peluqueros/reasignar/' . $id);
                    return;
                }
            }
        }

        $this->render('peluqueros/reasignar_turnos', array(
            'barber' => $barber,
            'appointments' => $this->Peluquero_model->obtener_turnos_futuros($id),
            'barbers' => $this->Peluquero_model->obtener_peluqueros(TRUE),
            'errors' => $errors,
        ), 'Reasignar turnos');
    }

    /**
     * Activa o desactiva al peluquero (REQ-16) sin cambiar horarios ni servicios.
     * Reutiliza guardar_peluquero: si hay turnos futuros que quedarían inválidos,
     * la transacción se revierte y no se aplica el cambio.
     */
    public function cambiar_estado($id)
    {
        $this->require_post();

        $barber = $this->Peluquero_model->buscar_peluquero($id);
        if (!$barber) {
            show_404();
        }

        $schedules = $this->Peluquero_model->consultar_horarios($id);
        $schedule_map = array();
        foreach ($schedules as $schedule) {
            $schedule_map[(int) $schedule['day_of_week']] = $schedule;
        }

        $schedule_rows = array();
        for ($day = 1; $day <= 7; $day++) {
            $enabled = isset($schedule_map[$day]);
            $schedule_rows[] = array(
                'day' => $day,
                'enabled' => $enabled ? 1 : 0,
                'start' => $enabled ? substr($schedule_map[$day]['start_time'], 0, 5) : '09:00',
                'end' => $enabled ? substr($schedule_map[$day]['end_time'], 0, 5) : '18:00',
            );
        }

        $service_ids = $this->Peluquero_model->obtener_servicios($id);
        $nuevo_estado = $barber['active'] ? 0 : 1;

        $data = array(
            'full_name' => $barber['full_name'],
            'phone' => $barber['phone'],
            'commission_percent' => (float) $barber['commission_percent'],
            'active' => $nuevo_estado,
            'updated_at' => date('Y-m-d H:i:s'),
        );

        $saved = $this->Peluquero_model->guardar_peluquero($id, $data, $schedule_rows, $service_ids);
        $mensaje = $nuevo_estado ? 'Peluquero activado.' : 'Peluquero desactivado.';

        $this->responder_guardado($saved, $mensaje);
    }

    /** Archiva por POST solo cuando no hay reservas futuras; conserva el historial. */
    public function eliminar($id)
    {
        $this->require_post();
        if (!$this->Peluquero_model->buscar_peluquero($id)) {
            show_404();
        }

        if ($this->Peluquero_model->obtener_turnos_futuros($id)) {
            $this->session->set_flashdata('error', 'Reasigná o cancelá los turnos futuros antes de eliminar al peluquero.');
        } elseif ($this->Peluquero_model->archivar_peluquero($id)) {
            $this->session->set_flashdata('success', 'Peluquero eliminado de la lista. Su historial se conservó.');
        } else {
            $this->session->set_flashdata('error', 'No se pudo eliminar el peluquero.');
        }
        redirect('peluqueros');
    }

    /** Restaura por POST el id archivado; recuerda revisar su jornada y servicios. */
    public function restaurar($id)
    {
        $this->require_post();
        if (!$this->Peluquero_model->esta_archivado($id)) {
            show_404();
        }

        if ($this->Peluquero_model->restaurar_peluquero($id)) {
            $this->session->set_flashdata('success', 'Peluquero restaurado. Revisá sus horarios y servicios.');
        } else {
            $this->session->set_flashdata('error', 'No se pudo restaurar el peluquero.');
        }
        redirect('peluqueros?archived=1');
    }
}
