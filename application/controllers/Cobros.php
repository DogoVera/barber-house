<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * CONTROLADOR: Cobros
 *
 * Registrar un cobro genera el ingreso y marca el turno como atendido.
 * Correcciones, anulaciones y formas de pago son tareas del Encargado.
 * Usa Cobro_model para ingresos/formas de pago y Turno_model para la atención.
 * Ambos roles pueden listar y cobrar con sesión válida. Valida importes y
 * coordina con el modelo la transacción que conserva el porcentaje histórico.
 */
class Cobros extends MY_Controller {

    public function __construct()
    {
        parent::__construct();
        $this->require_login();
        $this->load->model(array('Cobro_model', 'Turno_model'));
    }

    /** Lista por fechas/texto con 25 filas; solo Encargado puede ver los anulados. */
    public function index()
    {
        $q = trim((string) $this->input->get('q', TRUE));
        $from = (string) $this->input->get('from', TRUE);
        $to = (string) $this->input->get('to', TRUE);
        $archived = $this->input->get('archived') === '1'
            && $this->current_user['role_code'] === 'encargado';

        if ($from !== '' || $to !== '') {
            $range_from = valid_day($from) ? $from : date('Y-m-01');
            $range_to = valid_day($to) ? $to : date('Y-m-d');
        } else {
            $range_from = '1000-01-01';
            $range_to = '9999-12-31';
        }

        $pagination = $this->paginacion(
            $this->Cobro_model->contar_cobros($range_from, $range_to, $q, $archived)
        );

        $this->render('cobros/listado_cobros', array(
            'payments' => $this->Cobro_model->obtener_cobros(
                $range_from,
                $range_to,
                $q,
                $archived,
                $pagination['limit'],
                $pagination['offset']
            ),
            'q' => $q,
            'from' => $from,
            'to' => $to,
            'archived' => $archived,
            'paginacion' => $pagination,
            'ruta_paginacion' => 'cobros',
            'filtros_paginacion' => array(
                'q' => $q,
                'from' => $from,
                'to' => $to,
                'archived' => $archived ? '1' : '',
            ),
        ), 'Cobros');
    }

    /** Abre el cobro del id de turno; 404 si no existe y redirección si ya tiene cobro. */
    public function nuevo($appointment_id)
    {
        $appointment = $this->Turno_model->buscar_turno($appointment_id);
        if (!$appointment) {
            show_404();
        }
        if (!empty($appointment['payment'])) {
            redirect('cobros');
            return;
        }

        $this->render('cobros/formulario_cobro', array(
            'appointment' => $appointment,
            'methods' => $this->Cobro_model->obtener_formas_pago(),
            'errors' => array(),
        ), 'Registrar cobro');
    }

    /**
     * Valida turno, importe positivo y forma del POST; registra con Cobro_model.
     * El modelo comprueba vigencia/duplicados bajo bloqueo y devuelve éxito/mensaje.
     * Los errores simples vuelven al formulario; los de negocio vuelven al turno.
     */
    public function guardar()
    {
        $this->require_post();

        $appointment_id = (int) $this->posted('appointment_id');
        $appointment = $this->Turno_model->buscar_turno($appointment_id);
        if (!$appointment) {
            show_404();
        }

        $amount = $this->posted('amount');
        $method_id = (int) $this->posted('payment_method_id');
        $errors = array();

        if (!valid_money($amount, TRUE)) {
            $errors[] = 'El importe debe ser positivo y tener hasta dos decimales.';
        }
        if (!$method_id) {
            $errors[] = 'Seleccioná una forma de pago.';
        }

        if ($errors) {
            $this->render('cobros/formulario_cobro', array(
                'appointment' => $appointment,
                'methods' => $this->Cobro_model->obtener_formas_pago(),
                'errors' => $errors,
            ), 'Registrar cobro');
            return;
        }

        list($ok, $message) = $this->Cobro_model->registrar_cobro_turno(
            $appointment_id,
            $amount,
            $method_id,
            trim($this->posted('notes', '')),
            $this->current_user['id']
        );

        $this->session->set_flashdata($ok ? 'success' : 'error', $message);
        redirect($ok ? 'cobros' : 'turnos/ver/' . $appointment_id);
    }

    /** Solo Encargado abre la corrección del cobro vigente por id; en otro caso 403/404. */
    public function editar($id)
    {
        $this->require_manager();
        $payment = $this->Cobro_model->buscar_cobro($id);
        if (!$payment || $payment['voided_at']) {
            show_404();
        }

        $this->render('cobros/editar_cobro', array(
            'payment' => $payment,
            'methods' => $this->Cobro_model->obtener_formas_pago(),
            'errors' => array(),
        ), 'Corregir cobro');
    }

    /** Corrige por POST como Encargado; el modelo mantiene el porcentaje histórico. */
    public function actualizar($id)
    {
        $this->require_manager();
        $this->require_post();

        $payment = $this->Cobro_model->buscar_cobro($id);
        if (!$payment || $payment['voided_at']) {
            show_404();
        }

        $amount = $this->posted('amount');
        $method_id = (int) $this->posted('payment_method_id');
        $errors = array();

        if (!valid_money($amount, TRUE)) {
            $errors[] = 'Revisá el importe.';
        }
        if (!$method_id) {
            $errors[] = 'Seleccioná una forma de pago.';
        }

        if ($errors) {
            $payment = array_merge($payment, $this->input->post(NULL, TRUE));
            $this->render('cobros/editar_cobro', array(
                'payment' => $payment,
                'methods' => $this->Cobro_model->obtener_formas_pago(),
                'errors' => $errors,
            ), 'Corregir cobro');
            return;
        }

        $saved = $this->Cobro_model->actualizar_cobro(
            $id,
            $amount,
            $method_id,
            trim($this->posted('notes', ''))
        );

        $this->session->set_flashdata(
            $saved ? 'success' : 'error',
            $saved ? 'Cobro corregido.' : 'No se pudo corregir el cobro.'
        );
        redirect($saved ? 'contabilidad' : 'cobros/editar/' . $id);
    }

    /** Lista todas las formas de pago para Encargado. */
    public function formas_pago()
    {
        $this->require_manager();
        $this->render('cobros/formas_pago', array(
            'methods' => $this->Cobro_model->obtener_formas_pago(FALSE),
        ), 'Formas de pago');
    }

    /** Registra por POST un nombre de hasta 80 caracteres, sin duplicados; informa errores. */
    public function guardar_forma_pago()
    {
        $this->require_manager();
        $this->require_post();

        $name = trim($this->posted('name', ''));
        if ($name === '' || mb_strlen($name) > 80) {
            $this->session->set_flashdata('error', 'Ingresá un nombre de hasta 80 caracteres.');
        } elseif ($this->Cobro_model->existe_forma_pago($name)) {
            $this->session->set_flashdata('error', 'Esa forma de pago ya existe.');
        } elseif ($this->Cobro_model->registrar_forma_pago($name)) {
            $this->session->set_flashdata('success', 'Forma de pago agregada.');
        } else {
            $this->session->set_flashdata('error', 'No se pudo agregar la forma de pago.');
        }

        redirect('formas-pago');
    }

    /** Encargado anula por POST el cobro vigente del id y lo excluye de ingresos/comisiones. */
    public function eliminar($id)
    {
        $this->require_manager();
        $this->require_post();

        $payment = $this->Cobro_model->buscar_cobro($id);
        if (!$payment || $payment['voided_at']) {
            show_404();
        }

        $saved = $this->Cobro_model->anular_cobro($id, $this->current_user['id']);
        $this->session->set_flashdata(
            $saved ? 'success' : 'error',
            $saved ? 'Cobro anulado. Ya no suma a ingresos ni comisiones.' : 'No se pudo anular el cobro.'
        );
        redirect('cobros');
    }

    /** Encargado restaura por POST un cobro anulado; 404 si no está anulado. */
    public function restaurar($id)
    {
        $this->require_manager();
        $this->require_post();

        $payment = $this->Cobro_model->buscar_cobro($id);
        if (!$payment || !$payment['voided_at']) {
            show_404();
        }

        $saved = $this->Cobro_model->restaurar_cobro($id);
        $this->session->set_flashdata(
            $saved ? 'success' : 'error',
            $saved ? 'Cobro restaurado.' : 'No se pudo restaurar el cobro.'
        );
        redirect('cobros?archived=1');
    }
}
