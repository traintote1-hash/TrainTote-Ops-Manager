<?php
$currentOperationsPage =
    $_SERVER['PHP_SELF']
    ?? '';

$isSessionHistory =
    $currentOperationsPage === '/operations/sessions.php'
    && ($_GET['status'] ?? '') === 'completed';

$operationsNavItem = 'dashboard';

if ($currentOperationsPage === '/operations/sessions.php') {
    $operationsNavItem = $isSessionHistory ? 'history' : 'sessions';
} elseif (
    strpos($currentOperationsPage, '/operations/session_edit.php') !== false
    || strpos($currentOperationsPage, '/operations/generate.php') !== false
) {
    $operationsNavItem = 'build';
} elseif (
    strpos($currentOperationsPage, '/operations/switch_lists.php') !== false
    || strpos($currentOperationsPage, '/operations/work_order.php') !== false
    || strpos($currentOperationsPage, '/operations/complete_job.php') !== false
) {
    $operationsNavItem = 'switch_lists';
} elseif (strpos($currentOperationsPage, '/operations/load_status.php') !== false) {
    $operationsNavItem = 'load_status';
} elseif (
    strpos($currentOperationsPage, '/operations/prepared_cuts.php') !== false
    || strpos($currentOperationsPage, '/operations/prepared_cut.php') !== false
) {
    $operationsNavItem = 'prepared_cuts';
} elseif (strpos($currentOperationsPage, '/jobs/') !== false) {
    $operationsNavItem = 'job_titles';
}

if (!function_exists('ttOperationsNavActive')) {
    function ttOperationsNavActive(string $item, string $currentItem): string
    {
        return $item === $currentItem ? 'active' : '';
    }
}
?>

<aside class="tt-sidebar tt-module-nav" aria-label="Operations navigation">

    <div class="tt-module-nav-header">
        <h3>Operations</h3>
    </div>

    <nav class="tt-module-nav-list">

        <a
        class="<?= ttOperationsNavActive('dashboard', $operationsNavItem); ?>"
        <?= $operationsNavItem === 'dashboard' ? 'aria-current="page"' : ''; ?>
        href="/operations/dashboard.php">
        Dashboard
        </a>

        <a
        class="<?= ttOperationsNavActive('build', $operationsNavItem); ?>"
        <?= $operationsNavItem === 'build' ? 'aria-current="page"' : ''; ?>
        href="/operations/sessions.php">
        Build / Start Session
        </a>

        <a
        class="<?= ttOperationsNavActive('sessions', $operationsNavItem); ?>"
        <?= $operationsNavItem === 'sessions' ? 'aria-current="page"' : ''; ?>
        href="/operations/sessions.php">
        Active Sessions
        </a>

        <a
        class="<?= ttOperationsNavActive('switch_lists', $operationsNavItem); ?>"
        <?= $operationsNavItem === 'switch_lists' ? 'aria-current="page"' : ''; ?>
        href="/operations/switch_lists.php">
        Switch Lists / Work Orders
        </a>

        <a
        class="<?= ttOperationsNavActive('load_status', $operationsNavItem); ?>"
        <?= $operationsNavItem === 'load_status' ? 'aria-current="page"' : ''; ?>
        href="/operations/load_status.php">
        Review Load Status
        </a>

        <a
        class="<?= ttOperationsNavActive('prepared_cuts', $operationsNavItem); ?>"
        <?= $operationsNavItem === 'prepared_cuts' ? 'aria-current="page"' : ''; ?>
        href="/operations/prepared_cuts.php">
        Prepared Cuts
        </a>

        <a
        class="<?= ttOperationsNavActive('job_titles', $operationsNavItem); ?>"
        <?= $operationsNavItem === 'job_titles' ? 'aria-current="page"' : ''; ?>
        href="/jobs/list.php">
        Job Titles
        </a>

        <a
        class="<?= ttOperationsNavActive('history', $operationsNavItem); ?>"
        <?= $operationsNavItem === 'history' ? 'aria-current="page"' : ''; ?>
        href="/operations/sessions.php?status=completed">
        Session History
        </a>

    </nav>

</aside>
