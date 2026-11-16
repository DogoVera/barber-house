<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * CONTROLADOR: Autenticacion
 * Administra inicio/cierre de sesión mediante Usuario_model.
 * Es el único módulo accesible sin sesión previa. Comprueba cuenta activa y hash,
 * renueva la sesión al ingresar y no revela cuál de las credenciales falló.
 */
class Autenticacion extends MY_Controller {

    public function __construct()
    {
        parent::__construct();
        $this->load->model('Usuario_model');
    }

    /** Envía al panel si hay sesión, o al formulario de acceso en caso contrario. */
    public function index()
    {
        redirect($this->current_user ? 'inicio' : 'login');
    }

    /** Muestra el acceso; por POST verifica usuario/clave y guarda solo datos públicos en sesión. */
    public function iniciar_sesion()
    {
        if ($this->current_user) {
            redirect('inicio');
            return;
        }

        $data = array('error' => '');

        if ($this->input->method(TRUE) === 'POST') {
            $username = trim($this->posted('username', ''));
            $password = (string) $this->input->post('password');
            $user = $this->Usuario_model->buscar_activo_por_usuario($username);

            // password_verify compara la clave escrita con el hash guardado.
            if ($user && password_verify($password, $user['password_hash'])) {
                $this->session->sess_regenerate(TRUE);
                $this->session->set_userdata('auth_user', array(
                    'id' => (int) $user['id'],
                    'username' => $user['username'],
                    'full_name' => $user['full_name'],
                    'role_code' => $user['role_code'],
                    'role_name' => $user['role_name'],
                ));

                $this->Usuario_model->registrar_acceso($user['id']);
                redirect('inicio');
                return;
            }

            // No se informa si falló usuario o contraseña por separado.
            $data['error'] = 'Usuario o contraseña incorrectos.';
        }

        $this->load->view('autenticacion/inicio_sesion', $data);
    }

    /** Exige POST, destruye la sesión y redirige al acceso. */
    public function cerrar_sesion()
    {
        $this->require_post();
        $this->session->sess_destroy();
        redirect('login');
    }
}
