<?php /** Administración de formas de pago: recibe $methods; Cobros exige Encargado. */ ?>
<div class="heading">
    <div><h1>Formas de pago</h1><p>Efectivo, transferencia, tarjeta y métodos futuros.</p></div>
</div>

<div class="grid g2">
    <!-- Catálogo existente de medios de pago y su estado -->
    <section class="card">
        <div class="tablewrap">
            <table>
                <thead><tr><th>Nombre</th><th>Estado</th></tr></thead>
                <tbody>
                    <?php foreach ($methods as $method): ?>
                        <tr>
                            <td><?= h($method['name']) ?></td>
                            <td><span class="badge <?= $method['active'] ? 'green' : 'neutral' ?>"><?= $method['active'] ? 'Activo' : 'Inactivo' ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="card">
        <div class="cardhead"><div><h3>Nueva forma de pago</h3><p>Disponible para cobros futuros</p></div></div>
        <!-- Alta: el controlador rechaza nombres vacíos o duplicados -->
        <?= form_open('formas-pago/guardar') ?>
            <div class="field">
                <label>Nombre</label>
                <input class="input" name="name" maxlength="80" required placeholder="Ej.: Débito">
            </div>
            <div class="form-actions"><button class="btn primary" type="submit">Agregar</button></div>
        <?= form_close() ?>
    </section>
</div>
