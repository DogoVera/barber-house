<?php
/** Alta/edición de Servicios: recibe $service o NULL y errores de validación. */
$editing = $service && !empty($service['id']);
?>

<div class="heading">
    <div>
        <h1><?= $editing ? 'Editar servicio' : 'Nuevo servicio' ?></h1>
        <p>El precio actualizado se usa en turnos futuros; los turnos existentes conservan su snapshot.</p>
    </div>
</div>

<?php $this->load->view('compartidas/errores_formulario'); ?>

<section class="card">
    <?= form_open($editing ? 'servicios/actualizar/' . $service['id'] : 'servicios/guardar') ?>
        <!-- Valores del catálogo para nuevas selecciones, no para recalcular históricos -->
        <div class="formgrid3">
            <div class="field">
                <label class="required">Nombre</label>
                <input class="input" name="name" value="<?= h($service['name'] ?? '') ?>" required>
            </div>
            <div class="field">
                <label class="required">Precio</label>
                <input class="input" type="number" min="0" step="0.01" name="price" value="<?= h($service['price'] ?? '') ?>" required>
            </div>
            <div class="field">
                <label class="required">Duración (minutos)</label>
                <input class="input" type="number" min="1" name="duration_minutes" value="<?= h($service['duration_minutes'] ?? '45') ?>" required>
            </div>
        </div>

        <!-- Actividad: determina si puede seleccionarse en nuevos turnos -->
        <div class="check" style="margin-top:14px">
            <label><input type="checkbox" name="active" value="1" <?= !isset($service['active']) || $service['active'] ? 'checked' : '' ?>> Servicio activo</label>
        </div>

        <div class="form-actions">
            <a class="btn" href="<?= site_url('servicios') ?>">Cancelar</a>
            <button class="btn primary" type="submit">Guardar</button>
        </div>
    <?= form_close() ?>
</section>
