<?php

if (!isset($pageTitle)) {
    $pageTitle = 'TrainTote';
}

?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta
name="viewport"
content="width=device-width,initial-scale=1">
<title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>

<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">

<link
href="/assets/css/traintote.css"
rel="stylesheet">

<link
href="/assets/css/tt-shell.css"
rel="stylesheet">

<link
href="/assets/css/tt-navigation.css"
rel="stylesheet">

</head>
<body>

<?php
require_once __DIR__ . '/../../includes/navbar.php';
?>

<div class="tt-shell">
