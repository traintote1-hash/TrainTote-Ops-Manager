<?php

function mobileExpect($condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$root = dirname(__DIR__);
$project = dirname($root);
$sidebar = file_get_contents($project . '/assets/components/sidebar.php');
$shellCss = file_get_contents($project . '/assets/css/operations-shell.css');
$operationsCss = file_get_contents($project . '/assets/css/operations.css');
$navigationCss = file_get_contents($project . '/assets/css/tt-navigation.css');
$dashboard = file_get_contents($root . '/dashboard.php');
$settings = file_get_contents($root . '/settings.php');
$sessions = file_get_contents($root . '/sessions.php');
$switchLists = file_get_contents($root . '/switch_lists.php');
$workOrder = file_get_contents($root . '/work_order.php');
$history = file_get_contents($root . '/history.php');
$repairs = file_get_contents($root . '/repairs.php');
$dispatcherJs = file_get_contents($project . '/assets/js/dispatcher.js');
$fastClockWidget = file_get_contents($root . '/fast_clock_widget.php');

mobileExpect(strpos($sidebar, 'tt-operations-menu-toggle') !== false
    && strpos($sidebar, 'aria-expanded="false"') !== false
    && strpos($sidebar, 'aria-controls="ttOperationsMenu"') !== false,
    'Operations navigation must expose an accessible compact mobile control.');
mobileExpect(strpos($shellCss, '@media (max-width: 900px)') !== false
    && strpos($shellCss, '.tt-module-nav-list.is-open') !== false
    && strpos($shellCss, 'min-height: 44px') !== false,
    'The shared Operations shell must collapse its sidebar into touch-sized mobile navigation.');
mobileExpect(strpos($dashboard, 'operations-shell.css') !== false,
    'The Operations dashboard must use the shared responsive navigation shell.');
mobileExpect(strpos($navigationCss, '.navbar-brand') !== false
    && strpos($navigationCss, 'white-space: normal') !== false,
    'The global navbar must wrap safely on narrow screens.');
mobileExpect(strpos($settings, 'tt-module-setting') !== false
    && strpos($settings, 'tt-module-setting-copy') !== false,
    'Module toggles must stay beside their labels on phones.');
foreach ([$sessions, $switchLists, $workOrder, $history, $repairs] as $markup) {
    mobileExpect(strpos($markup, 'tt-mobile-cards') !== false
        && strpos($markup, 'data-label=') !== false,
        'Primary Operations tables must use labeled mobile cards.');
}
mobileExpect(strpos($workOrder, 'data-label="Move / Exception"') !== false
    && strpos($workOrder, '>Not Moved</option>') !== false
    && strpos($workOrder, 'tt-closeout-controls') !== false
    && strpos($operationsCss, 'position:sticky') !== false,
    'Work-order exceptions and final completion must remain distinct and prominent on phones.');
mobileExpect(strpos($operationsCss, 'width:1.75rem;height:1.75rem') !== false
    && strpos($operationsCss, 'min-height:52px') !== false,
    'Crew controls must provide large one-handed tap targets.');
mobileExpect(strpos($fastClockWidget, 'data-fast-clock-time') !== false
    && strpos($operationsCss, '.tt-fast-clock-controls') !== false,
    'Fast Clock markup and controls must retain responsive shared styling.');
mobileExpect(strpos($dispatcherJs, 'document.hidden') !== false
    && strpos($dispatcherJs, 'stopped=!data.polling') !== false,
    'Mobile changes must preserve visibility-aware and disabled polling suppression.');
mobileExpect(strpos($operationsCss, '@media print') !== false
    && strpos($shellCss, '@media print') !== false,
    'Responsive work must preserve dedicated print rules.');

echo "mobile_responsive_test: OK\n";
