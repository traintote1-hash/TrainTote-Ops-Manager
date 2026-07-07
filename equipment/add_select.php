<?php

session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

?>

<?php include '../includes/header.php'; ?>

<title>Add Equipment</title>
<link rel="stylesheet" href="../assets/css/import_export.css">

</head>

<body>

<?php include '../includes/navbar.php'; ?>

<div class="container mt-5">

<div class="text-center mb-5">

<h1>Add Equipment</h1>

<p class="lead text-muted">

Choose how you'd like to add equipment.

</p>

</div>

<div class="row g-4">

<div class="col-md-6">

<div class="card h-100 shadow-sm border-primary">

<div class="card-body text-center p-5">

<h2 class="mb-3">

🤖 AI Scanner

</h2>

<p class="mb-4">

Upload a photo and let AI identify and fill in the equipment details automatically.

</p>

<span class="badge bg-success mb-3">

Recommended

</span>

<br>

<a
href="../ai/scan_equipment.php"
class="btn btn-primary btn-lg">

Start AI Scan

</a>

</div>

</div>

</div>

<div class="col-md-6">

<div class="card h-100 shadow-sm">

<div class="card-body text-center p-5">

<h2 class="mb-3">

✏️ Manual Entry

</h2>

<p class="mb-4">

Create an equipment record by entering the details yourself.

</p>

<a
href="add.php"
class="btn btn-success btn-lg">

Manual Entry

</a>

</div>

</div>

</div>

</div>


<div class="tt-import-export-page mt-4">

<section class="tt-import-hero">

<h2 class="h4 mb-0">JMRI Import / Export Center</h2>

<p>Import and export OperationsPro-style car and locomotive roster files for your TrainTote equipment roster.</p>

</section>

<div class="tt-import-grid">

<article class="tt-import-card">

<h3>Import JMRI Roster</h3>

<p class="text-muted">Preview car or locomotive roster rows before adding them to TrainTote.</p>

<a href="../import_export/jmri_import.php" class="btn btn-primary">Start Import</a>

</article>

<article class="tt-import-card">

<h3>Export JMRI CSV</h3>

<p class="text-muted">Download current TrainTote equipment in JMRI-compatible car or locomotive CSV format.</p>

<div class="tt-import-button-row">

<a href="../import_export/jmri_export.php?type=cars" class="btn btn-outline-primary">Export Cars</a>

<a href="../import_export/jmri_export.php?type=locomotives" class="btn btn-outline-primary">Export Locomotives</a>

</div>

</article>

<article class="tt-import-card">

<h3>Templates</h3>

<p class="text-muted">Download sample files with the expected JMRI column order.</p>

<div class="tt-import-button-row">

<a href="../import_export/templates.php?type=cars" class="btn btn-outline-secondary">Car Template</a>

<a href="../import_export/templates.php?type=locomotives" class="btn btn-outline-secondary">Locomotive Template</a>

</div>

</article>

</div>

</div>

<div class="text-center mt-4">

<a
href="list.php"
class="btn btn-secondary">

Back to Equipment

</a>

</div>

</div>

<?php include '../includes/footer.php'; ?>