<?php
/** Alta/edición de Turnos: recibe turno, catálogos, servicios seleccionados y errores. */
$editing = $appointment && !empty($appointment['id']);

// En alta también aceptamos valores que vienen desde la consulta de disponibilidad.
$query_services = $this->input->get('service_ids');
if (!$editing && !$selected_services && is_array($query_services)) {
    $selected_services = array_map('intval', $query_services);
}

$client_id = $appointment['client_id'] ?? (int) $this->input->get('client_id');
$barber_id = $appointment['barber_id'] ?? (int) $this->input->get('barber_id');
$date = $appointment['date']
    ?? ($editing ? substr($appointment['start_at'], 0, 10) : ($this->input->get('date', TRUE) ?: date('Y-m-d')));
$time = $appointment['time']
    ?? ($editing ? substr($appointment['start_at'], 11, 5) : ($this->input->get('time', TRUE) ?: ($walkin ? date('H:i') : date('H:i', strtotime('+1 hour')))));
$status = $appointment['status'] ?? ($walkin ? 'attended' : 'reserved');
?>

<div class="heading">
    <div>
        <h1><?= $editing ? 'Editar turno' : ($walkin ? 'Atención sin turno previo' : 'Nuevo turno') ?></h1>
        <p>Elegí los datos de la atención. El servidor vuelve a validar horario, servicios y superposición al guardar.</p>
    </div>
</div>

<?php $this->load->view('compartidas/errores_formulario'); ?>

<section class="card">
    <?= form_open($editing ? 'turnos/actualizar/' . $appointment['id'] : 'turnos/guardar', array('data-appointment-form' => '1')) ?>
        <input type="hidden" name="walk_in" value="<?= $walkin ? 1 : 0 ?>">
        <?php if ($editing): ?>
            <input type="hidden" data-appointment-id value="<?= (int) $appointment['id'] ?>">
        <?php endif; ?>

        <!-- Cliente registrado, profesional y momento de la atención -->
        <div class="formgrid">
            <div class="field">
                <label class="required">Cliente</label>
                <select name="client_id" required>
                    <option value="">Seleccionar</option>
                    <?php foreach ($clients as $client): ?>
                        <option value="<?= (int) $client['id'] ?>" <?= $client_id == $client['id'] ? 'selected' : '' ?>>
                            <?= h($client['first_name'] . ' ' . $client['last_name'] . ' · ' . $client['phone']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <a class="small" href="<?= site_url('clientes/nuevo') ?>">¿No figura? Crear cliente</a>
            </div>

            <div class="field">
                <label class="required">Peluquero</label>
                <select name="barber_id" data-availability-barber required>
                    <option value="">Seleccionar</option>
                    <?php foreach ($barbers as $barber): ?>
                        <option value="<?= (int) $barber['id'] ?>" <?= $barber_id == $barber['id'] ? 'selected' : '' ?>>
                            <?= h($barber['full_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label class="required">Fecha</label>
                <input class="input" type="date" name="date" data-availability-date value="<?= h($date) ?>" required>
            </div>

            <div class="field">
                <label class="required">Hora</label>
                <input class="input" type="time" name="time" data-availability-time value="<?= h($time) ?>" required>
            </div>
        </div>

        <div class="divider"></div>

        <div class="cardhead">
            <div>
                <h3>Servicios</h3>
                <p>Se pueden combinar varios en una misma atención.</p>
            </div>
        </div>

        <!-- data-price/data-minutes alimentan solo los estimados visuales de JavaScript -->
        <div class="service-picker">
            <?php foreach ($services as $service): ?>
                <label class="service-option">
                    <input
                        type="checkbox"
                        name="service_ids[]"
                        value="<?= (int) $service['id'] ?>"
                        data-service-check
                        data-price="<?= h($service['price']) ?>"
                        data-minutes="<?= (int) $service['duration_minutes'] ?>"
                        <?= in_array((int) $service['id'], $selected_services, TRUE) ? 'checked' : '' ?>
                    >
                    <span>
                        <strong><?= h($service['name']) ?></strong>
                        <small><?= money($service['price']) ?> · <?= (int) $service['duration_minutes'] ?> min</small>
                    </span>
                </label>
            <?php endforeach; ?>
        </div>

        <div class="note" style="margin-top:14px">
            Estimado: <strong data-service-total>$0</strong> · <strong data-service-minutes>0 min</strong>.
            Los importes se verifican nuevamente en el servidor.
        </div>

        <!-- Consulta AJAX: usa la ruta pública conservada y las mismas reglas del guardado -->
        <div class="card" style="margin-top:14px;box-shadow:none" data-availability-box data-url="<?= site_url('turnos/disponibilidad') ?>">
            <div class="cardhead">
                <div>
                    <h3>Horarios disponibles</h3>
                    <p>Se calculan con el horario del peluquero, la duración seleccionada y los turnos ya registrados.</p>
                </div>
                <button class="btn" type="button" data-check-availability>Consultar</button>
            </div>
            <div class="small muted" data-availability-message>Elegí peluquero, fecha y al menos un servicio.</div>
            <div class="inline" data-availability-slots style="margin-top:10px"></div>
        </div>

        <!-- Estado y nota del turno; guardar revalida todo en el servidor -->
        <div class="formgrid" style="margin-top:14px">
            <div class="field">
                <label>Estado</label>
                <select name="status">
                    <?php foreach (status_options() as $code => $label): ?>
                        <option value="<?= h($code) ?>" <?= $status === $code ? 'selected' : '' ?>><?= h($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label>Observación</label>
                <textarea name="notes" maxlength="500"><?= h($appointment['notes'] ?? '') ?></textarea>
            </div>
        </div>

        <div class="form-actions">
            <a class="btn" href="<?= site_url('turnos') ?>">Cancelar</a>
            <button class="btn primary" type="submit">Guardar turno</button>
        </div>
    <?= form_close() ?>
</section>
