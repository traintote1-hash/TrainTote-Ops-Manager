<?php
if(!isset($pageTitle)) $pageTitle='TrainTote';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= htmlspecialchars($pageTitle) ?></title>
<link rel="stylesheet" href="/assets/css/tt-shell.css">
</head>
<body>
<header class="tt-header">
  <div class="tt-brand">🚂 TrainTote</div>
  <nav class="tt-topnav">
    <a href="/operations/dashboard.php">Operations Center</a>
    <a href="/equipment/list.php">Equipment</a>
    <a href="/industries/list.php">Industries</a>
    <a href="/waybills/list.php">Waybills</a>
    <a href="/jobs/list.php">Jobs</a>
  </nav>
</header>
<div class="tt-shell">
