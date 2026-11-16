<?php
/** Formulario de Peluqueros: recibe datos, jornadas, catálogo, selección y errores. */
// Convertimos los horarios a un mapa [día => horario] para leerlos fácilmente.
$schedule_map = array();
foreach ($schedules as $schedule) {
    $schedule_map[(int) $schedule['day_of_week']] = $schedule;
}

$editing = $barber && !empty($barber['id']);
?>

<div class="heading">
    <div>
        <h1><?= $editing ? 'Editar peluquero' : 'Nuevo peluquero' ?></h1>
        <p>Datos, servicios, días y horarios de trabajo.</p>
    </div>
</div>

<?php $this->load->view('compartidas/errores_formulario'); ?>

<section class="card">
    <?= form_open($editing ? 'peluqueros/actualizar/' . $barber['id'] : 'peluqueros/guardar') ?>
        <!-- Identificación, porcentaje actual y habilitación para nuevos turnos -->
        <div class="formgrid">
            <div class="field">
                <label class="required">Nombre y apellido</label>
                <input class="input" name="full_name" value="<?= h($barber['full_name'] ?? '') ?>" required>
            </div>

            <div class="field">
                <label>Teléfono</label>
                <input class="input" name="phone" value="<?= h($barber['phone'] ?? '') ?>">
            </div>

            <div class="field">
                <label class="required">Porcentaje</label>
                <input
                    class="input"
                    type="number"
                    min="0"
                    max="100"
                    step="0.01"
                    name="commission_percent"
                    value="<?= h($barber['commission_percent'] ?? '50') ?>"
                    required
                >
            </div>

            <div class="check">
                <label>
                    <input type="checkbox" name="active" value="1" <?= !isset($barber['active']) || $barber['active'] ? 'checked' : '' ?>>
                    Peluquero activo
                </label>
            </div>
        </div>

        <div class="divider"></div>
        <div class="cardhead">
            <div>
                <h3>Servicios que realiza</h3>
                <p>Se usan para validar los turnos.</p>
            </div>
        </div>

        <!-- Servicios permitidos: el backend exige que cubran las reservas futuras -->
        <div class="service-picker">
            <?php foreach ($services as $service): ?>
                <label class="service-option">
                    <input
                        type="checkbox"
                        name="service_ids[]"
                        value="<?= (int) $service['id'] ?>"
                        <?= in_array((int) $service['id'], $selected_services, TRUE) ? 'checked' : '' ?>
                    >
                    <span>
                        <strong><?= h($service['name']) ?></strong>
                        <small><?= money($service['price']) ?> · <?= (int) $service['duration_minutes'] ?> min</small>
                    </span>
                </label>
            <?php endforeach; ?>
        </div>

        <div class="divider"></div>
        <div class="cardhead">
            <div>
                <h3>Días y horarios</h3>
                <p>El sistema impedirá turnos fuera de estos rangos.</p>
            </div>
        </div>

        <!-- Una fila por día: coincide con day_1/start_1/end_1 leídos por Peluqueros -->
        <?php for ($day = 1; $day <= 7; $day++): ?>
            <?php $schedule = $schedule_map[$day] ?? NULL; ?>
            <div class="schedule-grid">
                <div class="day"><?= h(day_name($day)) ?></div>

                <label class="checkday">
                    <input type="checkbox" name="day_<?= $day ?>" value="1" <?= $schedule ? 'checked' : '' ?>>
                    Trabaja
                </label>

                <input
                    class="input"
                    type="time"
                    name="start_<?= $day ?>"
                    value="<?= h($schedule ? substr($schedule['start_time'], 0, 5) : '09:00') ?>"
                >

                <input
                    class="input"
                    type="time"
                    name="end_<?= $day ?>"
                    value="<?= h($schedule ? substr($schedule['end_time'], 0, 5) : '18:00') ?>"
                >
            </div>
        <?php endfor; ?>

        <div class="form-actions">
            <a class="btn" href="<?= site_url('peluqueros') ?>">Cancelar</a>
            <button class="btn primary" type="submit">Guardar peluquero</button>
        </div>
    <?= form_close() ?>
</section>
