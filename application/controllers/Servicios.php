<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * CONTROLADOR: Servicios
 * Administra catálogo, precios, duración, actividad, archivo y restauración.
 * Utiliza Servicio_model. Todas sus operaciones requieren perfil Encargado.
 * Valida el POST antes de guardar y comunica errores sin cambiar históricos.
 */
class Servicios extends MY_Controller {

    public function __construct()
    {
        parent::__construct();
        $this->require_manager();
        $this->load->model('Servicio_model');
    }

    /** Lista servicios filtrados por nombre y archivo. */
    public function index()
    {
        $q = trim((string) $this->input->get('q', TRUE));
        $archived = $this->input->get('archived') === '1';

        $this->render('servicios/listado_servicios', array(
            'services' => $this->Servicio_model->obtener_servicios(FALSE, $archived, $q),
            'q' => $q,
            'archived' => $archived,
        ), 'Servicios');
    }

    /** Muestra el formulario vacío de servicio. */
    public function nuevo()
    {
        $this->render('servicios/formulario_servicio', array('service' => NULL, 'errors' => array()), 'Nuevo servicio');
    }

    /** Devuelve errores de nombre, precio no negativo y duración entera positiva del POST. */
    private function validar_datos()
    {
        $errors = array();

        if (trim($this->posted('name', '')) === '') {
            $errors[] = 'El nombre es obligatorio.';
        }

        // El precio puede ser cero, pero no negativo ni con formato inválido.
        if (!valid_money($this->posted('price'))) {
            $errors[] = 'El precio debe ser válido y no negativo.';
        }

        $duration = $this->posted('duration_minutes');
        if (!is_scalar($duration) || !preg_match('/^[1-9][0-9]{0,3}$/D', (string) $duration)) {
            $errors[] = 'La duración debe ser mayor a 0.';
        }

        return $errors;
    }

    /** Prepara los campos validados del catálogo para Servicio_model. */
    private function datos_servicio()
    {
        return array(
            'name' => trim($this->posted('name', '')),
            'price' => (float) $this->posted('price', 0),
            'duration_minutes' => (int) $this->posted('duration_minutes', 0),
            'active' => $this->posted('active', 0) ? 1 : 0,
            'updated_at' => date('Y-m-d H:i:s'),
        );
    }

    /** Registra por POST; ante errores vuelve al formulario conservando los datos. */
    public function guardar()
    {
        $this->require_post();
        $errors = $this->validar_datos();

        if ($errors) {
            $this->render('servicios/formulario_servicio', array(
                'service' => $this->input->post(NULL, TRUE),
                'errors' => $errors,
            ), 'Nuevo servicio');
            return;
        }

        $data = $this->datos_servicio();
        $data['created_at'] = date('Y-m-d H:i:s');

        if ($this->Servicio_model->registrar_servicio($data)) {
            $this->session->set_flashdata('success', 'Servicio creado.');
        } else {
            $this->session->set_flashdata('error', 'No se pudo crear el servicio.');
        }
        redirect('servicios');
    }

    /** Carga el servicio por id o responde 404. */
    public function editar($id)
    {
        $service = $this->Servicio_model->buscar_servicio($id);
        if (!$service) {
            show_404();
        }
        $this->render('servicios/formulario_servicio', array('service' => $service, 'errors' => array()), 'Editar servicio');
    }

    /** Actualiza por POST el catálogo; los snapshots de turnos anteriores permanecen guardados. */
    public function actualizar($id)
    {
        $this->require_post();
        $service = $this->Servicio_model->buscar_servicio($id);
        if (!$service) {
            show_404();
        }

        $errors = $this->validar_datos();
        if ($errors) {
            $this->render('servicios/formulario_servicio', array(
                'service' => array_merge($service, $this->input->post(NULL, TRUE)),
                'errors' => $errors,
            ), 'Editar servicio');
            return;
        }

        if ($this->Servicio_model->actualizar_servicio($id, $this->datos_servicio())) {
            $this->session->set_flashdata('success', 'Servicio actualizado.');
        } else {
            $this->session->set_flashdata('error', 'No se pudo actualizar el servicio.');
        }
        redirect('servicios');
    }

    /** Archiva por POST si no participa en reservas futuras; informa el motivo si se rechaza. */
    public function eliminar($id)
    {
        $this->require_post();
        if (!$this->Servicio_model->buscar_servicio($id)) {
            show_404();
        }

        if ($this->Servicio_model->tiene_turnos_futuros($id)) {
            $this->session->set_flashdata('error', 'El servicio está en turnos futuros. Modificalos o cancelalos antes de eliminarlo.');
        } elseif ($this->Servicio_model->archivar_servicio($id)) {
            $this->session->set_flashdata('success', 'Servicio eliminado de la lista. Los precios históricos se conservaron.');
        } else {
            $this->session->set_flashdata('error', 'No se pudo eliminar el servicio.');
        }
        redirect('servicios');
    }

    /** Restaura por POST un servicio archivado; 404 si el id no está archivado. */
    public function restaurar($id)
    {
        $this->require_post();
        if (!$this->Servicio_model->esta_archivado($id)) {
            show_404();
        }

        if ($this->Servicio_model->restaurar_servicio($id)) {
            $this->session->set_flashdata('success', 'Servicio restaurado.');
        } else {
            $this->session->set_flashdata('error', 'No se pudo restaurar el servicio.');
        }
        redirect('servicios?archived=1');
    }
}
