<?php
$currentOperationsPage =
    $_SERVER['PHP_SELF']
    ?? '';

if (!function_exists('ttOperationsNavActive')) {
    function ttOperationsNavActive(string $page, string $currentOperationsPage): string
    {
        if (strpos($currentOperationsPage, $page) !== false) {
            return 'active';
        }

        return '';
    }
}
?>

<aside class="tt-sidebar tt-module-nav" aria-label="Operations navigation">

    <div class="tt-module-nav-header">
        <h3>Operations</h3>
    </div>

    <nav class="tt-module-nav-list">

        <a
        class="<?= ttOperationsNavActive('/operations/dashboard.php', $currentOperationsPage); ?>"
        href="/operations/dashboard.php">
        Dashboard
        </a>

        <a
        class="<?= ttOperationsNavActive('/operations/session', $currentOperationsPage); ?>"
        href="/operations/sessions.php">
        Build / Start Session
        </a>

        <a
        class="<?= ttOperationsNavActive('/operations/sessions.php', $currentOperationsPage); ?>"
        href="/operations/sessions.php">
        Active Sessions
        </a>

        <a
        href="/operations/switch_lists.php">
        Switch Lists / Work Orders
        </a>

        <a
        href="/operations/prepared_cuts.php">
        Prepared Cuts
        </a>

        <a
        href="/jobs/list.php">
        Job Titles
        </a>

        <a
        href="/operations/sessions.php?status=completed">
        Session History
        </a>

    </nav>

</aside>

<main class="tt-content">
