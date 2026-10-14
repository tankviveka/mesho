<?php
/**
 * Common page header: <head>, sidebar, top bar and the opening content wrapper.
 *
 * Usage:
 *   $pageTitle = 'Dashboard';
 *   $active    = 'dashboard';
 *   require __DIR__ . '/includes/header.php';
 *   ... page body ...
 *   require __DIR__ . '/includes/footer.php';
 */

$pageTitle = $pageTitle ?? APP_NAME;
$active = $active ?? 'dashboard';
$flash = flash_take();
?>
<!doctype html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($pageTitle) ?> | <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="assets/vendor/bootstrap.min.css">
    <link rel="stylesheet" href="assets/vendor/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<a class="skip-link" href="#mainContent">Skip to content</a>

<div class="app-shell">
    <?php require __DIR__ . '/sidebar.php'; ?>

    <div class="main-column">
        <header class="topbar">
            <div class="topbar-left">
                <button class="btn btn-light btn-sm sidebar-toggle d-lg-none" type="button" id="sidebarOpen" aria-label="Open menu">
                    <i class="bi bi-list"></i>
                </button>
                <div class="page-heading">
                    <h1><?= e($pageTitle) ?></h1>
                    <small class="page-subtitle"><?= e(DEMO_NOTICE) ?></small>
                </div>
            </div>

            <div class="topbar-right">
                <span class="demo-badge" title="This website uses generated sample data only. It is not connected to Meesho.">
                    <span class="demo-dot"></span> DEMO MODE
                </span>
                <span class="topbar-date d-none d-md-flex">
                    <i class="bi bi-calendar3"></i><?= e(date('d M Y')) ?>
                </span>
                <div class="dropdown">
                    <button class="btn btn-light btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-person-circle me-1"></i>
                        <span class="d-none d-sm-inline"><?= e($_SESSION['user_name'] ?? 'Demo User') ?></span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><h6 class="dropdown-header">Signed in as <?= e($_SESSION['user_username'] ?? 'demo') ?></h6></li>
                        <li><a class="dropdown-item" href="settings.php"><i class="bi bi-gear me-2"></i>Settings</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>
        </header>

        <main class="content-area" id="mainContent">
            <?php if (!empty($flash)): ?>
                <div class="alert alert-<?= e($flash['type'] === 'error' ? 'danger' : $flash['type']) ?> alert-dismissible fade show" role="alert">
                    <?= e($flash['message']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
