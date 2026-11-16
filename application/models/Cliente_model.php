<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * MODELO: Cliente_model
 * Administra datos personales, búsqueda, bajas lógicas e historial de clientes.
 * Tablas: clients, appointments, barbers, appointment_services y services.
 * Lo utilizan Clientes y Turnos; las bajas conservan las atenciones anteriores.
 * Las consultas devuelven arrays; las escrituras devuelven id o FALSE/booleano.
 */
class Cliente_model extends CI_Model {

    /** Filtros compartidos por listado y conteo. */
    private function filtros($q, $archived)
    {
        $this->db->where($archived ? 'deleted_at IS NOT NULL' : 'deleted_at IS NULL', NULL, FALSE);

        if ($q !== '') {
            $this->db->group_start()
                ->like('first_name', $q)
                ->or_like('last_name', $q)
                ->or_like('phone', $q)
                ->or_like('tipo', $q)
                
                ->group_end();
        }
    }

    /** Busca texto en nombre/apellido/teléfono; límite 0 devuelve todos los resultados. */
    public function buscar_clientes($q = '', $archived = FALSE, $limit = 0, $offset = 0)
    {
        $this->db->from('clients');
        $this->filtros($q, $archived);

        if ($limit > 0) {
            $this->db->limit($limit, $offset);
        }

        return $this->db->order_by('last_name')
            ->order_by('first_name')
            ->get()
            ->result_array();
    }

    /** Cuenta los clientes del mismo filtro para calcular la paginación. */
    public function contar_clientes($q = '', $archived = FALSE)
    {
        $this->db->from('clients');
        $this->filtros($q, $archived);
        return $this->db->count_all_results();
    }

    /** Recibe un id y devuelve el cliente no archivado, o NULL si no existe. */
    public function buscar_cliente($id)
    {
        return $this->db->where('id', $id)
            ->where('deleted_at IS NULL', NULL, FALSE)
            ->get('clients')
            ->row_array();
    }

    /** Busca por id exclusivamente entre los clientes archivados. */
    public function buscar_archivado($id)
    {
        return $this->db->where('id', $id)
            ->where('deleted_at IS NOT NULL', NULL, FALSE)
            ->get('clients')
            ->row_array();
    }

    /** Devuelve una coincidencia exacta o NULL; excluye el id editado si se indica. */
    public function buscar_duplicado($first, $last, $phone, $exclude_id = NULL)
    {
        $this->db->where('first_name', $first)
            ->where('last_name', $last)
            ->where('phone', $phone)
            ->where('tipo', $tipo)
           
            ->where('deleted_at IS NULL', NULL, FALSE);

        if ($exclude_id) {
            $this->db->where('id !=', $exclude_id);
        }

        return $this->db->get('clients')->row_array();
    }

    /** Inserta los campos validados por Clientes; devuelve el nuevo id o FALSE. */
    public function registrar_cliente($data)
    {
        if (!$this->db->insert('clients', $data)) {
            return FALSE;
        }
        return $this->db->insert_id();
    }

    /** Actualiza por id los campos recibidos y devuelve si MySQL aceptó la escritura. */
    public function actualizar_cliente($id, $data)
    {
        return $this->db->where('id', $id)->update('clients', $data);
    }

    /** Marca deleted_at por id; el controlador comprueba antes las reservas futuras. */
    public function archivar_cliente($id)
    {
        return $this->db->where('id', $id)
            ->where('deleted_at IS NULL', NULL, FALSE)
            ->update('clients', array('deleted_at' => date('Y-m-d H:i:s')));
    }

    /** Quita la baja lógica; Clientes comprueba antes posibles datos duplicados. */
    public function restaurar_cliente($id)
    {
        return $this->db->where('id', $id)
            ->where('deleted_at IS NOT NULL', NULL, FALSE)
            ->update('clients', array('deleted_at' => NULL));
    }

    /** No se archiva un cliente con reservas futuras. */
    public function tiene_reservas_futuras($id)
    {
        return $this->db->where('client_id', $id)
            ->where('start_at >=', date('Y-m-d H:i:s'))
            ->where('status', 'reserved')
            ->count_all_results('appointments') > 0;
    }

    /** Devuelve turnos por cliente, más recientes primero; la subconsulta reúne sus servicios. */
    public function obtener_historial($client_id)
    {
        return $this->db->select("a.*, b.full_name barber_name,
            (SELECT GROUP_CONCAT(s.name ORDER BY s.name SEPARATOR ', ')
               FROM appointment_services x
               JOIN services s ON s.id = x.service_id
              WHERE x.appointment_id = a.id) services", FALSE)
            ->from('appointments a')
            ->join('barbers b', 'b.id = a.barber_id')
            ->where('a.client_id', $client_id)
            ->order_by('a.start_at', 'DESC')
            ->get()
            ->result_array();
    }
}
