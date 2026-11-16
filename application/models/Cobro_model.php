<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * MODELO: Cobro_model
 * Tablas: payments, payment_methods, appointments, barbers y clients.
 * Utilizado por Cobros, Contabilidad y Panel_principal para registrar ingresos,
 * corregir/anular/restaurar cobros, administrar formas de pago y liquidar períodos.
 *
 * Un cobro representa el ingreso del local y guarda el porcentaje de comisión
 * histórico del peluquero. Los anulados se conservan pero no suman.
 */
class Cobro_model extends CI_Model {

    /** Lista formas de pago; TRUE limita a las activas para registrar/corregir cobros. */
    public function obtener_formas_pago($active_only = TRUE)
    {
        if ($active_only) {
            $this->db->where('active', 1);
        }

        return $this->db->order_by('name')->get('payment_methods')->result_array();
    }

    /** Comprueba por nombre la restricción de unicidad, incluso en formas inactivas. */
    public function existe_forma_pago($name)
    {
        return $this->db->where('name', $name)
            ->count_all_results('payment_methods') > 0;
    }

    /** Inserta una forma activa con nombre previamente validado; devuelve booleano. */
    public function registrar_forma_pago($name)
    {
        return $this->db->insert('payment_methods', array(
            'name' => $name,
            'active' => 1,
        ));
    }

    /** Filtros compartidos por listado y conteo. */
    private function filtros_listado($q, $archived)
    {
        $this->db->where($archived ? 'p.voided_at IS NOT NULL' : 'p.voided_at IS NULL', NULL, FALSE);

        if ($q !== '') {
            $this->db->group_start()
                ->like('c.first_name', $q)
                ->or_like('c.last_name', $q)
                ->or_like('b.full_name', $q)
                ->or_like('pm.name', $q)
                ->group_end();
        }
    }

    /** Base del listado de cobros dentro de un rango. */
    private function consulta_cobros($from, $to, $q, $archived)
    {
        $this->db->select("p.*, pm.name payment_method,
            CONCAT(c.first_name, ' ', c.last_name) client_name,
            b.full_name barber_name", FALSE)
            ->from('payments p')
            ->join('payment_methods pm', 'pm.id = p.payment_method_id')
            ->join('appointments a', 'a.id = p.appointment_id')
            ->join('clients c', 'c.id = a.client_id')
            ->join('barbers b', 'b.id = a.barber_id')
            ->where('p.paid_at >=', $from . ' 00:00:00')
            ->where('p.paid_at <=', $to . ' 23:59:59');

        $this->filtros_listado($q, $archived);
    }

    /** Busca por id, incluidos anulados; devuelve array o NULL con datos del turno. */
    public function buscar_cobro($id)
    {
        return $this->db->select('p.*, a.client_id, a.barber_id, a.start_at, pm.name payment_method')
            ->from('payments p')
            ->join('appointments a', 'a.id = p.appointment_id')
            ->join('payment_methods pm', 'pm.id = p.payment_method_id')
            ->where('p.id', $id)
            ->get()
            ->row_array();
    }

    /** Devuelve el único cobro del turno, incluso anulado, o NULL. */
    public function por_turno($appointment_id)
    {
        return $this->db->where('appointment_id', $appointment_id)
            ->get('payments')
            ->row_array();
    }

    /** Guarda fecha/usuario de anulación sin borrar el ingreso histórico; booleano. */
    public function anular_cobro($id, $user_id)
    {
        return $this->db->where('id', $id)
            ->where('voided_at IS NULL', NULL, FALSE)
            ->update('payments', array(
                'voided_at' => date('Y-m-d H:i:s'),
                'voided_by' => $user_id,
            ));
    }

    /** Quita la anulación por id para volver a incluir el cobro en los totales. */
    public function restaurar_cobro($id)
    {
        return $this->db->where('id', $id)
            ->where('voided_at IS NOT NULL', NULL, FALSE)
            ->update('payments', array(
                'voided_at' => NULL,
                'voided_by' => NULL,
            ));
    }

    /**
     * Registra el cobro dentro de una transacción.
     *
     * REQ-43/48: el cobro genera el ingreso y marca el turno atendido.
     * REQ-50: se congela porcentaje y monto de comisión.
     * Recibe id de turno, importe validado, forma, observación y usuario autor.
     * Rechaza turnos cancelados/ausentes, cobros duplicados y formas inactivas.
     * Devuelve array(booleano, mensaje); cualquier error revierte ambos registros.
     */
    public function registrar_cobro_turno($appointment_id, $amount, $method_id, $notes, $user_id)
    {
        $this->db->trans_begin();

        // El turno queda bloqueado mientras se comprueba/crea el cobro.
        $appointment = $this->db->query(
            'SELECT a.*, b.commission_percent
               FROM appointments a
               JOIN barbers b ON b.id = a.barber_id
              WHERE a.id = ? FOR UPDATE',
            array($appointment_id)
        )->row_array();

        if (!$appointment
            || in_array($appointment['status'], array('cancelled', 'no_show'), TRUE)
            || $this->por_turno($appointment_id)) {
            $this->db->trans_rollback();
            return array(FALSE, 'El turno no se puede cobrar en su estado actual o ya tiene un cobro.');
        }

        if (!$this->forma_pago_activa($method_id)) {
            $this->db->trans_rollback();
            return array(FALSE, 'Seleccioná una forma de pago activa.');
        }

        $percent = (float) $appointment['commission_percent'];
        $commission = round((float) $amount * $percent / 100, 2);

        $this->db->insert('payments', array(
            'appointment_id' => $appointment_id,
            'payment_method_id' => $method_id,
            'amount' => $amount,
            'commission_percent_snapshot' => $percent,
            'commission_amount' => $commission,
            'notes' => $notes,
            'paid_at' => date('Y-m-d H:i:s'),
            'created_by' => $user_id,
        ));

        $this->db->where('id', $appointment_id)->update('appointments', array(
            'status' => 'attended',
            'updated_at' => date('Y-m-d H:i:s'),
        ));

        if ($this->db->trans_status() === FALSE) {
            $this->db->trans_rollback();
            return array(FALSE, 'No se pudo registrar el cobro.');
        }

        $this->db->trans_commit();
        return array(TRUE, 'Cobro registrado e ingreso generado.');
    }

    /** Valida que la forma exista y esté habilitada antes de escribir un cobro. */
    private function forma_pago_activa($id)
    {
        return $id > 0 && $this->db->where('id', $id)
            ->where('active', 1)
            ->count_all_results('payment_methods') > 0;
    }

    /**
     * Recibe id, importe, forma y nota; devuelve FALSE si no está vigente o la forma
     * no está activa. Recalcula el monto con el porcentaje histórico, no el actual.
     */
    public function actualizar_cobro($id, $amount, $method_id, $notes)
    {
        $payment = $this->buscar_cobro($id);
        if (!$payment || $payment['voided_at'] || !$this->forma_pago_activa($method_id)) {
            return FALSE;
        }

        $commission = round(
            (float) $amount * (float) $payment['commission_percent_snapshot'] / 100,
            2
        );

        return $this->db->where('id', $id)->update('payments', array(
            'amount' => $amount,
            'payment_method_id' => $method_id,
            'commission_amount' => $commission,
            'notes' => $notes,
            'updated_at' => date('Y-m-d H:i:s'),
        ));
    }

    /** Total cobrado vigente entre fechas inclusivas YYYY-MM-DD; devuelve float o 0. */
    public function total_ingresos($from, $to)
    {
        $row = $this->db->select_sum('amount', 'total')
            ->where('voided_at IS NULL', NULL, FALSE)
            ->where('paid_at >=', $from . ' 00:00:00')
            ->where('paid_at <=', $to . ' 23:59:59')
            ->get('payments')
            ->row_array();

        return (float) ($row['total'] ?: 0);
    }

    /** Suma las comisiones históricas de cobros vigentes dentro del período. */
    public function total_comisiones($from, $to)
    {
        $row = $this->db->select_sum('commission_amount', 'total')
            ->where('voided_at IS NULL', NULL, FALSE)
            ->where('paid_at >=', $from . ' 00:00:00')
            ->where('paid_at <=', $to . ' 23:59:59')
            ->get('payments')
            ->row_array();

        return (float) ($row['total'] ?: 0);
    }

    /**
     * Recibe fechas inclusivas; devuelve una fila por peluquero con cantidad de
     * atenciones, producción real cobrada y pago (suma de comisiones históricas).
     * No incluye anulados ni aplica nuevamente el porcentaje actual del peluquero.
     */
    public function calcular_liquidacion($from, $to)
    {
        return $this->db->select('b.id, b.full_name, COUNT(p.id) attentions,
            SUM(p.amount) production, SUM(p.commission_amount) payout')
            ->from('payments p')
            ->join('appointments a', 'a.id = p.appointment_id')
            ->join('barbers b', 'b.id = a.barber_id')
            ->where('p.voided_at IS NULL', NULL, FALSE)
            ->where('p.paid_at >=', $from . ' 00:00:00')
            ->where('p.paid_at <=', $to . ' 23:59:59')
            ->group_by(array('b.id', 'b.full_name'))
            ->order_by('b.full_name')
            ->get()
            ->result_array();
    }

    /** Lista cobros por período/texto/anulación; límite 0 devuelve todas las filas. */
    public function obtener_cobros($from, $to, $q = '', $archived = FALSE, $limit = 0, $offset = 0)
    {
        $this->consulta_cobros($from, $to, $q, $archived);

        if ($limit > 0) {
            $this->db->limit($limit, $offset);
        }

        return $this->db->order_by('p.paid_at', 'DESC')->get()->result_array();
    }

    /** Cuenta los cobros del mismo filtro que obtener_cobros para paginar. */
    public function contar_cobros($from, $to, $q = '', $archived = FALSE)
    {
        $this->consulta_cobros($from, $to, $q, $archived);
        return $this->db->count_all_results();
    }
}
