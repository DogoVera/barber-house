<?php /** Detalle de Clientes: recibe $client y $history ya consultados por Cliente_model. */ ?>
<div class="heading">
    <div>
        <h1><?= h($client['first_name'] . ' ' . $client['last_name']) ?></h1>
        <p><?= h($client['phone']) ?></p>
    </div>
    <div class="inline">
        <a class="btn" href="<?= site_url('clientes/editar/' . $client['id']) ?>">Editar</a>
        <a class="btn primary" href="<?= site_url('turnos/nuevo?client_id=' . $client['id']) ?>">Nuevo turno</a>
    </div>
</div>

<div class="grid g2">
    <!-- Datos personales y observaciones del cliente -->
    <section class="card">
        <div class="cardhead">
            <div><h3>Datos</h3><p>Información del cliente</p></div>
        </div>
        <div class="row"><span>Nombre</span><strong><?= h($client['first_name'] . ' ' . $client['last_name']) ?></strong></div>
        <div class="row"><span>Teléfono</span><strong><?= h($client['phone']) ?></strong></div>
        <div class="row"><span>Tipo</span><strong><?= h($client['tipo'] ?: '—') ?></strong></div>
        <div class="row"><span>Observaciones</span><strong><?= h($client['notes'] ?: '—') ?></strong></div>
    </section>

    <!-- Historial con peluquero, servicios y estado de cada atención -->
    <section class="card">
        <div class="cardhead">
            <div><h3>Historial</h3><p>Turnos anteriores</p></div>
        </div>
        <div class="tablewrap">
            <table>
                <thead><tr><th>Fecha</th><th>Peluquero</th><th>Servicios</th><th>Estado</th></tr></thead>
                <tbody>
                    <?php if (!$history): ?>
                        <tr><td colspan="4" class="empty">Sin historial todavía.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($history as $appointment): ?>
                        <tr>
                            <td><?= date('d/m/Y H:i', strtotime($appointment['start_at'])) ?></td>
                            <td><?= h($appointment['barber_name']) ?></td>
                            <td><?= h($appointment['services'] ?: '—') ?></td>
                            <td><span class="badge <?= status_class($appointment['status']) ?>"><?= h(status_label($appointment['status'])) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
