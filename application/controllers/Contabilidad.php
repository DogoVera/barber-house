<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * CONTROLADOR: Contabilidad
 *
 * Esta sección es exclusiva del Encargado. Los totales se calculan con
 * cobros y egresos vigentes; los anulados se conservan pero no suman.
 * Utiliza Cobro_model para ingresos/comisiones/liquidación y Egreso_model
 * para categorías y registro, corrección, anulación y restauración de gastos.
 * Prepara movimientos y resultado del local; no ejecuta consultas directamente.
 */
class Contabilidad extends MY_Controller {

    public function __construct()
    {
        parent::__construct();
        $this->require_manager();
        $this->load->model(array('Cobro_model', 'Egreso_model'));
    }

    /** Devuelve el período seleccionado, siempre ordenado y válido. */
    private function periodo_seleccionado()
    {
        $from = $this->input->get('from', TRUE) ?: date('Y-m-01');
        $to = $this->input->get('to', TRUE) ?: date('Y-m-d');

        if (!valid_day($from)) {
            $from = date('Y-m-01');
        }
        if (!valid_day($to)) {
            $to = date('Y-m-d');
        }

        if ($from > $to) {
            $temporary = $from;
            $from = $to;
            $to = $temporary;
        }

        return array($from, $to);
    }

    /** Resumen económico y liquidación por peluquero. */
    public function index()
    {
        list($from, $to) = $this->periodo_seleccionado();

        $income = $this->Cobro_model->total_ingresos($from, $to);
        $expenses = $this->Egreso_model->total_egresos($from, $to);
        $commissions = $this->Cobro_model->total_comisiones($from, $to);

        $payments = $this->Cobro_model->obtener_cobros($from, $to);
        $expense_rows = $this->Egreso_model->obtener_egresos($from, $to);

        // Armamos una sola línea de tiempo para la vista. La vista solo la muestra.
        $movements = array();

        foreach ($payments as $payment) {
            $movements[] = array(
                'date' => $payment['paid_at'],
                'kind' => 'Cobro',
                'detail' => $payment['client_name'] . ' · ' . $payment['barber_name']
                    . ' · ' . $payment['payment_method']
                    . ' · comisión ' . $payment['commission_percent_snapshot'] . '%',
                'amount' => $payment['amount'],
                'link' => site_url('cobros/editar/' . $payment['id']),
            );
        }

        foreach ($expense_rows as $expense) {
            $movements[] = array(
                'date' => $expense['expense_date'] . ' ' . substr($expense['created_at'], 11, 8),
                'kind' => 'Egreso',
                'detail' => $expense['concept'] . ' · ' . $expense['category_name'],
                'amount' => -$expense['amount'],
                'link' => site_url('egresos/editar/' . $expense['id']),
            );
        }

        usort($movements, function ($a, $b) {
            return strcmp($b['date'], $a['date']);
        });

        $this->render('contabilidad/resumen_contabilidad', array(
            'from' => $from,
            'to' => $to,
            'income' => $income,
            'expenses_total' => $expenses,
            'commissions' => $commissions,
            'result' => $income - $expenses - $commissions,
            'movements' => $movements,
            'production' => $this->Cobro_model->calcular_liquidacion($from, $to),
            'categories' => $this->Egreso_model->obtener_categorias(),
        ), 'Contabilidad');
    }

    /** Listado paginado de egresos. */
    public function egresos()
    {
        $from = (string) $this->input->get('from', TRUE);
        $to = (string) $this->input->get('to', TRUE);
        $q = trim((string) $this->input->get('q', TRUE));
        $archived = $this->input->get('archived') === '1';

        if (!valid_day($from)) {
            $from = '';
        }
        if (!valid_day($to)) {
            $to = '';
        }

        $range_from = $from === '' ? '1000-01-01' : $from;
        $range_to = $to === '' ? '9999-12-31' : $to;

        $pagination = $this->paginacion(
            $this->Egreso_model->contar_egresos($range_from, $range_to, $q, $archived)
        );

        $this->render('contabilidad/listado_egresos', array(
            'from' => $from,
            'to' => $to,
            'q' => $q,
            'archived' => $archived,
            'expenses' => $this->Egreso_model->obtener_egresos(
                $range_from,
                $range_to,
                $q,
                $archived,
                $pagination['limit'],
                $pagination['offset']
            ),
            'categories' => $this->Egreso_model->obtener_categorias(),
            'paginacion' => $pagination,
            'ruta_paginacion' => 'egresos',
            'filtros_paginacion' => array(
                'q' => $q,
                'from' => $from,
                'to' => $to,
                'archived' => $archived ? '1' : '',
            ),
        ), 'Egresos');
    }

    /** Lee el POST; devuelve campos del gasto o FALSE si fecha/categoría/concepto/importe fallan. */
    private function datos_egreso()
    {
        $date = $this->posted('expense_date');
        $category_id = (int) $this->posted('category_id');
        $concept = trim($this->posted('concept', ''));
        $amount = $this->posted('amount');

        if (!valid_day($date)
            || !$this->Egreso_model->categoria_valida($category_id)
            || $concept === ''
            || !valid_money($amount, TRUE)) {
            return FALSE;
        }

        return array(
            'expense_date' => $date,
            'category_id' => $category_id,
            'concept' => $concept,
            'amount' => $amount,
            'updated_at' => date('Y-m-d H:i:s'),
        );
    }

    /** Registra un gasto por POST con autoría; ante datos inválidos informa y vuelve al listado. */
    public function guardar_egreso()
    {
        $this->require_post();
        $data = $this->datos_egreso();

        if (!$data) {
            $this->session->set_flashdata(
                'error',
                'Completá fecha válida, categoría activa, concepto e importe positivo.'
            );
            redirect('egresos');
            return;
        }

        $data['created_by'] = $this->current_user['id'];
        $data['created_at'] = date('Y-m-d H:i:s');

        $saved = $this->Egreso_model->registrar_egreso($data);
        $this->session->set_flashdata(
            $saved ? 'success' : 'error',
            $saved ? 'Egreso registrado.' : 'No se pudo registrar el egreso.'
        );
        redirect('egresos');
    }

    /** Muestra la corrección de un gasto vigente por id, o responde 404. */
    public function editar_egreso($id)
    {
        $expense = $this->Egreso_model->buscar_egreso($id);
        if (!$expense || $expense['voided_at']) {
            show_404();
        }

        $this->render('contabilidad/formulario_egreso', array(
            'expense' => $expense,
            'categories' => $this->Egreso_model->obtener_categorias(),
            'errors' => array(),
        ), 'Corregir egreso');
    }

    /** Valida y corrige el id por POST; si falla validación conserva los datos del formulario. */
    public function actualizar_egreso($id)
    {
        $this->require_post();

        $expense = $this->Egreso_model->buscar_egreso($id);
        if (!$expense || $expense['voided_at']) {
            show_404();
        }

        $data = $this->datos_egreso();
        if (!$data) {
            $this->render('contabilidad/formulario_egreso', array(
                'expense' => array_merge($expense, $this->input->post(NULL, TRUE)),
                'categories' => $this->Egreso_model->obtener_categorias(),
                'errors' => array('Revisá los datos del egreso.'),
            ), 'Corregir egreso');
            return;
        }

        $saved = $this->Egreso_model->actualizar_egreso($id, $data);
        $this->session->set_flashdata(
            $saved ? 'success' : 'error',
            $saved ? 'Egreso corregido.' : 'No se pudo corregir el egreso.'
        );
        redirect($saved ? 'egresos' : 'egresos/editar/' . $id);
    }

    /** Anula por POST sin borrar: guarda fecha y usuario; 404 si no está vigente. */
    public function anular_egreso($id)
    {
        $this->require_post();
        $expense = $this->Egreso_model->buscar_egreso($id);
        if (!$expense || $expense['voided_at']) {
            show_404();
        }

        $saved = $this->Egreso_model->anular_egreso($id, $this->current_user['id']);
        $this->session->set_flashdata(
            $saved ? 'success' : 'error',
            $saved ? 'Egreso anulado.' : 'No se pudo anular el egreso.'
        );
        redirect('egresos');
    }

    /** Restaura por POST un gasto anulado para incluirlo nuevamente en el resultado. */
    public function restaurar_egreso($id)
    {
        $this->require_post();
        $expense = $this->Egreso_model->buscar_egreso($id);
        if (!$expense || !$expense['voided_at']) {
            show_404();
        }

        $saved = $this->Egreso_model->restaurar_egreso($id);
        $this->session->set_flashdata(
            $saved ? 'success' : 'error',
            $saved ? 'Egreso restaurado.' : 'No se pudo restaurar el egreso.'
        );
        redirect('egresos?archived=1');
    }
}
