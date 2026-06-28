<?php
if (!isset($pageTitle)) {
    $pageTitle = 'TrainTote';
}

$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';

function tt_nav_active(string $href, string $currentPath): string
{
    return strpos($currentPath, $href) === 0 ? ' class="active"' : '';
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
<link rel="stylesheet" href="/assets/css/tt-variables.css">
<link rel="stylesheet" href="/assets/css/tt-reset.css">
<link rel="stylesheet" href="/assets/css/tt-typography.css">
<link rel="stylesheet" href="/assets/css/tt-shell.css">
<link rel="stylesheet" href="/assets/css/tt-layout.css">
<link rel="stylesheet" href="/assets/css/tt-navigation.css">
<link rel="stylesheet" href="/assets/css/tt-toolbar.css">
<link rel="stylesheet" href="/assets/css/tt-buttons.css">
<link rel="stylesheet" href="/assets/css/tt-cards.css">
<link rel="stylesheet" href="/assets/css/tt-stat-tiles.css">
<link rel="stylesheet" href="/assets/css/tt-empty-state.css">
<link rel="stylesheet" href="/assets/css/tt-utilities.css">
</head>
<body>
<header class="tt-header">
  <a class="tt-brand" href="/dashboard.php">🚂 TrainTote</a>
  <nav class="tt-topnav" aria-label="Primary navigation">
    <a href="/operations/dashboard.php"<?= tt_nav_active('/operations', $currentPath) ?>>Operations Center</a>
    <a href="/equipment/list.php"<?= tt_nav_active('/equipment', $currentPath) ?>>Equipment</a>
    <a href="/industries/list.php"<?= tt_nav_active('/industries', $currentPath) ?>>Industries</a>
    <a href="/waybills/list.php"<?= tt_nav_active('/waybills', $currentPath) ?>>Waybills</a>
    <a href="/jobs/list.php"<?= tt_nav_active('/jobs', $currentPath) ?>>Jobs</a>
  </nav>
</header>
<div class="tt-shell">
