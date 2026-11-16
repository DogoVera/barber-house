<?php
/** Vista de Clientes: alta/edición; recibe $client (o NULL) y $errors. */
$editing = $client && !empty($client['id']);
?>

<div class="heading">
    <div>
        <h1><?= $editing ? 'Editar cliente' : 'Nuevo cliente' ?></h1>
        <p>Datos mínimos definidos en el relevamiento.</p>
    </div>
</div>

<?php $this->load->view('compartidas/errores_formulario'); ?>

<section class="card">
    <?= form_open($editing ? 'clientes/actualizar/' . $client['id'] : 'clientes/guardar') ?>
        <!-- Datos personales obligatorios: el controlador también los valida -->
        <div class="formgrid">
            <div class="field">
                <label class="required">Nombre</label>
                <input class="input" name="first_name" value="<?= h($client['first_name'] ?? '') ?>" required>
            </div>

            <div class="field">
                <label class="required">Apellido</label>
                <input class="input" name="last_name" value="<?= h($client['last_name'] ?? '') ?>" required>
            </div>

            <div class="field">
                <label class="required">Teléfono</label>
                <input class="input" name="phone" value="<?= h($client['phone'] ?? '') ?>" required>
            </div>
        </div>
        
       <div class="field">
            <label>Tipo</label>
            <select name="tipo">
             <option value="">Seleccione un tipo</option>
             <option value="VIP" <?= strtolower($client['tipo'] ?? '') == 'vip' ? 'selected' : '' ?>>VIP</option>
             <option value="Regular" <?= strtolower($client['tipo'] ?? '') == 'regular' ? 'selected' : '' ?>>Regular</option>
             <option value="Nuevo" <?= strtolower($client['tipo'] ?? '') == 'nuevo' ? 'selected' : '' ?>>Nuevo</option>
            </select>
        </div>

        <!-- Preferencias o aclaraciones opcionales del cliente -->
        <div class="field" style="margin-top:13px">
            <label>Observaciones</label>
            <textarea name="notes"><?= h($client['notes'] ?? '') ?></textarea>
        </div>

        <!-- Guardado por POST con protección CSRF de form_open -->
        <div class="form-actions">
            <a class="btn" href="<?= site_url('clientes') ?>">Cancelar</a>
            <button class="btn primary" type="submit">Guardar cliente</button>
        </div>
    <?= form_close() ?>
</section>
