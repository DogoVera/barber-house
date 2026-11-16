<?php /** Excepción no controlada: CI3 entrega mensaje y objeto $exception para el diagnóstico. */ ?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Error</title>
</head>
<body style="font-family:Segoe UI,Arial;background:#171614;color:#eee;padding:40px">
    <div style="max-width:900px;margin:auto;background:#23211f;padding:24px;border-radius:16px">
        <h1>Ocurrió un error</h1>
        <p><?= html_escape($message) ?></p>
        <!-- La traza técnica solo se presenta en desarrollo -->
        <?php if (ENVIRONMENT === 'development'): ?>
            <pre style="white-space:pre-wrap"><?= html_escape($exception->getTraceAsString()) ?></pre>
        <?php endif; ?>
    </div>
</body>
</html>
