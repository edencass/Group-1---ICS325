<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/security.php';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= isset($page_title) ? e($page_title) . ' — ' : '' ?><?= e(APP_NAME) ?></title>
    <link
      href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
      rel="stylesheet"
      integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
      crossorigin="anonymous">
    <link rel="stylesheet" href="<?= e(APP_URL) ?>/assets/css/style.css">
</head>
<body>
<?php include __DIR__ . '/dev_banner.php'; ?>
<?php include __DIR__ . '/navigation.php'; ?>
<main class="container py-4">