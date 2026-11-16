<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * MODELO: Turno_model (agenda).
 * Tablas: appointments, appointment_services, clients, barbers, services,
 * payments y payment_methods; consulta jornadas mediante Peluquero_model.
 * Utilizado por Turnos, Peluqueros, Cobros y Panel_principal.
 *
 * Concentra las reglas principales de los turnos:
 * - servicios y duración total;
 * - horarios del peluquero;
 * - superposición de turnos;
 * - disponibilidad;
 * - snapshots históricos;
 * - protección de turnos cobrados.
 */
class Turno_model extends CI_Model {

    /** Motivo del último guardado/reasignación rechazado, mostrado por el controlador. */
    public $last_error = 'No se pudo guardar el turno.';

    /** Aplica los mismos filtros al listado y al conteo. */
    private function filtros_agenda($date, $barber_id, $q, $status)
    {
        if ($date !== '' && $date !== NULL) {
            $this->db->where('DATE(a.start_at)', $date);
        }

        if ($barber_id) {
            $this->db->where('a.barber_id', $barber_id);
        }

        if ($status !== '') {
            $this->db->where('a.status', $status);
        }

        if ($q !== '') {
            $this->db->group_start()
                ->like('c.first_name', $q)
                ->or_like('c.last_name', $q)
                ->or_like('c.phone', $q)
                ->or_like('b.full_name', $q)
                ->group_end();
        }
    }

    /**
     * Lista turnos de una fecha o, si $date está vacío, todo el historial.
     * Recibe filtros de peluquero, texto y estado; límite 0 lista sin paginar.
     * Las subconsultas resumen varios servicios sin duplicar filas de turnos.
     */
    public function obtener_turnos($date, $barber_id = NULL, $q = '', $status = '', $limit = 0, $offset = 0)
    {
        $this->db->select("
            a.*,
            CONCAT(c.first_name, ' ', c.last_name) client_name,
            c.phone client_phone,
            b.full_name barber_name,
            (SELECT COALESCE(SUM(x.price_snapshot), 0)
               FROM appointment_services x
              WHERE x.appointment_id = a.id) estimated_total,
            (SELECT COALESCE(SUM(x.duration_snapshot), 0)
               FROM appointment_services x
              WHERE x.appointment_id = a.id) service_minutes,
            (SELECT COUNT(*)
               FROM payments p
              WHERE p.appointment_id = a.id) has_payment,
            (SELECT GROUP_CONCAT(s.name ORDER BY s.name SEPARATOR ', ')
               FROM appointment_services x
               JOIN services s ON s.id = x.service_id
              WHERE x.appointment_id = a.id) services", FALSE);

        $this->db->from('appointments a');
        $this->db->join('clients c', 'c.id = a.client_id');
        $this->db->join('barbers b', 'b.id = a.barber_id');

        $this->filtros_agenda($date, $barber_id, $q, $status);

        if ($limit > 0) {
            $this->db->limit($limit, $offset);
        }

        return $this->db->order_by('a.start_at')->get()->result_array();
    }

    /** Cantidad de turnos con los mismos filtros del listado. */
    public function contar_turnos($date, $barber_id = NULL, $q = '', $status = '')
    {
        $this->db->from('appointments a');
        $this->db->join('clients c', 'c.id = a.client_id');
        $this->db->join('barbers b', 'b.id = a.barber_id');

        $this->filtros_agenda($date, $barber_id, $q, $status);
        return $this->db->count_all_results();
    }

    /** Devuelve el turno completo por id (incluso cancelado), o NULL si no existe. */
    public function buscar_turno($id)
    {
        $row = $this->db->select("
                a.*,
                CONCAT(c.first_name, ' ', c.last_name) client_name,
                c.phone client_phone,
                b.full_name barber_name", FALSE)
            ->from('appointments a')
            ->join('clients c', 'c.id = a.client_id')
            ->join('barbers b', 'b.id = a.barber_id')
            ->where('a.id', $id)
            ->get()
            ->row_array();

        if (!$row) {
            return NULL;
        }

        $row['services'] = $this->obtener_servicios($id);
        $row['payment'] = $this->db->select('p.*, pm.name payment_method')
            ->from('payments p')
            ->join('payment_methods pm', 'pm.id = p.payment_method_id')
            ->where('p.appointment_id', $id)
            ->get()
            ->row_array();

        return $row;
    }

    /** Servicios guardados en el turno con sus snapshots. */
    public function obtener_servicios($appointment_id)
    {
        return $this->db->select('x.*, s.name')
            ->from('appointment_services x')
            ->join('services s', 's.id = x.service_id')
            ->where('x.appointment_id', $appointment_id)
            ->order_by('s.name')
            ->get()
            ->result_array();
    }

    /** Un cobro anulado también cuenta como historial de cobro del turno. */
    public function tiene_cobro($appointment_id)
    {
        return $this->db->where('appointment_id', $appointment_id)
            ->count_all_results('payments') > 0;
    }

    /**
     * REQ-33: evita turnos superpuestos.
     * Dos intervalos se cruzan si startA < endB y endA > startB.
     */
    public function hay_superposicion($barber_id, $start, $end, $exclude_id = NULL)
    {
        $this->db->from('appointments')
            ->where('barber_id', $barber_id)
            ->where_in('status', array('reserved', 'attended'))
            ->where('start_at <', $end)
            ->where('end_at >', $start);

        if ($exclude_id) {
            $this->db->where('id !=', $exclude_id);
        }

        return $this->db->count_all_results() > 0;
    }

    /**
     * Valida fecha, servicios, peluquero, horario y superposición.
     * Devuelve array($ok, $mensaje, $inicio, $fin, $servicios).
     * Recibe id de peluquero, ids de servicios, fecha YYYY-MM-DD y hora HH:MM.
     * $exclude_id permite editar sin cruzarse consigo mismo y mantiene snapshots.
     * Ante un dato inválido devuelve FALSE y una explicación sin escribir en MySQL.
     */
    public function validar_horario($barber_id, $service_ids, $date, $time, $exclude_id = NULL)
    {
        $this->load->model('Servicio_model');
        $this->load->model('Peluquero_model');

        // 1) Servicios válidos y sin repetir.
        if (!is_array($service_ids) || !$service_ids) {
            return array(FALSE, 'Seleccioná servicios válidos.', NULL, NULL, array());
        }

        $ids = array();
        foreach ($service_ids as $id) {
            if (!is_scalar($id)
                || !ctype_digit((string) $id)
                || (int) $id < 1
                || in_array((int) $id, $ids, TRUE)) {
                return array(FALSE, 'Seleccioná servicios válidos sin repetir.', NULL, NULL, array());
            }
            $ids[] = (int) $id;
        }

        $services = $this->Servicio_model->obtener_activos($ids);
        if (count($services) !== count($ids)) {
            return array(FALSE, 'Seleccioná servicios activos y válidos.', NULL, NULL, $services);
        }

        // 2) En edición se conservan precio y duración originales.
        $previous = array();
        if ($exclude_id) {
            foreach ($this->obtener_servicios($exclude_id) as $old) {
                $previous[$old['service_id']] = $old;
            }
        }

        $minutes = 0;
        foreach ($services as &$service) {
            if (isset($previous[$service['id']])) {
                $service['price'] = $previous[$service['id']]['price_snapshot'];
                $service['duration_minutes'] = $previous[$service['id']]['duration_snapshot'];
            }
            $minutes += (int) $service['duration_minutes'];
        }
        unset($service);

        if ($minutes <= 0) {
            return array(FALSE, 'La duración del turno no es válida.', NULL, NULL, $services);
        }

        // 3) Fecha/hora válida y turno dentro del mismo día.
        if (!valid_day($date) || !valid_clock($time)) {
            return array(FALSE, 'La fecha u hora no es válida.', NULL, NULL, $services);
        }

        $start = $date . ' ' . $time . ':00';
        $end = date('Y-m-d H:i:s', strtotime($start . ' +' . $minutes . ' minutes'));

        if (substr($start, 0, 10) !== substr($end, 0, 10)) {
            return array(FALSE, 'El turno debe terminar el mismo día.', NULL, NULL, $services);
        }

        // 4) Peluquero activo, servicios compatibles y horario laboral.
        $barber = $this->Peluquero_model->buscar_peluquero($barber_id);
        if (!$barber || !$barber['active']) {
            return array(FALSE, 'El peluquero no está activo.', NULL, NULL, $services);
        }

        if (!$this->Peluquero_model->realiza_servicios($barber_id, $ids)) {
            return array(FALSE, 'El peluquero seleccionado no realiza todos los servicios elegidos.', NULL, NULL, $services);
        }

        if (!$this->Peluquero_model->respeta_horario($barber_id, $start, $end)) {
            return array(FALSE, 'El turno queda fuera de los días u horarios de trabajo del peluquero.', NULL, NULL, $services);
        }

        if ($this->hay_superposicion($barber_id, $start, $end, $exclude_id)) {
            return array(FALSE, 'El peluquero ya tiene un turno que se superpone con ese horario.', NULL, NULL, $services);
        }

        return array(TRUE, '', $start, $end, $services);
    }

    /**
     * REQ-05 y REQ-19: horarios disponibles en intervalos de 15 minutos.
     * Se usa la misma validación que al guardar para no tener reglas distintas.
     * Recibe peluquero, fecha, ids de servicios e id opcional del turno editado.
     * Devuelve jornada, ocupados, horas libres, duración y error (vacío si fue válido).
     */
    public function horarios_disponibles($barber_id, $date, $service_ids, $exclude_id = NULL)
    {
        $result = array(
            'schedule' => NULL,
            'occupied' => array(),
            'slots'    => array(),
            'minutes'  => 0,
            'error'    => '',
        );

        if (!valid_day($date)) {
            $result['error'] = 'Seleccioná una fecha válida.';
            return $result;
        }

        $this->load->model(array('Peluquero_model', 'Servicio_model'));
        $barber = $this->Peluquero_model->buscar_peluquero($barber_id);

        if (!$barber || !$barber['active']) {
            $result['error'] = 'El peluquero no está activo.';
            return $result;
        }

        $day = (int) date('N', strtotime($date));
        foreach ($this->Peluquero_model->consultar_horarios($barber_id) as $schedule) {
            if ((int) $schedule['day_of_week'] === $day && (int) $schedule['active'] === 1) {
                $result['schedule'] = $schedule;
                break;
            }
        }

        // Los cancelados/ausentes no ocupan espacio.
        $result['occupied'] = $this->db->select('start_at, end_at')
            ->from('appointments')
            ->where('barber_id', $barber_id)
            ->where_in('status', array('reserved', 'attended'))
            ->where('start_at >=', $date . ' 00:00:00')
            ->where('start_at <=', $date . ' 23:59:59')
            ->order_by('start_at')
            ->get()
            ->result_array();

        if (!$result['schedule']) {
            $result['error'] = 'El peluquero no trabaja ese día.';
            return $result;
        }

        if (!is_array($service_ids) || !$service_ids) {
            $result['error'] = 'Seleccioná al menos un servicio.';
            return $result;
        }

        $ids = array();
        foreach ($service_ids as $id) {
            if (!is_scalar($id)
                || !ctype_digit((string) $id)
                || (int) $id < 1
                || in_array((int) $id, $ids, TRUE)) {
                $result['error'] = 'Seleccioná servicios válidos sin repetir.';
                return $result;
            }
            $ids[] = (int) $id;
        }

        $services = $this->Servicio_model->obtener_activos($ids);
        if (count($services) !== count($ids)
            || !$this->Peluquero_model->realiza_servicios($barber_id, $ids)) {
            $result['error'] = 'El peluquero no realiza todos los servicios seleccionados o están inactivos.';
            return $result;
        }

        // En edición se conserva la duración snapshot del turno.
        $previous = array();
        if ($exclude_id) {
            foreach ($this->obtener_servicios($exclude_id) as $old) {
                $previous[$old['service_id']] = $old;
            }
        }

        foreach ($services as $service) {
            $result['minutes'] += isset($previous[$service['id']])
                ? (int) $previous[$service['id']]['duration_snapshot']
                : (int) $service['duration_minutes'];
        }

        if ($result['minutes'] <= 0) {
            $result['error'] = 'La duración de los servicios no es válida.';
            return $result;
        }

        $opening = strtotime($date . ' ' . $result['schedule']['start_time']);
        $closing = strtotime($date . ' ' . $result['schedule']['end_time']);

        // Cada candidato tiene que entrar completo antes del cierre.
        for ($candidate = $opening;
             $candidate + $result['minutes'] * 60 <= $closing;
             $candidate += 15 * 60) {

            $time = date('H:i', $candidate);
            list($ok) = $this->validar_horario($barber_id, $ids, $date, $time, $exclude_id);

            if ($ok) {
                $result['slots'][] = $time;
            }
        }

        return $result;
    }

    /**
     * Crea/actualiza turno y servicios en una transacción; recibe id o NULL,
     * campos preparados por Turnos y servicios validados con precio/duración.
     * Revalida bajo bloqueo y conserva snapshots. Devuelve id o FALSE;
     * last_error describe el rechazo y el rollback evita un guardado parcial.
     */
    public function guardar_turno($id, $data, $services)
    {
        $this->db->trans_begin();

        // Bloquear el peluquero serializa dos guardados simultáneos de agenda.
        $this->db->query('SELECT id FROM barbers WHERE id = ? FOR UPDATE', array($data['barber_id']));

        if ($id) {
            $this->db->query('SELECT id FROM appointments WHERE id = ? FOR UPDATE', array($id));

            if ($this->tiene_cobro($id)) {
                $this->db->trans_rollback();
                $this->last_error = 'El turno ya fue cobrado y no puede modificarse.';
                return FALSE;
            }
        }

        // Se vuelve a validar dentro de la transacción: el horario pudo ser
        // ocupado por otra petición entre el formulario y el guardado.
        $ids = array();
        foreach ($services as $service) {
            $ids[] = $service['id'];
        }

        list($valid, $reason) = $this->validar_horario(
            $data['barber_id'],
            $ids,
            substr($data['start_at'], 0, 10),
            substr($data['start_at'], 11, 5),
            $id
        );

        if (!$valid) {
            $this->db->trans_rollback();
            $this->last_error = $reason;
            return FALSE;
        }

        if ($id) {
            $this->db->where('id', $id)->update('appointments', $data);
        } else {
            $this->db->insert('appointments', $data);
            $id = $this->db->insert_id();
        }

        $previous = array();
        foreach ($this->obtener_servicios($id) as $old) {
            $previous[$old['service_id']] = $old;
        }

        $this->db->where('appointment_id', $id)->delete('appointment_services');

        // Snapshot: precio y duración quedan congelados para el historial.
        foreach ($services as $service) {
            $old = isset($previous[$service['id']]) ? $previous[$service['id']] : NULL;

            $this->db->insert('appointment_services', array(
                'appointment_id'    => $id,
                'service_id'        => $service['id'],
                'price_snapshot'    => $old ? $old['price_snapshot'] : $service['price'],
                'duration_snapshot' => $old ? $old['duration_snapshot'] : $service['duration_minutes'],
            ));
        }

        if ($this->db->trans_status() === FALSE) {
            $this->db->trans_rollback();
            return FALSE;
        }

        $this->db->trans_commit();
        return $id;
    }

    /**
     * Cambia el estado con bloqueo para que cobro y cancelación concurrentes
     * nunca dejen un turno cobrado como cancelado/ausente.
     * Recibe id y estado validado por Turnos; devuelve array(booleano, mensaje).
     * Si se reactiva un turno, vuelve a comprobar su disponibilidad antes del commit.
     */
    public function cambiar_estado($id, $status)
    {
        $appointment = $this->db->where('id', $id)->get('appointments')->row_array();
        if (!$appointment) {
            return array(FALSE, 'El turno no existe.');
        }

        $this->db->trans_begin();

        // Mismo orden de bloqueo que al guardar: peluquero -> turno.
        $this->db->query('SELECT id FROM barbers WHERE id = ? FOR UPDATE', array($appointment['barber_id']));
        $appointment = $this->db->query(
            'SELECT * FROM appointments WHERE id = ? FOR UPDATE',
            array($id)
        )->row_array();

        if ($this->tiene_cobro($id) && $status !== 'attended') {
            $this->db->trans_rollback();
            return array(FALSE, 'El turno ya tiene un cobro registrado, por lo que no puede pasar a ese estado.');
        }

        // Si un turno inactivo vuelve a reservarse, su horario debe seguir libre.
        $was_inactive = in_array($appointment['status'], array('cancelled', 'no_show'), TRUE);
        if ($was_inactive && in_array($status, array('reserved', 'attended'), TRUE)) {
            $service_ids = array_column($this->obtener_servicios($id), 'service_id');
            list($ok, $reason) = $this->validar_horario(
                $appointment['barber_id'],
                $service_ids,
                substr($appointment['start_at'], 0, 10),
                substr($appointment['start_at'], 11, 5),
                $id
            );

            if (!$ok) {
                $this->db->trans_rollback();
                return array(FALSE, $reason);
            }
        }

        $this->db->where('id', $id)->update('appointments', array(
            'status' => $status,
            'updated_at' => date('Y-m-d H:i:s'),
        ));

        if ($this->db->trans_status() === FALSE) {
            $this->db->trans_rollback();
            return array(FALSE, 'No se pudo actualizar el estado del turno.');
        }

        $this->db->trans_commit();
        return array(TRUE, 'Estado actualizado.');
    }

    /**
     * Recibe id de turno, peluquero destino e intervalo calculado al validar.
     * Conserva los servicios y snapshots; rechaza cobros o conflictos bajo bloqueo.
     * Devuelve booleano y deja el motivo de rechazo en last_error.
     */
    public function reasignar_turno($id, $barber_id, $start, $end)
    {
        $this->db->trans_begin();

        $this->db->query('SELECT id FROM barbers WHERE id = ? FOR UPDATE', array($barber_id));
        $this->db->query('SELECT id FROM appointments WHERE id = ? FOR UPDATE', array($id));

        if ($this->tiene_cobro($id)) {
            $this->db->trans_rollback();
            $this->last_error = 'Un turno cobrado no se puede reasignar.';
            return FALSE;
        }

        $ids = array();
        foreach ($this->obtener_servicios($id) as $service) {
            $ids[] = $service['service_id'];
        }

        list($valid, $reason) = $this->validar_horario(
            $barber_id,
            $ids,
            substr($start, 0, 10),
            substr($start, 11, 5),
            $id
        );

        if (!$valid) {
            $this->db->trans_rollback();
            $this->last_error = $reason;
            return FALSE;
        }

        $this->db->where('id', $id)->update('appointments', array(
            'barber_id' => $barber_id,
            'start_at' => $start,
            'end_at' => $end,
            'updated_at' => date('Y-m-d H:i:s'),
        ));

        if ($this->db->trans_status() === FALSE) {
            $this->db->trans_rollback();
            return FALSE;
        }

        $this->db->trans_commit();
        return TRUE;
    }
}
