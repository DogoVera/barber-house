<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * MODELO: Servicio_model
 * Administra el catálogo en services y consulta appointment_services/appointments
 * para impedir bajas con reservas futuras. Relacionado con Servicios, Peluqueros
 * y Turnos. Permite listar, buscar, registrar, actualizar, archivar y restaurar.
 * No recalcula los snapshots ya guardados en los turnos.
 */
class Servicio_model extends CI_Model {

    /** Devuelve el catálogo filtrado por actividad, archivo y texto del nombre. */
    public function obtener_servicios($active_only = FALSE, $archived = FALSE, $q = '')
    {
        $this->db->from('services');
        $this->db->where($archived ? 'deleted_at IS NOT NULL' : 'deleted_at IS NULL', NULL, FALSE);

        if ($active_only) {
            $this->db->where('active', 1);
        }

        if ($q !== '') {
            $this->db->like('name', $q);
        }

        return $this->db->order_by('name')->get()->result_array();
    }

    /** Busca por id un servicio no archivado; devuelve array o NULL. */
    public function buscar_servicio($id)
    {
        return $this->db->where('id', $id)
            ->where('deleted_at IS NULL', NULL, FALSE)
            ->get('services')
            ->row_array();
    }

    /** Servicios activos usados al crear/validar un turno. */
    public function obtener_activos($ids)
    {
        if (!$ids) {
            return array();
        }

        return $this->db->where_in('id', array_map('intval', $ids))
            ->where('active', 1)
            ->where('deleted_at IS NULL', NULL, FALSE)
            ->get('services')
            ->result_array();
    }

    /** Indica si el servicio participa en reservas futuras que impedirían su baja. */
    public function tiene_turnos_futuros($id)
    {
        return $this->db->from('appointment_services x')
            ->join('appointments a', 'a.id = x.appointment_id')
            ->where('x.service_id', (int) $id)
            ->where('a.status', 'reserved')
            ->where('a.start_at >=', date('Y-m-d H:i:s'))
            ->count_all_results() > 0;
    }

    /** Comprueba por id si el servicio está dado de baja lógica. */
    public function esta_archivado($id)
    {
        return $this->db->where('id', (int) $id)
            ->where('deleted_at IS NOT NULL', NULL, FALSE)
            ->count_all_results('services') > 0;
    }

    /** Inserta campos validados por Servicios; devuelve id o FALSE si falla. */
    public function registrar_servicio($data)
    {
        if (!$this->db->insert('services', $data)) {
            return FALSE;
        }
        return $this->db->insert_id();
    }

    /** Actualiza el catálogo por id; no modifica precio ni duración históricos. */
    public function actualizar_servicio($id, $data)
    {
        return $this->db->where('id', $id)->update('services', $data);
    }

    /** Desactiva y archiva por id sin eliminar referencias históricas. */
    public function archivar_servicio($id)
    {
        return $this->db->where('id', $id)
            ->where('deleted_at IS NULL', NULL, FALSE)
            ->update('services', array(
                'active' => 0,
                'deleted_at' => date('Y-m-d H:i:s'),
            ));
    }

    /** Reactiva un servicio archivado; devuelve el resultado booleano de MySQL. */
    public function restaurar_servicio($id)
    {
        return $this->db->where('id', $id)
            ->where('deleted_at IS NOT NULL', NULL, FALSE)
            ->update('services', array(
                'active' => 1,
                'deleted_at' => NULL,
            ));
    }
}
