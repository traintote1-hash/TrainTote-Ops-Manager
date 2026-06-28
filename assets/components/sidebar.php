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
        class="<?= ttOperationsNavActive('/operations/generate.php', $currentOperationsPage); ?>"
        href="/operations/generate.php">
        Generate Session
        </a>

        <a
        class="<?= ttOperationsNavActive('/operations/switch_list.php', $currentOperationsPage); ?>"
        href="/operations/switch_list.php">
        Switch Lists
        </a>

        <a
        href="#">
        Session
        </a>

        <a
        href="#">
        Crew
        </a>

        <a
        href="#">
        Dispatcher
        </a>

        <a
        href="#">
        Repairs
        </a>

    </nav>

</aside>

<main class="tt-content">
