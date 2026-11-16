<?php
/** Vista parcial: recibe $errors del controlador; solo muestra mensajes escapados. */
?>
<?php if ($errors): ?>
    <div class="errors">
        <ul>
            <?php foreach ($errors as $error): ?>
                <li><?= h($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>
