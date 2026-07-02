<?php
session_start();
require_once '../config/database.php';
require_once '../includes/jmri_import_helpers.php';
$railroad = ttJmriGetRailroad($pdo);
?>
<?php include '../includes/header.php'; ?>
<title>JMRI Import / Export Center</title>
<link rel="stylesheet" href="../assets/css/import_export.css">
</head>
<body>
<?php include '../includes/navbar.php'; ?>
<div class="container mt-4 mb-5 tt-import-export-page">
    <section class="tt-import-hero">
        <h1>JMRI Import / Export Center</h1>
        <p>Import and export OperationsPro-style car and locomotive roster files for your TrainTote equipment roster.</p>
    </section>

    <div class="tt-import-grid">
        <article class="tt-import-card">
            <h2>Import JMRI Roster</h2>
            <p class="text-muted">Preview car or locomotive roster rows before adding them to TrainTote.</p>
            <a href="jmri_import.php" class="btn btn-primary">Start Import</a>
        </article>

        <article class="tt-import-card">
            <h2>Export JMRI CSV</h2>
            <p class="text-muted">Download current TrainTote equipment in JMRI-compatible car or locomotive CSV format.</p>
            <div class="tt-import-button-row">
                <a href="jmri_export.php?type=cars" class="btn btn-outline-primary">Export Cars</a>
                <a href="jmri_export.php?type=locomotives" class="btn btn-outline-primary">Export Locomotives</a>
            </div>
        </article>

        <article class="tt-import-card">
            <h2>Templates</h2>
            <p class="text-muted">Download sample files with the expected JMRI column order.</p>
            <div class="tt-import-button-row">
                <a href="templates.php?type=cars" class="btn btn-outline-secondary">Car Template</a>
                <a href="templates.php?type=locomotives" class="btn btn-outline-secondary">Locomotive Template</a>
            </div>
        </article>
    </div>

    <div class="tt-import-actions">
        <a href="../equipment/list.php" class="btn btn-secondary">Back to Equipment</a>
        <a href="../dashboard.php" class="btn btn-outline-secondary">Back to Dashboard</a>
    </div>
</div>
<?php include '../includes/footer.php'; ?>
