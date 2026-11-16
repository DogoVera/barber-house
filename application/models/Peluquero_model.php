<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * MODELO: Peluquero_model
 *
 * Maneja datos personales, días/horarios, servicios y comisión. Al editar la
 * disponibilidad protege los turnos futuros: si alguno quedaría inválido, la
 * modificación completa se rechaza y la transacción vuelve atrás.
 * Tablas: barbers, barber_schedules, barber_services, appointments,
 * appointment_services y clients. Lo utilizan Peluqueros y Turnos, también
 * Turno_model para comprobar la jornada y los servicios permitidos.
 */
class Peluquero_model extends CI_Model {

    /** Explica el rechazo de guardar_peluquero después de revertir la transacción. */
    public $last_error = '';

    /** Lista por actividad, archivo y nombre/teléfono; devuelve un array de filas. */
    public function obtener_peluqueros($active_only = FALSE, $archived = FALSE, $q = '')
    {
        $this->db->from('barbers');
        $this->db->where($archived ? 'deleted_at IS NOT NULL' : 'deleted_at IS NULL', NULL, FALSE);

        if ($active_only) {
            $this->db->where('active', 1);
        }

        if ($q !== '') {
            $this->db->group_start()
                ->like('full_name', $q)
                ->or_like('phone', $q)
                ->group_end();
        }

        return $this->db->order_by('full_name')->get()->result_array();
    }

    /** Peluquero no archivado por id; devuelve array o NULL. */
    public function buscar_peluquero($id)
    {
        return $this->db->where('id', $id)
            ->where('deleted_at IS NULL', NULL, FALSE)
            ->get('barbers')
            ->row_array();
    }

    /** Indica si el id corresponde a un peluquero archivado. */
    public function esta_archivado($id)
    {
        return $this->db->where('id', (int) $id)
            ->where('deleted_at IS NOT NULL', NULL, FALSE)
            ->count_all_results('barbers') > 0;
    }

    /** Baja lógica: conserva el historial. */
    public function archivar_peluquero($id)
    {
        return $this->db->where('id', $id)
            ->where('deleted_at IS NULL', NULL, FALSE)
            ->update('barbers', array(
                'active' => 0,
                'deleted_at' => date('Y-m-d H:i:s'),
            ));
    }

    /** Reactiva y restaura por id; Peluqueros comprueba antes que esté archivado. */
    public function restaurar_peluquero($id)
    {
        return $this->db->where('id', $id)
            ->where('deleted_at IS NOT NULL', NULL, FALSE)
            ->update('barbers', array(
                'active' => 1,
                'deleted_at' => NULL,
            ));
    }

    /** Devuelve las filas de jornada del peluquero, ordenadas de lunes a domingo. */
    public function consultar_horarios($barber_id)
    {
        return $this->db->where('barber_id', $barber_id)
            ->order_by('day_of_week')
            ->get('barber_schedules')
            ->result_array();
    }

    /** Ids de servicios que puede realizar. */
    public function obtener_servicios($barber_id)
    {
        $rows = $this->db->select('service_id')
            ->where('barber_id', $barber_id)
            ->get('barber_services')
            ->result_array();

        return array_map('intval', array_column($rows, 'service_id'));
    }

    /** Verifica que realice TODOS los servicios solicitados. */
    public function realiza_servicios($barber_id, $service_ids)
    {
        $service_ids = array_values(array_unique(array_map('intval', $service_ids)));
        if (!$service_ids) {
            return FALSE;
        }

        $found = $this->db->where('barber_id', $barber_id)
            ->where_in('service_id', $service_ids)
            ->count_all_results('barber_services');

        return $found === count($service_ids);
    }

    /** Comprueba que un intervalo esté dentro del horario laboral guardado. */
    public function respeta_horario($barber_id, $start_at, $end_at)
    {
        if (substr($start_at, 0, 10) !== substr($end_at, 0, 10)) {
            return FALSE;
        }

        $day = (int) date('N', strtotime($start_at));
        $start = date('H:i:s', strtotime($start_at));
        $end = date('H:i:s', strtotime($end_at));

        return $this->db->where('barber_id', $barber_id)
            ->where('day_of_week', $day)
            ->where('active', 1)
            ->where('start_time <=', $start)
            ->where('end_time >=', $end)
            ->count_all_results('barber_schedules') > 0;
    }

    /**
     * Verifica un turno futuro contra la NUEVA configuración todavía no guardada.
     */
    private function reserva_futura_valida($appointment, $schedule_rows, $service_ids, $active)
    {
        if (!$active) {
            return FALSE;
        }

        // Todos los servicios del turno tienen que seguir habilitados.
        $appointment_services = $this->db->select('service_id')
            ->where('appointment_id', $appointment['id'])
            ->get('appointment_services')
            ->result_array();

        $allowed_services = array_map('intval', $service_ids);
        foreach ($appointment_services as $service) {
            if (!in_array((int) $service['service_id'], $allowed_services, TRUE)) {
                return FALSE;
            }
        }

        // El turno completo debe entrar en el nuevo horario de ese día.
        $day = (int) date('N', strtotime($appointment['start_at']));
        $start = date('H:i', strtotime($appointment['start_at']));
        $end = date('H:i', strtotime($appointment['end_at']));

        foreach ($schedule_rows as $row) {
            if ((int) $row['day'] !== $day || empty($row['enabled'])) {
                continue;
            }

            if ($row['start'] <= $start && $row['end'] >= $end) {
                return TRUE;
            }
        }

        return FALSE;
    }

    /**
     * Guarda datos, horarios y servicios del peluquero en una sola transacción.
     *
     * REQ-14/15/18: si al editar se invalidaría una reserva futura, no se
     * guarda nada. Así nunca quedan turnos activos fuera de disponibilidad.
     * Recibe id (NULL para alta), campos, siete filas de horarios e ids de servicios.
     * Devuelve id guardado o FALSE; last_error explica el rechazo tras rollback.
     */
    public function guardar_peluquero($id, $data, $schedule_rows, $service_ids)
    {
        $this->last_error = '';
        $service_ids = array_values(array_unique(array_map('intval', (array) $service_ids)));

        $this->db->trans_begin();

        if ($id) {
            // Bloquear la fila evita cambios simultáneos de disponibilidad.
            $this->db->query('SELECT id FROM barbers WHERE id = ? FOR UPDATE', array($id));

            $future = $this->db->select("a.*, CONCAT(c.first_name, ' ', c.last_name) client_name", FALSE)
                ->from('appointments a')
                ->join('clients c', 'c.id = a.client_id')
                ->where('a.barber_id', $id)
                ->where('a.start_at >=', date('Y-m-d H:i:s'))
                ->where('a.status', 'reserved')
                ->order_by('a.start_at')
                ->get()
                ->result_array();

            foreach ($future as $appointment) {
                if (!$this->reserva_futura_valida(
                    $appointment,
                    $schedule_rows,
                    $service_ids,
                    !empty($data['active'])
                )) {
                    $this->db->trans_rollback();
                    $this->last_error = 'No se puede guardar: el turno de '
                        . $appointment['client_name'] . ' del '
                        . date('d/m/Y H:i', strtotime($appointment['start_at']))
                        . ' quedaría fuera de la nueva disponibilidad o sin un servicio permitido.';
                    return FALSE;
                }
            }

            $this->db->where('id', $id)->update('barbers', $data);
        } else {
            $this->db->insert('barbers', $data);
            $id = $this->db->insert_id();
        }

        // Los horarios se reemplazan por lo que está marcado en el formulario.
        $this->db->where('barber_id', $id)->delete('barber_schedules');
        foreach ($schedule_rows as $row) {
            if (!$row['enabled']) {
                continue;
            }

            $this->db->insert('barber_schedules', array(
                'barber_id' => $id,
                'day_of_week' => $row['day'],
                'start_time' => $row['start'],
                'end_time' => $row['end'],
                'active' => 1,
            ));
        }

        // Igual criterio para los servicios que puede realizar.
        $this->db->where('barber_id', $id)->delete('barber_services');
        foreach ($service_ids as $service_id) {
            $this->db->insert('barber_services', array(
                'barber_id' => $id,
                'service_id' => $service_id,
            ));
        }

        if ($this->db->trans_status() === FALSE) {
            $this->db->trans_rollback();
            $this->last_error = 'No se pudo guardar el peluquero.';
            return FALSE;
        }

        $this->db->trans_commit();
        return $id;
    }

    /** Reservas futuras usadas por la pantalla de reasignación. */
    public function obtener_turnos_futuros($barber_id)
    {
        return $this->db->select("a.*, CONCAT(c.first_name, ' ', c.last_name) client_name", FALSE)
            ->from('appointments a')
            ->join('clients c', 'c.id = a.client_id')
            ->where('a.barber_id', $barber_id)
            ->where('a.start_at >=', date('Y-m-d H:i:s'))
            ->where('a.status', 'reserved')
            ->order_by('a.start_at')
            ->get()
            ->result_array();
    }
}
