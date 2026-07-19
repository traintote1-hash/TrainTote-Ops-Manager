<?php

require_once __DIR__ . '/navbar_helpers.php';

$currentNavPath = tt_nav_request_path();
$currentNavHost = tt_nav_request_host();

$primaryNavItems = array(
    array('key' => 'dashboard', 'label' => 'Dashboard', 'href' => tt_nav_ops_href('/dashboard.php', $currentNavHost)),
    array('key' => 'equipment', 'label' => 'Equipment', 'href' => tt_nav_ops_href('/equipment/list.php', $currentNavHost)),
    array('key' => 'car_status', 'label' => 'Car Status', 'href' => tt_nav_ops_href('/equipment/status.php', $currentNavHost)),
    array('key' => 'industries', 'label' => 'Industries', 'href' => tt_nav_ops_href('/industries/list.php', $currentNavHost)),
    array('key' => 'waybills', 'label' => 'Waybills', 'href' => tt_nav_ops_href('/waybills/list.php', $currentNavHost)),
    array('key' => 'operations', 'label' => 'Operations', 'href' => tt_nav_ops_href('/operations/dashboard.php', $currentNavHost)),
    array('key' => 'ai', 'label' => 'AI Scanner', 'href' => tt_nav_ops_href('/ai/scan_equipment.php', $currentNavHost)),
    array('key' => 'wiki', 'label' => 'Wiki', 'href' => 'https://wiki.traintote.com/'),
    array('key' => 'forum', 'label' => 'Forum', 'href' => 'https://forum.traintote.com/'),
);

?>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4" aria-label="Main navigation">
    <div class="container-fluid">
        <a class="navbar-brand" href="/dashboard.php">TrainTote Ops Manager</a>

        <button
            class="navbar-toggler"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#navbarNav"
            aria-controls="navbarNav"
            aria-expanded="false"
            aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <?php foreach ($primaryNavItems as $navItem): ?>
                    <?php $isActive = tt_nav_is_active($navItem['key'], $currentNavPath, $currentNavHost); ?>
                    <li class="nav-item">
                        <a
                            class="nav-link<?= $isActive ? ' active' : '' ?>"
                            href="<?= htmlspecialchars($navItem['href'], ENT_QUOTES, 'UTF-8') ?>"
                            <?= $isActive ? 'aria-current="page"' : '' ?>><?= htmlspecialchars($navItem['label'], ENT_QUOTES, 'UTF-8') ?></a>
                    </li>
                <?php endforeach; ?>
            </ul>

            <ul class="navbar-nav">
                <li class="nav-item">
                    <a class="nav-link" href="<?= htmlspecialchars(tt_nav_ops_href('/logout.php', $currentNavHost), ENT_QUOTES, 'UTF-8') ?>">Logout</a>
                </li>
            </ul>
        </div>
    </div>
</nav>
