<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Controlador base de Barber House.
 *
 * Centraliza sesión, permisos, layout, lectura de formularios y paginación.
 */
class MY_Controller extends CI_Controller {

    const REGISTROS_POR_PAGINA = 25;

    /** Usuario con sesión o NULL. */
    protected $current_user = NULL;

    public function __construct()
    {
        parent::__construct();

        $this->current_user = $this->session->userdata('auth_user');

        // Se relee la cuenta en cada petición para aplicar una desactivación
        // realizada por otro Encargado sin esperar a que termine la sesión.
        if ($this->current_user) {
            $this->load->model('Usuario_model');
            $user = $this->Usuario_model->buscar_usuario((int) $this->current_user['id']);

            if (!$user || !$user['active']) {
                $this->session->unset_userdata('auth_user');
                $this->current_user = NULL;
            } else {
                // Nunca se guarda el hash de contraseña dentro de la sesión.
                $this->current_user = array(
                    'id'        => (int) $user['id'],
                    'username'  => $user['username'],
                    'full_name' => $user['full_name'],
                    'role_code' => $user['role_code'],
                    'role_name' => $user['role_name'],
                );
                $this->session->set_userdata('auth_user', $this->current_user);
            }
        }

        $this->load->vars('current_user', $this->current_user);
    }

    /** Exige una sesión válida. */
    protected function require_login()
    {
        if (!$this->current_user) {
            $this->session->set_flashdata('error', 'Iniciá sesión para continuar.');
            redirect('login');
            exit;
        }
    }

    /** Exige el perfil Encargado. */
    protected function require_manager()
    {
        $this->require_login();

        if (($this->current_user['role_code'] ?? '') !== 'encargado') {
            show_error('No tenés permisos para acceder a esta sección.', 403, 'Acceso restringido');
            exit;
        }
    }

    /** Recibe ruta de vista, datos y título; compone cabecera, menú, contenido y pie. */
    protected function render($view, $data = array(), $title = 'Barber House')
    {
        $data['page_title'] = $title;
        $this->load->view('plantilla/cabecera', $data);
        $this->load->view('plantilla/menu_lateral', $data);
        $this->load->view($view, $data);
        $this->load->view('plantilla/pie', $data);
    }

    /** Calcula límite y offset para listados largos. */
    protected function paginacion($total)
    {
        $total = (int) $total;
        $limit = self::REGISTROS_POR_PAGINA;
        $pages = max(1, (int) ceil($total / $limit));

        // Valores 0, negativos o superiores al máximo se corrigen.
        $page = min(max(1, (int) $this->input->get('page')), $pages);

        return array(
            'page'   => $page,
            'pages'  => $pages,
            'total'  => $total,
            'limit'  => $limit,
            'offset' => ($page - 1) * $limit,
            'first'  => $total ? (($page - 1) * $limit + 1) : 0,
            'last'   => min($total, $page * $limit),
        );
    }

    /** Lee un campo POST aplicando el filtro de entrada de CodeIgniter. */
    protected function posted($key, $default = NULL)
    {
        $value = $this->input->post($key, TRUE);
        return $value === NULL ? $default : $value;
    }

    /** Exige POST para acciones que modifican datos. */
    protected function require_post()
    {
        if ($this->input->method(TRUE) !== 'POST') {
            show_error('Operación disponible solo mediante formulario.', 405);
        }
    }
}
