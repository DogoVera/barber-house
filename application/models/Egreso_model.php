<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * MODELO: Egreso_model
 * Administra expenses y expense_categories; une users para mostrar la autoría.
 * Utilizado por Contabilidad y Panel_principal. Consulta gastos y totales,
 * registra/corrige egresos y permite anular/restaurar sin borrar su historial.
 * Los registros anulados quedan excluidos del resultado económico.
 */
class Egreso_model extends CI_Model {

    /** Devuelve categorías activas, ordenadas para los formularios. */
    public function obtener_categorias()
    {
        return $this->db->where('active', 1)
            ->order_by('name')
            ->get('expense_categories')
            ->result_array();
    }

    /** Comprueba que el id recibido corresponda a una categoría activa. */
    public function categoria_valida($id)
    {
        return $id > 0 && $this->db->where('id', $id)
            ->where('active', 1)
            ->count_all_results('expense_categories') > 0;
    }

    /** Consulta base reutilizada por listado y conteo. */
    private function consulta_egresos($from, $to, $q, $archived)
    {
        $this->db->select('e.*, ec.name category_name, u.full_name created_by_name')
            ->from('expenses e')
            ->join('expense_categories ec', 'ec.id = e.category_id')
            ->join('users u', 'u.id = e.created_by')
            ->where($archived ? 'e.voided_at IS NOT NULL' : 'e.voided_at IS NULL', NULL, FALSE)
            ->where('e.expense_date >=', $from)
            ->where('e.expense_date <=', $to);

        if ($q !== '') {
            $this->db->group_start()
                ->like('e.concept', $q)
                ->or_like('ec.name', $q)
                ->group_end();
        }
    }

    /** Lista por fechas inclusivas, texto y anulación; límite 0 desactiva paginación. */
    public function obtener_egresos($from, $to, $q = '', $archived = FALSE, $limit = 0, $offset = 0)
    {
        $this->consulta_egresos($from, $to, $q, $archived);

        if ($limit > 0) {
            $this->db->limit($limit, $offset);
        }

        return $this->db->order_by('e.expense_date', 'DESC')
            ->order_by('e.id', 'DESC')
            ->get()
            ->result_array();
    }

    /** Cuenta exactamente el mismo conjunto filtrado que obtener_egresos. */
    public function contar_egresos($from, $to, $q = '', $archived = FALSE)
    {
        $this->consulta_egresos($from, $to, $q, $archived);
        return $this->db->count_all_results();
    }

    /** Busca por id, incluidos anulados; devuelve array o NULL. */
    public function buscar_egreso($id)
    {
        return $this->db->where('id', $id)->get('expenses')->row_array();
    }

    /** Inserta datos validados por Contabilidad; devuelve id o FALSE. */
    public function registrar_egreso($data)
    {
        if (!$this->db->insert('expenses', $data)) {
            return FALSE;
        }
        return $this->db->insert_id();
    }

    /** Corrige campos por id; el controlador verifica permiso y vigencia. */
    public function actualizar_egreso($id, $data)
    {
        return $this->db->where('id', $id)->update('expenses', $data);
    }

    /** Anular conserva el registro pero lo quita de los totales. */
    public function anular_egreso($id, $user_id)
    {
        return $this->db->where('id', $id)
            ->where('voided_at IS NULL', NULL, FALSE)
            ->update('expenses', array(
                'voided_at' => date('Y-m-d H:i:s'),
                'voided_by' => $user_id,
            ));
    }

    /** Restablece por id un egreso anulado y limpia los datos de su anulación. */
    public function restaurar_egreso($id)
    {
        return $this->db->where('id', $id)
            ->where('voided_at IS NOT NULL', NULL, FALSE)
            ->update('expenses', array(
                'voided_at' => NULL,
                'voided_by' => NULL,
            ));
    }

    /** Suma gastos vigentes en fechas inclusivas YYYY-MM-DD; devuelve float, o 0. */
    public function total_egresos($from, $to)
    {
        $row = $this->db->select_sum('amount', 'total')
            ->where('voided_at IS NULL', NULL, FALSE)
            ->where('expense_date >=', $from)
            ->where('expense_date <=', $to)
            ->get('expenses')
            ->row_array();

        return (float) ($row['total'] ?: 0);
    }
}
