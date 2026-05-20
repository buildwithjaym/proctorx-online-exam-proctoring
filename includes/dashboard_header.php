<?php

if (!isset($pageTitle)) {
    $pageTitle = 'Dashboard';
}

if (!isset($activePage)) {
    $activePage = 'dashboard';
}

if (!isset($panelLabel)) {
    $panelLabel = 'Dashboard';
}

if (!isset($extraStyles)) {
    $extraStyles = [];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo e($pageTitle); ?> | ProctorX</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="<?php echo e(app_url('assets/css/auth.css')); ?>">

    <?php foreach ($extraStyles as $style): ?>
        <link rel="stylesheet" href="<?php echo e(app_url($style)); ?>">
    <?php endforeach; ?>
</head>
<body>

<div class="dashboard-shell">
    <?php require __DIR__ . '/sidebar.php'; ?>

    <main class="dashboard-main">
        <header class="dashboard-topbar">
            <div>
                <span><?php echo e($panelLabel); ?></span>
                <h1><?php echo e($pageTitle); ?></h1>
            </div>

            <a class="logout-link" href="<?php echo e(app_url('logout.php')); ?>">Logout</a>
        </header>