<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * CONTROLADOR: Turnos
 *
 * El controlador recibe la petición, valida datos simples y delega las
 * reglas de agenda al Turno_model. Usa Cliente_model, Peluquero_model y
 * Servicio_model para preparar formularios. Ambos roles con sesión pueden
 * consultar agenda/disponibilidad, crear, editar y cambiar estados.
 * No permite alterar turnos cobrados y conserva los servicios históricos.
 */
class Turnos extends MY_Controller {

    public function __construct()
    {
        parent::__construct();
        $this->require_login();

        $this->load->model(array(
            'Turno_model',
            'Cliente_model',
            'Peluquero_model',
            'Servicio_model',
        ));
    }

    /** Muestra la agenda diaria o el historial completo paginado. */
    public function index()
    {
        $date = $this->input->get('date', TRUE);
        if ($date === NULL) {
            $date = date('Y-m-d');
        }
        if ($date !== '' && !valid_day($date)) {
            $date = date('Y-m-d');
        }

        $barber_id = (int) $this->input->get('barber_id');
        $q = trim((string) $this->input->get('q', TRUE));
        $status = (string) $this->input->get('status', TRUE);

        $allowed_statuses = array('', 'reserved', 'attended', 'cancelled', 'no_show');
        if (!in_array($status, $allowed_statuses, TRUE)) {
            $status = '';
        }

        $barber = $barber_id ?: NULL;
        $limit = 0;
        $offset = 0;
        $pagination = array();

        // La agenda de un día se ve completa. Solo el historial se pagina.
        if ($date === '') {
            $pagination = $this->paginacion(
                $this->Turno_model->contar_turnos($date, $barber, $q, $status)
            );
            $limit = $pagination['limit'];
            $offset = $pagination['offset'];
        }

        $this->render('turnos/listado_turnos', array(
            'date' => $date,
            'q' => $q,
            'status' => $status,
            'barber_id' => $barber_id,
            'appointments' => $this->Turno_model->obtener_turnos(
                $date,
                $barber,
                $q,
                $status,
                $limit,
                $offset
            ),
            'barbers' => $this->Peluquero_model->obtener_peluqueros(),
            'paginacion' => $pagination,
            'ruta_paginacion' => 'turnos',
            'filtros_paginacion' => array(
                'date' => '',
                'q' => $q,
                'status' => $status,
                'barber_id' => $barber_id,
            ),
        ), 'Agenda / Turnos');
    }

    /** Abre un turno reservado con formulario vacío. */
    public function nuevo()
    {
        $this->mostrar_formulario(NULL, array(), FALSE);
    }

    /** Abre una atención espontánea; propone estado atendido y la hora actual. */
    public function sin_reserva()
    {
        $this->mostrar_formulario(NULL, array(), TRUE);
    }

    /**
     * Devuelve en JSON los horarios libres para fecha, peluquero y servicios.
     * REQ-05 / REQ-19: consultar espacios disponibles.
     */
    public function disponibilidad()
    {
        $barber_id = (int) $this->input->get('barber_id');
        $date_input = $this->input->get('date', TRUE);
        $date = is_scalar($date_input) ? (string) $date_input : '';
        $service_ids = $this->input->get('service_ids');
        $exclude_id = (int) $this->input->get('appointment_id');

        if ($exclude_id) {
            $appointment = $this->Turno_model->buscar_turno($exclude_id);
            if (!$appointment || $appointment['payment']) {
                show_404();
                return;
            }
        }

        $result = $this->Turno_model->horarios_disponibles(
            $barber_id,
            $date,
            $service_ids,
            $exclude_id ?: NULL
        );

        $this->output
            ->set_content_type('application/json', 'utf-8')
            ->set_output(json_encode($result));
    }

    /** Carga el id solicitado o 404; si fue cobrado informa y vuelve al detalle. */
    public function editar($id)
    {
        $appointment = $this->Turno_model->buscar_turno($id);
        if (!$appointment) {
            show_404();
        }

        if ($appointment['payment']) {
            $this->session->set_flashdata(
                'error',
                'Un turno cobrado no puede modificarse. El Encargado puede corregir el cobro.'
            );
            redirect('turnos/ver/' . $id);
            return;
        }

        $this->mostrar_formulario($appointment, array(), (bool) $appointment['walk_in']);
    }

    /** Recibe turno o NULL, errores y modo espontáneo; $posted conserva la selección enviada. */
    private function mostrar_formulario($appointment, $errors, $walkin, $posted = FALSE)
    {
        if ($posted) {
            $ids = $this->input->post('service_ids');
            $selected_services = is_array($ids) ? array_map('intval', $ids) : array();
        } else {
            $selected_services = $appointment && !empty($appointment['services'])
                ? array_map('intval', array_column($appointment['services'], 'service_id'))
                : array();
        }

        $services = $this->Servicio_model->obtener_servicios(TRUE);

        // Al editar mostramos los snapshots del turno para no confundirlos con
        // precios/duraciones que pudieron cambiar posteriormente.
        if ($appointment && !empty($appointment['services'])) {
            foreach ($services as &$service) {
                foreach ($appointment['services'] as $saved_service) {
                    if ((int) $service['id'] === (int) $saved_service['service_id']) {
                        $service['price'] = $saved_service['price_snapshot'];
                        $service['duration_minutes'] = $saved_service['duration_snapshot'];
                    }
                }
            }
            unset($service);
        }

        $this->render('turnos/formulario_turno', array(
            'appointment' => $appointment,
            'errors' => $errors,
            'walkin' => $walkin,
            'clients' => $this->Cliente_model->buscar_clientes(),
            'barbers' => $this->Peluquero_model->obtener_peluqueros(TRUE),
            'services' => $services,
            'selected_services' => $selected_services,
        ), $appointment && !empty($appointment['id'])
            ? 'Editar turno'
            : ($walkin ? 'Atención sin turno' : 'Nuevo turno'));
    }

    /** Valida POST y agenda; recibe id excluido y devuelve errores, inicio, fin y servicios. */
    private function validar_formulario($exclude_id)
    {
        $errors = array();
        $client_id = (int) $this->posted('client_id');
        $barber_id = (int) $this->posted('barber_id');
        $service_ids = $this->input->post('service_ids');
        $date = $this->posted('date');
        $time = $this->posted('time');

        if (!$this->Cliente_model->buscar_cliente($client_id)) {
            $errors[] = 'Seleccioná un cliente válido.';
        }
        if (!$barber_id) {
            $errors[] = 'Seleccioná un peluquero.';
        }
        if (!is_array($service_ids) || !$service_ids) {
            $errors[] = 'Seleccioná al menos un servicio.';
        }
        if (!valid_day($date) || !valid_clock($time)) {
            $errors[] = 'Indicá una fecha y hora válidas.';
        }

        if ($errors) {
            return array($errors, NULL, NULL, array());
        }

        // REQ-29/32/33: el modelo calcula duración, comprueba horario y cruces.
        list($ok, $message, $start, $end, $services) = $this->Turno_model->validar_horario(
            $barber_id,
            $service_ids,
            $date,
            $time,
            $exclude_id
        );

        if (!$ok) {
            $errors[] = $message;
        }

        return array($errors, $start, $end, $services);
    }

    /**
     * Recibe id o NULL y modo espontáneo. Valida POST, prepara campos y llama a
     * Turno_model::guardar_turno. Si hay errores recuperables conserva el formulario;
     * si falla la transacción informa last_error. Al guardar redirige al detalle.
     */
    private function guardar_formulario($id, $walkin)
    {
        $this->require_post();

        $existing = $id ? $this->Turno_model->buscar_turno($id) : NULL;
        if ($id && !$existing) {
            show_404();
        }
        if ($existing && $existing['payment']) {
            show_error('No se puede modificar un turno cobrado.', 409);
        }

        list($errors, $start, $end, $services) = $this->validar_formulario($id);

        if ($errors) {
            // Conservamos lo que el usuario escribió para que no pierda datos.
            $posted_data = $this->input->post(NULL, TRUE);
            $appointment = $existing
                ? array_merge($existing, $posted_data)
                : array_merge($posted_data, array('id' => NULL));

            $this->mostrar_formulario($appointment, $errors, $walkin, TRUE);
            return;
        }

        $status = $this->posted('status', 'reserved');
        $allowed_statuses = array('reserved', 'attended', 'cancelled', 'no_show');
        if (!in_array($status, $allowed_statuses, TRUE)) {
            $this->mostrar_formulario($existing, array('Estado inválido.'), $walkin, TRUE);
            return;
        }

        $data = array(
            'client_id' => (int) $this->posted('client_id'),
            'barber_id' => (int) $this->posted('barber_id'),
            'start_at' => $start,
            'end_at' => $end,
            'status' => $status,
            'notes' => trim($this->posted('notes', '')),
            'walk_in' => $walkin ? 1 : 0,
            'updated_at' => date('Y-m-d H:i:s'),
        );

        if (!$id) {
            $data['created_at'] = date('Y-m-d H:i:s');
            $data['created_by'] = $this->current_user['id'];
        }

        $saved_id = $this->Turno_model->guardar_turno($id, $data, $services);
        if (!$saved_id) {
            $this->session->set_flashdata('error', $this->Turno_model->last_error);
            redirect('turnos');
            return;
        }

        $this->session->set_flashdata(
            'success',
            $id ? 'Turno actualizado.' : 'Turno registrado.'
        );
        redirect('turnos/ver/' . $saved_id);
    }

    /** Atiende el POST de alta y lee walk_in para distinguir una llegada espontánea. */
    public function guardar()
    {
        $this->guardar_formulario(NULL, (bool) $this->posted('walk_in', 0));
    }

    /** Atiende el POST de edición del id recibido por ruta. */
    public function actualizar($id)
    {
        $this->guardar_formulario($id, (bool) $this->posted('walk_in', 0));
    }

    /** Muestra el turno completo por id; 404 cuando no existe. */
    public function ver($id)
    {
        $appointment = $this->Turno_model->buscar_turno($id);
        if (!$appointment) {
            show_404();
        }

        $this->render('turnos/detalle_turno', array(
            'appointment' => $appointment,
        ), 'Detalle del turno');
    }

    /** Cambia el estado respetando cobros y concurrencia. */
    public function cambiar_estado($id)
    {
        $this->require_post();

        $status = $this->posted('status');
        $allowed_statuses = array('reserved', 'attended', 'cancelled', 'no_show');
        if (!in_array($status, $allowed_statuses, TRUE)) {
            $this->session->set_flashdata('error', 'Estado inválido.');
            redirect('turnos/ver/' . $id);
            return;
        }

        list($ok, $message) = $this->Turno_model->cambiar_estado($id, $status);
        $this->session->set_flashdata($ok ? 'success' : 'error', $message);
        redirect('turnos/ver/' . $id);
    }

    /** Eliminar de la agenda equivale a cancelar, conservando historial. */
    public function eliminar($id)
    {
        $this->require_post();

        list($ok, $message) = $this->Turno_model->cambiar_estado($id, 'cancelled');
        $this->session->set_flashdata($ok ? 'success' : 'error', $message);
        redirect('turnos/ver/' . $id);
    }
}
