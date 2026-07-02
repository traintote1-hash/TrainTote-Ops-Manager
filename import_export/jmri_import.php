<?php
session_start();
require_once '../config/database.php';
require_once '../includes/jmri_import_helpers.php';
$railroad = ttJmriGetRailroad($pdo);
?>
<?php include '../includes/header.php'; ?>
<title>Import JMRI Roster</title>
<link rel="stylesheet" href="../assets/css/import_export.css">
</head>
<body>
<?php include '../includes/navbar.php'; ?>
<div class="container mt-4 mb-5 tt-import-export-page">
    <section class="tt-import-hero">
        <h1>Import JMRI Roster</h1>
        <p>Upload an OperationsPro-style car or locomotive roster file, then review rows before committing them.</p>
    </section>

    <section class="tt-import-card">
        <form method="post" action="jmri_preview.php" enctype="multipart/form-data">
            <div class="mb-3">
                <label class="form-label">Import Type</label>
                <select name="import_type" class="form-select" required>
                    <option value="cars">Import cars</option>
                    <option value="locomotives">Import locomotives</option>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label">Duplicate Behavior</label>
                <select name="duplicate_behavior" class="form-select" required>
                    <option value="skip">Skip existing</option>
                    <option value="update">Update existing</option>
                    <option value="add">Add anyway</option>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label">JMRI file</label>
                <input type="file" name="jmri_file" class="form-control" accept=".csv,.txt,text/csv,text/plain" required>
                <div class="form-text">CSV files and files whose first line is exactly comma are parsed as comma-delimited. TXT files are parsed as basic space-delimited JMRI rows.</div>
            </div>

            <div class="tt-import-actions">
                <button type="submit" class="btn btn-primary">Preview Import</button>
                <a href="index.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </section>
</div>
<?php include '../includes/footer.php'; ?>
