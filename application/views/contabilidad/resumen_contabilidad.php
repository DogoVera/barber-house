<?php /** Contabilidad: recibe período, totales, liquidación, categorías y movimientos ya ordenados. */ ?>
<div class="heading">
    <div><h1>Contabilidad</h1><p>Movimientos y liquidaciones basados en importes realmente cobrados.</p></div>
    <div class="inline">
        <a class="btn" href="<?= site_url('egresos') ?>">Administrar egresos</a>
        <a class="btn" href="<?= site_url('formas-pago') ?>">Formas de pago</a>
    </div>
</div>

<!-- Período inclusivo aplicado a ingresos, gastos y liquidación -->
<section class="card" style="margin-bottom:16px">
    <form class="toolbar" method="get" action="<?= site_url('contabilidad') ?>">
        <div class="filters">
            <div class="field"><label>Desde</label><input class="input w-auto" type="date" name="from" value="<?= h($from) ?>" required></div>
            <div class="field"><label>Hasta</label><input class="input w-auto" type="date" name="to" value="<?= h($to) ?>" required></div>
        </div>
        <button class="btn primary" type="submit">Consultar período</button>
    </form>
</section>

<!-- Totales calculados en modelos; el controlador prepara el resultado neto -->
<div class="kpis">
    <div class="kpi"><span>Ingresos</span><strong><?= money($income) ?></strong><small>Cobros registrados</small></div>
    <div class="kpi"><span>Egresos</span><strong><?= money($expenses_total) ?></strong><small>Gastos del local</small></div>
    <div class="kpi"><span>A pagar peluqueros</span><strong><?= money($commissions) ?></strong><small>Comisiones históricas</small></div>
    <div class="kpi"><span>Resultado del local</span><strong><?= money($result) ?></strong><small>Ingresos - egresos - comisiones</small></div>
</div>

<div class="grid g2" style="margin-top:16px">
    <section class="card">
        <div class="cardhead"><div><h3>Registrar egreso</h3><p>Solo Encargado</p></div></div>
        <!-- Carga de gasto con categoría; no es una segunda carga de ingresos -->
        <?= form_open('egresos/guardar') ?>
            <div class="formgrid">
                <div class="field"><label>Fecha</label><input class="input" type="date" name="expense_date" value="<?= date('Y-m-d') ?>" required></div>
                <div class="field">
                    <label>Categoría</label>
                    <select name="category_id" required>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= (int) $category['id'] ?>"><?= h($category['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field"><label>Concepto</label><input class="input" name="concept" maxlength="180" required></div>
                <div class="field"><label>Monto</label><input class="input" type="number" min="0.01" step="0.01" name="amount" required></div>
            </div>
            <div class="form-actions"><button class="btn primary" type="submit">Registrar egreso</button></div>
        <?= form_close() ?>
    </section>

    <section class="card">
        <div class="cardhead">
            <div>
                <h3>Liquidación por peluquero</h3>
                <p>Producción bruta y comisión histórica aplicada a cada cobro.</p>
            </div>
        </div>
        <div class="tablewrap">
            <table>
                <thead><tr><th>Peluquero</th><th>Atenciones</th><th>Producción</th><th>Porcentaje efectivo</th><th>A pagar</th></tr></thead>
                <tbody>
                    <?php if (!$production): ?><tr><td colspan="5" class="empty">Sin cobros en este período.</td></tr><?php endif; ?>
                    <?php foreach ($production as $barber): ?>
                        <?php
                        // Indicador visual ponderado: no vuelve a calcular la comisión a pagar.
                        $effective_percent = 0;
                        if ($barber['production'] > 0) {
                            $effective_percent = 100 * $barber['payout'] / $barber['production'];
                        }
                        ?>
                        <tr>
                            <td><?= h($barber['full_name']) ?></td>
                            <td><?= (int) $barber['attentions'] ?></td>
                            <td><?= money($barber['production']) ?></td>
                            <td><?= $barber['production'] > 0 ? h(number_format($effective_percent, 2, ',', '.')) . ' %' : '—' ?></td>
                            <td><?= money($barber['payout']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<!-- Línea de tiempo económica preparada por Contabilidad, con enlaces de corrección -->
<section class="card" style="margin-top:16px">
    <div class="cardhead"><div><h3>Movimientos</h3><p>Ordenados del más reciente al más antiguo</p></div></div>

    <div class="tablewrap">
        <table>
            <thead><tr><th>Fecha</th><th>Tipo</th><th>Detalle</th><th>Importe</th><th></th></tr></thead>
            <tbody>
                <?php if (!$movements): ?><tr><td colspan="5" class="empty">Sin movimientos en este período.</td></tr><?php endif; ?>
                <?php foreach ($movements as $movement): ?>
                    <tr>
                        <td><?= date('d/m/Y H:i', strtotime($movement['date'])) ?></td>
                        <td><?= h($movement['kind']) ?></td>
                        <td><?= h($movement['detail']) ?></td>
                        <td><?= money($movement['amount']) ?></td>
                        <td><a class="btn" href="<?= $movement['link'] ?>">Corregir</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
