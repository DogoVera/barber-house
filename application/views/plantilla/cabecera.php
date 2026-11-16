<?php /** Cabecera común: MY_Controller entrega $page_title; abre el documento y carga estilos locales. */ ?>
<!doctype html>
<html lang="es" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= h($page_title ?? 'Barber House') ?> · Barber House</title>
    <link rel="stylesheet" href="<?= asset_url('assets/css/estilos_barberia.css') ?>">
</head>
<body>
