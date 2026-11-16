<?php
/**
 * Vista parcial de paginación: no consulta MySQL ni vuelve a contar registros.
 * Espera $paginacion, $ruta_paginacion y $filtros_paginacion.
 */
if (empty($paginacion) || $paginacion['pages'] < 2) {
    return;
}
?>
<div class="space" style="margin-top:15px">
    <span class="small muted">
        Mostrando <?= (int) $paginacion['first'] ?>&ndash;<?= (int) $paginacion['last'] ?>
        de <?= (int) $paginacion['total'] ?>
    </span>

    <span class="inline">
        <?php if ($paginacion['page'] > 1): ?>
            <a class="btn" href="<?= page_url($ruta_paginacion, $paginacion['page'] - 1, $filtros_paginacion) ?>">Anterior</a>
        <?php endif; ?>

        <span class="small muted">Página <?= (int) $paginacion['page'] ?> de <?= (int) $paginacion['pages'] ?></span>

        <?php if ($paginacion['page'] < $paginacion['pages']): ?>
            <a class="btn" href="<?= page_url($ruta_paginacion, $paginacion['page'] + 1, $filtros_paginacion) ?>">Siguiente</a>
        <?php endif; ?>
    </span>
</div>
