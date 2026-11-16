<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * MODELO: Usuario_model
 * Trabaja con users y roles para Autenticacion, Usuarios y MY_Controller.
 * Consulta cuentas/perfiles, controla nombres únicos, registra accesos y permite
 * altas, cambios y bajas lógicas. La contraseña llega cifrada desde Usuarios;
 * Autenticacion compara el hash mediante password_verify.
 */
class Usuario_model extends CI_Model {

    /** Busca el nombre de acceso solo en cuentas activas no archivadas; array o NULL. */
    public function buscar_activo_por_usuario($username)
    {
        return $this->db->select('u.*, r.code role_code, r.name role_name')
            ->from('users u')
            ->join('roles r', 'r.id = u.role_id')
            ->where('u.username', $username)
            ->where('u.active', 1)
            ->where('u.deleted_at IS NULL', NULL, FALSE)
            ->get()
            ->row_array();
    }

    /** Lista cuentas con su perfil y filtros de texto/archivo. */
    public function obtener_usuarios($q = '', $archived = FALSE)
    {
        $this->db->select('u.*, r.code role_code, r.name role_name')
            ->from('users u')
            ->join('roles r', 'r.id = u.role_id')
            ->where($archived ? 'u.deleted_at IS NOT NULL' : 'u.deleted_at IS NULL', NULL, FALSE);

        if ($q !== '') {
            $this->db->group_start()
                ->like('u.username', $q)
                ->or_like('u.full_name', $q)
                ->group_end();
        }

        return $this->db->order_by('u.full_name')->get()->result_array();
    }

    /** Devuelve los perfiles reales de roles para los formularios de Usuarios. */
    public function obtener_roles()
    {
        return $this->db->order_by('id')->get('roles')->result_array();
    }

    /** Busca por id una cuenta no archivada, incluso inactiva, con su perfil. */
    public function buscar_usuario($id)
    {
        return $this->db->select('u.*, r.code role_code, r.name role_name')
            ->from('users u')
            ->join('roles r', 'r.id = u.role_id')
            ->where('u.id', $id)
            ->where('u.deleted_at IS NULL', NULL, FALSE)
            ->get()
            ->row_array();
    }

    /** Incluye cuentas archivadas para respetar UNIQUE; puede excluir el id editado. */
    public function existe_usuario($username, $exclude_id = NULL)
    {
        $this->db->where('username', $username);
        if ($exclude_id) {
            $this->db->where('id !=', $exclude_id);
        }
        return $this->db->count_all_results('users') > 0;
    }

    /** Devuelve TRUE si el id pertenece a una cuenta archivada. */
    public function esta_archivado($id)
    {
        return $this->db->where('id', (int) $id)
            ->where('deleted_at IS NOT NULL', NULL, FALSE)
            ->count_all_results('users') > 0;
    }

    /** Registra la fecha del acceso correcto; devuelve el resultado de la actualización. */
    public function registrar_acceso($id)
    {
        return $this->db->where('id', (int) $id)
            ->update('users', array('last_login_at' => date('Y-m-d H:i:s')));
    }

    /** Deshabilita el acceso y conserva el usuario para la autoría histórica. */
    public function archivar_usuario($id)
    {
        return $this->db->where('id', $id)
            ->where('deleted_at IS NULL', NULL, FALSE)
            ->update('users', array(
                'active' => 0,
                'deleted_at' => date('Y-m-d H:i:s'),
            ));
    }

    /** Quita deleted_at y vuelve a habilitar una cuenta archivada por id. */
    public function restaurar_usuario($id)
    {
        return $this->db->where('id', $id)
            ->where('deleted_at IS NOT NULL', NULL, FALSE)
            ->update('users', array(
                'active' => 1,
                'deleted_at' => NULL,
            ));
    }

    /** Inserta datos y hash ya validados; devuelve id o FALSE ante error. */
    public function registrar_usuario($data)
    {
        if (!$this->db->insert('users', $data)) {
            return FALSE;
        }
        return $this->db->insert_id();
    }

    /** Actualiza campos por id; Usuarios protege previamente la propia cuenta. */
    public function actualizar_usuario($id, $data)
    {
        return $this->db->where('id', $id)->update('users', $data);
    }
}
