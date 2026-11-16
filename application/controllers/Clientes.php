<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * CONTROLADOR: Clientes
 * Coordina listado/búsqueda, alta, edición, detalle, archivo y restauración.
 * Utiliza Cliente_model para datos personales e historial de atenciones.
 * Recepcionista y Encargado pueden operar con sesión iniciada.
 * Valida datos obligatorios/duplicados; ante errores vuelve al formulario o
 * informa mediante mensajes de sesión. Las bajas conservan el historial.
 */
class Clientes extends MY_Controller {

    public function __construct()
    {
        parent::__construct();
        $this->require_login();
        $this->load->model('Cliente_model');
    }

    /** Muestra clientes activos/archivados filtrados por q, con 25 filas por página. */
    public function index()
    {
        $q = trim((string) $this->input->get('q', TRUE));
        $archived = $this->input->get('archived') === '1';
        $pagination = $this->paginacion($this->Cliente_model->contar_clientes($q, $archived));

        $this->render('clientes/listado_clientes', array(
            'clients' => $this->Cliente_model->buscar_clientes(
                $q,
                $archived,
                $pagination['limit'],
                $pagination['offset']
            ),
            'q' => $q,
            'archived' => $archived,
            'paginacion' => $pagination,
            'ruta_paginacion' => 'clientes',
            'filtros_paginacion' => array(
                'q' => $q,
                'archived' => $archived ? '1' : '',
            ),
        ), 'Clientes');
    }

    /** Abre un formulario vacío; no escribe en la base de datos. */
    public function nuevo()
    {
        $this->render('clientes/formulario_cliente', array(
            'client' => NULL,
            'errors' => array(),
        ), 'Nuevo cliente');
    }

    /** Devuelve errores del POST; excluye el id editado al consultar duplicados. */
    private function validar_datos($exclude_id = NULL)
    {
        $errors = array();
        $first = trim($this->posted('first_name', ''));
        $last = trim($this->posted('last_name', ''));
        $phone = trim($this->posted('phone', ''));
        $tipo = trim($this->posted('tipo', ''));

        if ($first === '') {
            $errors[] = 'El nombre es obligatorio.';
        }
        if ($last === '') {
            $errors[] = 'El apellido es obligatorio.';
        }
        if ($phone === '') {
            $errors[] = 'El teléfono es obligatorio.';
        }
        if ($tipo === '') {
            $errors[] = 'El tipo de cliente es obligatorio.';
        }
        
        if (!$errors && $this->Cliente_model->buscar_duplicado($first, $last, $phone, $exclude_id)) {
            $errors[] = 'Ya existe un cliente con estos datos. Buscalo para reutilizarlo.';
        }

        return $errors;
    }

    /** Prepara los campos de clients desde el POST filtrado, sin guardar todavía. */
    private function datos_cliente()
    {
        return array(
            'first_name' => trim($this->posted('first_name', '')),
            'last_name' => trim($this->posted('last_name', '')),
            'phone' => trim($this->posted('phone', '')),
            'tipo' => trim($this->posted('tipo', '')),
            'notes' => trim($this->posted('notes', '')),
            
            'updated_at' => date('Y-m-d H:i:s'), 
          
        );
    }

    /** Valida el POST y registra con Cliente_model; conserva los datos si son inválidos. */
    public function guardar()
    {
        $this->require_post();
        $errors = $this->validar_datos();

        if ($errors) {
            $this->render('clientes/formulario_cliente', array(
                'client' => $this->input->post(NULL, TRUE),
                'errors' => $errors,
            ), 'Nuevo cliente');
            return;
        }

        $data = $this->datos_cliente();
        $data['created_at'] = date('Y-m-d H:i:s');
        $id = $this->Cliente_model->registrar_cliente($data);

        if (!$id) {
            $this->session->set_flashdata('error', 'No se pudo registrar el cliente.');
            redirect('clientes');
            return;
        }

        $this->session->set_flashdata('success', 'Cliente registrado.');
        redirect('clientes/ver/' . $id);
    }

    /** Recibe id de ruta, carga el formulario o responde 404 si el cliente no existe. */
    public function editar($id)
    {
        $client = $this->Cliente_model->buscar_cliente($id);
        if (!$client) {
            show_404();
        }

        $this->render('clientes/formulario_cliente', array(
            'client' => $client,
            'errors' => array(),
        ), 'Editar cliente');
    }

    /** Actualiza por id vía POST; ante validación fallida vuelve a mostrar lo enviado. */
    public function actualizar($id)
    {
        $this->require_post();
        $client = $this->Cliente_model->buscar_cliente($id);
        if (!$client) {
            show_404();
        }

        $errors = $this->validar_datos($id);
        if ($errors) {
            $this->render('clientes/formulario_cliente', array(
                'client' => array_merge($client, $this->input->post(NULL, TRUE)),
                'errors' => $errors,
            ), 'Editar cliente');
            return;
        }

        if (!$this->Cliente_model->actualizar_cliente($id, $this->datos_cliente())) {
            $this->session->set_flashdata('error', 'No se pudo actualizar el cliente.');
        } else {
            $this->session->set_flashdata('success', 'Cliente actualizado.');
        }

        redirect('clientes/ver/' . $id);
    }

    /** Muestra datos e historial del id solicitado; responde 404 si está ausente/archivado. */
    public function ver($id)
    {
        $client = $this->Cliente_model->buscar_cliente($id);
        if (!$client) {
            show_404();
        }

        $this->render('clientes/detalle_cliente', array(
            'client' => $client,
            'history' => $this->Cliente_model->obtener_historial($id),
        ), 'Cliente');
    }

    /** Archiva por POST solo si el cliente no tiene reservas futuras; informa el resultado. */
    public function eliminar($id)
    {
        $this->require_post();
        if (!$this->Cliente_model->buscar_cliente($id)) {
            show_404();
        }

        if ($this->Cliente_model->tiene_reservas_futuras($id)) {
            $this->session->set_flashdata('error', 'Este cliente tiene turnos futuros. Cancelalos antes de eliminarlo.');
        } elseif ($this->Cliente_model->archivar_cliente($id)) {
            $this->session->set_flashdata('success', 'Cliente eliminado de la lista. Su historial se conservó.');
        } else {
            $this->session->set_flashdata('error', 'No se pudo eliminar el cliente.');
        }

        redirect('clientes');
    }

    /** Restaura por POST un id archivado si no duplica los datos de otro cliente vigente. */
    public function restaurar($id)
    {
        $this->require_post();
        $client = $this->Cliente_model->buscar_archivado($id);
        if (!$client) {
            show_404();
        }

        if ($this->Cliente_model->buscar_duplicado($client['first_name'], $client['last_name'], $client['phone'], $client['tipo'], $id)) {
            $this->session->set_flashdata('error', 'Ya existe un cliente activo con esos datos.');
        } elseif ($this->Cliente_model->restaurar_cliente($id)) {
            $this->session->set_flashdata('success', 'Cliente restaurado.');
        } else {
            $this->session->set_flashdata('error', 'No se pudo restaurar el cliente.');
        }

        redirect('clientes?archived=1');
    }
}
