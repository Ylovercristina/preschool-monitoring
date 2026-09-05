<?php
/**
 * Header and Topbar Navigation
 * Preschool Monitoring System
 */

require_once __DIR__ . '/auth.php';

$user = currentUser();
$role = userRole();
$flash = getFlash();

$pageTitle = $pageTitle ?? 'Dashboard';
$pageSubtitle = $pageSubtitle ?? APP_SUBTITLE;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> | <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= url('assets/css/style.css') ?>">
    <link rel="stylesheet" href="<?= url('assets/css/dashboard.css') ?>">
</head>
<body>

<div class="app-wrapper">
    <!-- Sidebar -->
    <?php require_once __DIR__ . '/sidebar/sidebar.php'; ?>

    <!-- Main Container -->
    <div class="app-main">
        <!-- Topbar -->
        <header class="app-topbar">
            <div class="topbar-left">
                <button class="mobile-toggle" id="mobileToggle" aria-label="Toggle Sidebar">☰</button>
                <div class="page-title-box">
                    <h1><?= htmlspecialchars($pageTitle) ?></h1>
                    <p><?= htmlspecialchars($pageSubtitle) ?></p>
                </div>
            </div>

            <div class="topbar-right">
                <!-- Role Badge -->
                <span class="role-pill <?= htmlspecialchars($role) ?>">
                    <?= strtoupper(htmlspecialchars($role)) ?> PORTAL
                </span>

                <!-- User Profile Pill -->
                <div class="user-profile-menu">
                    <div class="user-avatar">
                        <?= strtoupper(substr($user['name'] ?? 'U', 0, 1)) ?>
                    </div>
                    <div style="line-height: 1.1;">
                        <div style="font-size: 0.88rem; font-weight: 700; color: var(--text-primary);">
                            <?= htmlspecialchars($user['name'] ?? 'User') ?>
                        </div>
                        <div style="font-size: 0.72rem; color: var(--text-muted);">
                            <?= htmlspecialchars($user['email'] ?? '') ?>
                        </div>
                    </div>
                    <a href="<?= url('auth/logout.php') ?>" class="btn btn-secondary btn-sm" style="margin-left: 8px; border-radius: var(--radius-full); padding: 4px 10px;" title="Logout">
                        Logout
                    </a>
                </div>
            </div>
        </header>

        <!-- Page Inner Content -->
        <main class="page-container">
            <?php if ($flash): ?>
                <div class="flash-alert flash-<?= htmlspecialchars($flash['type']) ?>">
                    <span><?= htmlspecialchars($flash['message']) ?></span>
                    <button type="button" onclick="this.parentElement.remove()" style="background:none; border:none; cursor:pointer; font-size:1.1rem; color:inherit;">&times;</button>
                </div>
            <?php endif; ?>
