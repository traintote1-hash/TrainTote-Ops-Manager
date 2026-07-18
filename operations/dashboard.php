<?php
session_start();
require_once '../config/database.php';
require_once 'lib.php';
if (!isset($_SESSION['user_id'])) { header('Location: ../login.php'); exit; }
$userId = (int)$_SESSION['user_id'];
$railroad = ttOperationsRailroad($pdo, $userId);
$railroadId = (int)$railroad['id'];
$modules = ttOperationsModuleStates($pdo, $railroadId);
$isOwner = ttOperationsIsRailroadOwner($pdo, $railroadId, $userId);
$pageTitle = 'Operations Center';
include '../assets/components/header.php';
?>
<link rel="stylesheet" href="../assets/css/dashboard.css">
<link rel="stylesheet" href="../assets/css/operations-shell.css">
<?php include '../assets/components/sidebar.php'; ?>
<main class="tt-content"><div class="tt-dashboard-page">
<div class="tt-hero"><div class="tt-hero-main"><div><span class="tt-hero-kicker">Operations</span><h1>Operations Center</h1><p><?=ttHtml($railroad['name'])?></p></div></div><?php if ($isOwner): ?><a class="btn btn-sm btn-outline-light" href="/operations/settings.php">Operations Settings</a><?php endif; ?></div>

<div class="tt-section-header"><div><span class="tt-panel-kicker">Core workflow</span><h2>Run an Operating Session</h2></div></div>
<div class="tt-actions">
<a class="tt-action" href="/operations/sessions.php"><span class="tt-action-copy"><span>Sessions</span><small>Build, start, and manage operating sessions</small></span></a>
<a class="tt-action" href="/operations/switch_lists.php"><span class="tt-action-copy"><span>Switch Lists / Work Orders</span><small>Run and complete assigned work</small></span></a>
<a class="tt-action" href="/operations/history.php"><span class="tt-action-copy"><span>Session History</span><small>Review completed and cancelled sessions</small></span></a>
</div>

<?php if (array_filter($modules)): ?>
<div class="tt-section-header mt-4"><div><span class="tt-panel-kicker">Enabled modules</span><h2>Operations Tools</h2></div></div>
<div class="tt-dashboard-lower">
<?php if (!empty($modules['fast_clock'])): ?><a class="tt-panel text-decoration-none" href="/operations/sessions.php"><h3 class="h5">Fast Clock</h3><p class="tt-muted-text mb-0">Configure model time inside a session.</p></a><?php endif; ?>
<?php if (!empty($modules['dispatcher'])): ?><a class="tt-panel text-decoration-none" href="/operations/dispatcher.php"><h3 class="h5">Dispatcher</h3><p class="tt-muted-text mb-0">Open the live assignment overview.</p></a><?php endif; ?>
<?php if (!empty($modules['repair_queue'])): ?><a class="tt-panel text-decoration-none" href="/operations/repairs.php"><h3 class="h5">Repair Queue</h3><p class="tt-muted-text mb-0">Manage Bad Order equipment.</p></a><?php endif; ?>
<?php if (!empty($modules['crew_messaging'])): ?><a class="tt-panel text-decoration-none" href="/operations/switch_lists.php"><h3 class="h5">Crew Messaging</h3><p class="tt-muted-text mb-0">Review crew-facing work-order messages.</p></a><?php endif; ?>
<?php if (!empty($modules['advanced_roles'])): ?><a class="tt-panel text-decoration-none" href="/jobs/list.php"><h3 class="h5">Advanced Operations</h3><p class="tt-muted-text mb-0">Prepared cuts, load status, and role-based tools.</p></a><?php endif; ?>
</div>
<?php endif; ?>
</div></main>
<?php include '../assets/components/footer.php'; ?>
