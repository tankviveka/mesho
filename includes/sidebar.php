<?php
/**
 * Sidebar navigation. Requires $active (string) to be set by the page.
 */

$active = $active ?? 'dashboard';
$navGroups = nav_items();
?>

<aside class="sidebar" id="sidebar" aria-label="Main navigation">
    <div class="sidebar-brand">
        <span class="brand-logo"><i class="bi bi-shop"></i></span>
        <span class="brand-text">
            <strong>Meesho Seller</strong>
            <small>Demo Dashboard</small>
        </span>
        <button class="btn-close-sidebar d-lg-none" type="button" id="sidebarClose" aria-label="Close menu">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>

    <div class="sidebar-demo">
        <span class="demo-pill"><span class="demo-dot"></span> DEMO MODE</span>
        <small>Sample data only &middot; not linked to real Meesho</small>
    </div>

    <nav class="sidebar-nav">
        <?php foreach ($navGroups as $group): ?>
            <?php if (!empty($group['group'])): ?>
                <div class="nav-group-title"><?= e($group['group']) ?></div>
            <?php endif; ?>
            <ul class="nav flex-column">
                <?php foreach ($group['items'] as $item): ?>
                    <?php $isActive = is_active_nav($item['id'], $active); ?>
                    <li class="nav-item">
                        <a class="nav-link<?= $isActive ? ' active' : '' ?>"
                           href="<?= e($item['url']) ?>"
                           <?= $isActive ? 'aria-current="page"' : '' ?>>
                            <i class="bi bi-<?= e($item['icon']) ?>"></i>
                            <span><?= e($item['label']) ?></span>
                            <?php if (!empty($item['badge'])): ?>
                                <span class="nav-badge"><?= e($item['badge']) ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endforeach; ?>
    </nav>

    <div class="sidebar-footer">
        <div class="user-chip">
            <span class="user-avatar"><?= e(strtoupper(substr((string) ($_SESSION['user_name'] ?? 'Demo User'), 0, 1))) ?></span>
            <span class="user-meta">
                <strong><?= e($_SESSION['user_name'] ?? 'Demo User') ?></strong>
                <small><?= e($_SESSION['user_role'] ?? 'Seller') ?></small>
            </span>
        </div>
        <a class="btn btn-outline-light btn-sm w-100 mt-2" href="logout.php">
            <i class="bi bi-box-arrow-right me-1"></i>Logout
        </a>
    </div>
</aside>
<div class="sidebar-overlay" id="sidebarOverlay" hidden></div>
