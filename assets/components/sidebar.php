<?php
$currentOperationsPage =
    $_SERVER['PHP_SELF']
    ?? '';

$operationsNavItem = 'dashboard';

if (
    strpos($currentOperationsPage, '/operations/history.php') !== false
    || strpos($currentOperationsPage, '/operations/history_view.php') !== false
) {
    $operationsNavItem = 'history';
} elseif (strpos($currentOperationsPage, '/operations/dispatcher.php') !== false) {
    $operationsNavItem = 'dispatcher';
} elseif ($currentOperationsPage === '/operations/sessions.php') {
    $operationsNavItem = 'sessions';
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
    strpos($currentOperationsPage, '/operations/repairs.php') !== false
    || strpos($currentOperationsPage, '/operations/repair.php') !== false
) {
    $operationsNavItem = 'repairs';
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

        <?php $showDispatcher=isset($pdo,$_SESSION['user_id'])&&function_exists('ttDispatcherNavEnabled')&&ttDispatcherNavEnabled($pdo,(int)$_SESSION['user_id']);if($showDispatcher): ?>
        <a
        class="<?= ttOperationsNavActive('dispatcher', $operationsNavItem); ?>"
        <?= $operationsNavItem === 'dispatcher' ? 'aria-current="page"' : ''; ?>
        href="/operations/dispatcher.php">
        Dispatcher
        </a>
        <?php endif; ?>

        <a
        class="<?= ttOperationsNavActive('load_status', $operationsNavItem); ?>"
        <?= $operationsNavItem === 'load_status' ? 'aria-current="page"' : ''; ?>
        href="/operations/load_status.php">
        Review Load Status
        </a>

        <a
        class="<?= ttOperationsNavActive('repairs', $operationsNavItem); ?>"
        <?= $operationsNavItem === 'repairs' ? 'aria-current="page"' : ''; ?>
        href="/operations/repairs.php">
        Repair Queue
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
        href="/operations/history.php">
        Session History
        </a>

    </nav>

</aside>
