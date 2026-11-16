<?php /** Catálogo de Servicios para Encargado: recibe $services, $q y $archived. */ ?>
<div class="heading">
    <div>
        <h1>Servicios</h1>
        <p>Precios y duración aproximada.</p>
    </div>
    <a class="btn primary" href="<?= site_url('servicios/nuevo') ?>">+ Agregar servicio</a>
</div>
<section class="card">
    <!-- Filtros del catálogo actual o archivado -->
    <form class="toolbar" method="get" action="<?= site_url('servicios') ?>">
        <div class="filters">
            <input class="input" name="q" value="<?= h($q) ?>" placeholder="Nombre del servicio">
            <label class="check">
                <input type="checkbox" name="archived" value="1" <?= $archived ? 'checked' : '' ?>> Eliminados
            </label>
        </div>
        <div class="inline">
            <button class="btn" type="submit">Buscar</button>
            <a class="btn" href="<?= site_url('servicios') ?>">Limpiar</a>
        </div>
    </form>

    <!-- Precio y duración actuales; los turnos guardan sus propios snapshots -->
    <div class="tablewrap">
        <table>
            <thead>
                <tr><th>ID</th><th>Servicio</th><th>Precio</th><th>Duración</th><th>Estado</th><th>Acciones</th></tr>
            </thead>
            <tbody>
                <?php if (!$services): ?>
                    <tr><td colspan="6" class="empty">No se encontraron servicios.</td></tr>
                <?php endif; ?>
                <?php foreach ($services as $service): ?>
                    <tr>
                        <td><?= (int) $service['id'] ?></td>
                        <td><strong><?= h($service['name']) ?></strong></td>
                        <td><?= money($service['price']) ?></td>
                        <td><?= (int) $service['duration_minutes'] ?> min</td>
                        <td>
                            <span class="badge <?= $service['active'] ? 'green' : 'neutral' ?>"><?= $service['active'] ? 'Activo' : 'Inactivo' ?></span>
                        </td>
                        <td>
                            <div class="inline">
                                <?php if ($archived): ?>
                                    <?= form_open('servicios/restaurar/' . $service['id']) ?>
                                        <button class="btn" type="submit">Restaurar</button>
                                    <?= form_close() ?>
                                <?php else: ?>
                                    <a class="btn" href="<?= site_url('servicios/editar/' . $service['id']) ?>">Editar</a>
                                    <?= form_open('servicios/eliminar/' . $service['id']) ?>
                                        <button class="btn danger" type="submit" data-confirm="¿Eliminar este servicio? Los turnos anteriores conservarán su precio.">Eliminar</button>
                                    <?= form_close() ?>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
