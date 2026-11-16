<?php
/** Alta/edición de Usuarios: recibe cuenta o NULL, roles reales y errores. */
$editing = $user && !empty($user['id']);
?>

<div class="heading">
    <div><h1><?= $editing ? 'Editar usuario' : 'Nuevo usuario' ?></h1><p>Perfiles Recepcionista y Encargado.</p></div>
</div>

<?php $this->load->view('compartidas/errores_formulario'); ?>

<section class="card" style="max-width:850px">
    <?= form_open($editing ? 'usuarios/actualizar/' . $user['id'] : 'usuarios/guardar') ?>
        <!-- Cuenta y perfil; Usuarios valida y genera el hash de contraseña en backend -->
        <div class="formgrid">
            <div class="field">
                <label class="required">Nombre</label>
                <input class="input" name="full_name" value="<?= h($user['full_name'] ?? '') ?>" required>
            </div>
            <div class="field">
                <label class="required">Usuario</label>
                <input class="input" name="username" value="<?= h($user['username'] ?? '') ?>" required>
            </div>
            <div class="field">
                <!-- En edición, dejar vacío conserva la contraseña anterior -->
                <label><?= $editing ? 'Nueva contraseña (opcional)' : 'Contraseña' ?></label>
                <input class="input" type="password" name="password" <?= !$editing ? 'required' : '' ?>>
            </div>
            <div class="field">
                <label class="required">Perfil</label>
                <select name="role_id" required>
                    <?php foreach ($roles as $role): ?>
                        <option value="<?= (int) $role['id'] ?>" <?= ($user['role_id'] ?? '') == $role['id'] ? 'selected' : '' ?>><?= h($role['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="check" style="margin-top:14px">
            <label><input type="checkbox" name="active" value="1" <?= !isset($user['active']) || $user['active'] ? 'checked' : '' ?>> Usuario activo</label>
        </div>

        <div class="form-actions">
            <a class="btn" href="<?= site_url('usuarios') ?>">Cancelar</a>
            <button class="btn primary" type="submit">Guardar usuario</button>
        </div>
    <?= form_close() ?>
</section>
