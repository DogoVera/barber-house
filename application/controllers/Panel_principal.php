<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * CONTROLADOR: Panel_principal
 * Prepara el resumen diario usando Turno_model, Cobro_model y Egreso_model.
 * Ambos roles ven agenda e ingresos; solo Encargado recibe gastos, comisiones
 * y resultado del local. Este módulo consulta información y no modifica datos.
 */
class Panel_principal extends MY_Controller {

    public function __construct()
    {
        parent::__construct();
        $this->require_login();
        $this->load->model(array(
            'Turno_model',
            'Cobro_model',
            'Egreso_model',
        ));
    }

    /** Cuenta estados de los turnos de hoy y prepara los indicadores según el rol. */
    public function index()
    {
        $today = date('Y-m-d');
        $appointments = $this->Turno_model->obtener_turnos($today);

        $counts = array(
            'total' => count($appointments),
            'attended' => 0,
            'reserved' => 0,
            'cancelled' => 0,
            'no_show' => 0,
        );

        // Un foreach simple alimenta los contadores por estado.
        foreach ($appointments as $appointment) {
            if (isset($counts[$appointment['status']])) {
                $counts[$appointment['status']]++;
            }
        }

        $data = array(
            'appointments' => $appointments,
            'recent' => $appointments,
            'counts' => $counts,
            'income' => $this->Cobro_model->total_ingresos($today, $today),
        );

        if ($this->current_user['role_code'] === 'encargado') {
            $data['expenses'] = $this->Egreso_model->total_egresos($today, $today);
            $data['commissions'] = $this->Cobro_model->total_comisiones($today, $today);
            $data['local_result'] = $data['income'] - $data['expenses'] - $data['commissions'];
        }

        $this->render('panel/resumen_jornada', $data, 'Inicio');
    }
}
