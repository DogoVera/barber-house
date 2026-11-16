<?php
// Aplica categoría en BD y en la pantalla de Servicios (idempotente).

$mysqli = new mysqli('127.0.0.1', 'root', '', 'barber_house');
if ($mysqli->connect_errno) {
    fwrite(STDERR, "No se pudo conectar a la base de datos: " . $mysqli->connect_error . PHP_EOL);
    exit(1);
}
$mysqli->set_charset('utf8mb4');

$mysqli->query("ALTER TABLE services ADD COLUMN IF NOT EXISTS categoria VARCHAR(40) NULL AFTER name");
$mysqli->query("UPDATE services SET categoria = CASE
    WHEN name LIKE '%Barba%' THEN 'Barbería'
    WHEN name LIKE '%Color%' THEN 'Estilismo'
    WHEN name LIKE '%Corte%' THEN 'Barbería'
    ELSE 'Otro'
END");
echo "BD: columna categoria lista y servicios clasificados." . PHP_EOL;

// 1) Listado: agregar columna Categoría (si no existe)
$path = __DIR__ . '/application/views/servicios/listado_servicios.php';
$html = file_get_contents($path);
if (strpos($html, '<th>Categoría</th>') === false) {
    $html = str_replace(
        '<th>Servicio</th><th>Precio</th>',
        '<th>Servicio</th><th>Categoría</th><th>Precio</th>',
        $html
    );
    $html = str_replace(
        'colspan="6" class="empty"',
        'colspan="7" class="empty"',
        $html
    );
    $html = str_replace(
        "<td><strong><?= h(\$service['name']) ?></strong></td>\n                        <td><?= money(\$service['price']) ?></td>",
        "<td><strong><?= h(\$service['name']) ?></strong></td>\n                        <td><?= h(\$service['categoria'] ?? '—') ?></td>\n                        <td><?= money(\$service['price']) ?></td>",
        $html
    );
    file_put_contents($path, $html);
    echo "Vista listado: columna Categoría agregada." . PHP_EOL;
} else {
    echo "Vista listado: ya tenía columna Categoría." . PHP_EOL;
}

// 2) Formulario: agregar dropdown Categoría (si no existe)
$path = __DIR__ . '/application/views/servicios/formulario_servicio.php';
$html = file_get_contents($path);
if (strpos($html, 'name="categoria"') === false) {
    $search = "            <div class=\"field\">\n                <label class=\"required\">Precio</label>";
    $insert = "            <div class=\"field\">\n                <label>Categoría</label>\n                <select name=\"categoria\">\n                    <option value=\"\">Seleccionar</option>\n                    <option value=\"Barbería\" <?= (\$service['categoria'] ?? '') === 'Barbería' ? 'selected' : '' ?>>Barbería</option>\n                    <option value=\"Estilismo\" <?= (\$service['categoria'] ?? '') === 'Estilismo' ? 'selected' : '' ?>>Estilismo</option>\n                    <option value=\"Otro\" <?= (\$service['categoria'] ?? '') === 'Otro' ? 'selected' : '' ?>>Otro</option>\n                </select>\n            </div>\n" . $search;
    $html = str_replace($search, $insert, $html);
    file_put_contents($path, $html);
    echo "Vista formulario: desplegable Categoría agregado." . PHP_EOL;
} else {
    echo "Vista formulario: ya tenía desplegable Categoría." . PHP_EOL;
}

// 3) Controlador: guardar categoria (si no existe)
$path = __DIR__ . '/application/controllers/Servicios.php';
$html = file_get_contents($path);
if (strpos($html, "'categoria'") === false) {
    $search = "            'name' => trim(\$this->posted('name', '')),\n            'price' => (float) \$this->posted('price', 0),";
    $insert = "            'name' => trim(\$this->posted('name', '')),\n            'categoria' => trim(\$this->posted('categoria', '')),\n            'price' => (float) \$this->posted('price', 0),";
    $html = str_replace($search, $insert, $html);
    file_put_contents($path, $html);
    echo "Controlador: campo categoria agregado al guardado." . PHP_EOL;
} else {
    echo "Controlador: ya guardaba categoria." . PHP_EOL;
}

echo "Listo." . PHP_EOL;
