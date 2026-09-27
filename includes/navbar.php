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
    array('key' => 'wiki', 'label' => 'Wiki', 'href' => tt_nav_community_href('https://wiki.traintote.com/', $currentNavHost)),
    array('key' => 'forum', 'label' => 'Forum', 'href' => tt_nav_community_href('https://forum.traintote.com/', $currentNavHost)),
);

if ($currentNavHost === 'demo.traintote.com') {
    $primaryNavItems = array_values(array_filter($primaryNavItems, function ($navItem) {
        return isset($navItem['key']) && $navItem['key'] !== 'forum';
    }));
}

$profilePhoto = '';
if (!empty($_SESSION['user_id'])) {
    $photoFiles = glob(__DIR__ . '/../uploads/profile/user-' . (int) $_SESSION['user_id'] . '.*');
    if (!empty($photoFiles)) {
        $profilePhoto = '/uploads/profile/' . rawurlencode(basename($photoFiles[0]));
    }
}
?>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4" aria-label="Main navigation">
    <div class="container-fluid">
        <a class="navbar-brand" href="/dashboard.php">
            TrainTote Ops Manager
            <?php if ($currentNavHost === 'demo.traintote.com'): ?>
                <span class="tt-demo-badge" aria-label="Demo site">DEMO</span>
            <?php endif; ?>
        </a>

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
                <li class="nav-item d-flex align-items-center px-lg-2">
                    <label class="visually-hidden" for="tt-theme-select">Appearance</label>
                    <select id="tt-theme-select" class="form-select form-select-sm tt-theme-select" aria-label="Appearance">
                        <option value="system">System</option>
                        <option value="light">Light</option>
                        <option value="dark">Dark</option>
                    </select>
                </li>
                <li class="nav-item d-flex align-items-center">
                    <a class="nav-link p-0 ms-lg-2" href="<?= htmlspecialchars(tt_nav_ops_href('/profile.php', $currentNavHost), ENT_QUOTES, 'UTF-8') ?>" aria-label="Profile" title="Profile">
                        <?php if ($profilePhoto): ?>
                            <img src="<?= htmlspecialchars($profilePhoto, ENT_QUOTES, 'UTF-8') ?>" alt="" width="32" height="32" class="rounded-circle border border-secondary" style="object-fit: cover;">
                        <?php else: ?>
                            <span class="rounded-circle bg-secondary text-white d-inline-flex align-items-center justify-content-center" style="width: 32px; height: 32px;" aria-hidden="true">👤</span>
                        <?php endif; ?>
                        <span class="visually-hidden">Profile</span>
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>
