<?php /** Corrección de Contabilidad: recibe $expense vigente, $categories y $errors. */ ?>
<div class="heading">
    <div>
        <h1>Corregir egreso</h1>
        <p>Función exclusiva del Encargado.</p>
    </div>
</div>
<?php $this->load->view('compartidas/errores_formulario'); ?>
<section class="card" style="max-width:780px">
    <!-- Corrección de fecha, categoría, concepto e importe del gasto -->
    <?= form_open('egresos/actualizar/' . $expense['id']) ?>
        <div class="formgrid">
            <div class="field">
                <label>Fecha</label>
                <input class="input" type="date" name="expense_date" value="<?= h($expense['expense_date']) ?>" required>
            </div>
            <div class="field">
                <label>Categoría</label>
                <select name="category_id" required>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= (int) $category['id'] ?>" <?= $expense['category_id'] == $category['id'] ? 'selected' : '' ?>>
                            <?= h($category['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label>Concepto</label>
                <input class="input" name="concept" value="<?= h($expense['concept']) ?>" required>
            </div>
            <div class="field">
                <label>Monto</label>
                <input class="input" type="number" step="0.01" min="0.01" name="amount" value="<?= h($expense['amount']) ?>" required>
            </div>
        </div>
        <div class="form-actions">
            <a class="btn" href="<?= site_url('egresos') ?>">Cancelar</a>
            <button class="btn primary" type="submit">Guardar corrección</button>
        </div>
    <?= form_close() ?>

    <!-- Anular es una operación separada y conserva el registro en MySQL -->
    <?= form_open('egresos/anular/' . $expense['id']) ?>
        <button class="btn" type="submit" data-confirm="¿Anular este egreso? Dejará de contar en el resultado del local.">Anular egreso</button>
    <?= form_close() ?>
</section>
