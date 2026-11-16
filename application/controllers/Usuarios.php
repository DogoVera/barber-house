<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * CONTROLADOR: Usuarios
 * Administración de cuentas y perfiles mediante Usuario_model, solo Encargado.
 * Permite listado, alta, edición, archivo y restauración. Valida unicidad,
 * perfil y contraseña; protege la propia cuenta contra pérdida de acceso.
 * Nunca almacena contraseñas en texto plano ni elimina la autoría histórica.
 */
class Usuarios extends MY_Controller {

    public function __construct()
    {
        parent::__construct();
        $this->require_manager();
        $this->load->model('Usuario_model');
    }

    /** Lista cuentas filtradas por nombre/usuario y estado de archivo. */
    public function index()
    {
        $q = trim((string) $this->input->get('q', TRUE));
        $archived = $this->input->get('archived') === '1';

        $this->render('usuarios/listado_usuarios', array(
            'users' => $this->Usuario_model->obtener_usuarios($q, $archived),
            'q' => $q,
            'archived' => $archived,
        ), 'Usuarios');
    }

    /** Prepara el alta con los roles existentes en MySQL. */
    public function nuevo()
    {
        $this->render('usuarios/formulario_usuario', array(
            'user' => NULL,
            'roles' => $this->Usuario_model->obtener_roles(),
            'errors' => array(),
        ), 'Nuevo usuario');
    }

    /** Carga cuenta por id o 404; muestra perfiles para su edición. */
    public function editar($id)
    {
        $user = $this->Usuario_model->buscar_usuario($id);
        if (!$user) {
            show_404();
        }

        $this->render('usuarios/formulario_usuario', array(
            'user' => $user,
            'roles' => $this->Usuario_model->obtener_roles(),
            'errors' => array(),
        ), 'Editar usuario');
    }

    /** Valida el POST; id opcional excluye la propia cuenta al buscar duplicados. Devuelve errores. */
    private function validar_datos($id = NULL)
    {
        $errors = array();
        $username = trim($this->posted('username', ''));

        if ($username === '') {
            $errors[] = 'El usuario es obligatorio.';
        } elseif ($this->Usuario_model->existe_usuario($username, $id)) {
            $errors[] = 'Ese usuario ya existe.';
        }

        if (trim($this->posted('full_name', '')) === '') {
            $errors[] = 'El nombre es obligatorio.';
        }

        $role_id = (int) $this->posted('role_id');
        $valid_roles = array_map('intval', array_column($this->Usuario_model->obtener_roles(), 'id'));
        if (!in_array($role_id, $valid_roles, TRUE)) {
            $errors[] = 'Seleccioná un perfil válido.';
        }

        $password = (string) $this->input->post('password');
        if ((!$id || $password !== '') && strlen($password) < 6) {
            $errors[] = 'La contraseña debe tener al menos 6 caracteres.';
        }

        return $errors;
    }

    /** Construye los campos de la tabla users. */
    private function datos_usuario($include_password = FALSE)
    {
        $data = array(
            'username' => trim($this->posted('username', '')),
            'full_name' => trim($this->posted('full_name', '')),
            'role_id' => (int) $this->posted('role_id'),
            'active' => $this->posted('active', 0) ? 1 : 0,
            'updated_at' => date('Y-m-d H:i:s'),
        );

        $password = (string) $this->input->post('password');
        if ($include_password || $password !== '') {
            // Nunca se guarda la contraseña en texto plano.
            $data['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
        }

        return $data;
    }

    /** Registra por POST con hash; ante errores muestra de nuevo el formulario y sus mensajes. */
    public function guardar()
    {
        $this->require_post();
        $errors = $this->validar_datos();

        if ($errors) {
            $this->render('usuarios/formulario_usuario', array(
                'user' => $this->input->post(NULL, TRUE),
                'roles' => $this->Usuario_model->obtener_roles(),
                'errors' => $errors,
            ), 'Nuevo usuario');
            return;
        }

        $data = $this->datos_usuario(TRUE);
        $data['created_at'] = date('Y-m-d H:i:s');

        $saved = $this->Usuario_model->registrar_usuario($data);
        $this->session->set_flashdata(
            $saved ? 'success' : 'error',
            $saved ? 'Usuario creado.' : 'No se pudo crear el usuario.'
        );
        redirect('usuarios');
    }

    /** Edita por POST; no permite desactivar/cambiar perfil propio y conserva clave si viene vacía. */
    public function actualizar($id)
    {
        $this->require_post();
        $user = $this->Usuario_model->buscar_usuario($id);
        if (!$user) {
            show_404();
        }

        $errors = $this->validar_datos($id);
        if ($errors) {
            $this->render('usuarios/formulario_usuario', array(
                'user' => array_merge($user, $this->input->post(NULL, TRUE)),
                'roles' => $this->Usuario_model->obtener_roles(),
                'errors' => $errors,
            ), 'Editar usuario');
            return;
        }

        $data = $this->datos_usuario(FALSE);
        $is_self = (int) $id === (int) $this->current_user['id'];

        // El usuario conectado no puede quitarse su propio acceso o perfil.
        if ($is_self && (!$data['active'] || $data['role_id'] != $user['role_id'])) {
            $this->session->set_flashdata(
                'error',
                'No podés desactivar ni cambiar el perfil de tu propia cuenta.'
            );
            redirect('usuarios/editar/' . $id);
            return;
        }

        $saved = $this->Usuario_model->actualizar_usuario($id, $data);
        $this->session->set_flashdata(
            $saved ? 'success' : 'error',
            $saved ? 'Usuario actualizado.' : 'No se pudo actualizar el usuario.'
        );
        redirect($saved ? 'usuarios' : 'usuarios/editar/' . $id);
    }

    /** Archiva y desactiva por POST otra cuenta; rechaza eliminar la propia. */
    public function eliminar($id)
    {
        $this->require_post();
        $user = $this->Usuario_model->buscar_usuario($id);
        if (!$user) {
            show_404();
        }

        if ((int) $id === (int) $this->current_user['id']) {
            $this->session->set_flashdata('error', 'No podés eliminar tu propia cuenta.');
            redirect('usuarios');
            return;
        }

        $saved = $this->Usuario_model->archivar_usuario($id);
        $this->session->set_flashdata(
            $saved ? 'success' : 'error',
            $saved ? 'Usuario eliminado de la lista y acceso deshabilitado.' : 'No se pudo eliminar el usuario.'
        );
        redirect('usuarios');
    }

    /** Restaura por POST el id archivado, o responde 404 si no lo está. */
    public function restaurar($id)
    {
        $this->require_post();

        if (!$this->Usuario_model->esta_archivado($id)) {
            show_404();
        }

        $saved = $this->Usuario_model->restaurar_usuario($id);
        $this->session->set_flashdata(
            $saved ? 'success' : 'error',
            $saved ? 'Usuario restaurado.' : 'No se pudo restaurar el usuario.'
        );
        redirect('usuarios?archived=1');
    }
}
