<?php
session_start();
require_once '../config/database.php';
require_once '../includes/jmri_import_helpers.php';
$railroad = ttJmriGetRailroad($pdo);
$import = $_SESSION['jmri_import'] ?? null;
$selectedRows = array_map('intval', (array)($_POST['selected_rows'] ?? []));
$created = 0;
$updated = 0;
$skipped = 0;
$errors = [];

if (!$import || empty($import['rows']) || !is_array($import['rows'])) {
    $errors[] = 'No JMRI import preview is available. Please upload the file again.';
}
else {
    $duplicateBehavior = $import['duplicate_behavior'] ?? 'skip';

    foreach ($selectedRows as $rowIndex) {
        if (!isset($import['rows'][$rowIndex])) {
            continue;
        }

        $row = $import['rows'][$rowIndex];

        if (($row['status'] ?? '') === 'error') {
            $skipped++;
            continue;
        }

        $duplicateId = $row['duplicate_id'] ?? null;

        if ($duplicateId && $duplicateBehavior === 'skip') {
            $skipped++;
            continue;
        }

        try {
            if ($duplicateId && $duplicateBehavior === 'update') {
                ttJmriUpdateEquipment($pdo, (int)$duplicateId, $row);
                $updated++;
            }
            else {
                ttJmriInsertEquipment($pdo, (int)$railroad['id'], $row);
                $created++;
            }
        }
        catch (Throwable $exception) {
            $errors[] = 'Row ' . (int)$row['row_number'] . ': ' . $exception->getMessage();
        }
    }

    unset($_SESSION['jmri_import']);
}
?>
<?php include '../includes/header.php'; ?>
<title>JMRI Import Complete</title>
<link rel="stylesheet" href="../assets/css/import_export.css">
</head>
<body>
<?php include '../includes/navbar.php'; ?>
<div class="container mt-4 mb-5 tt-import-export-page">
    <section class="tt-import-hero">
        <h1>JMRI Import Complete</h1>
        <p>TrainTote finished processing the selected JMRI roster rows.</p>
    </section>

    <section class="tt-import-card">
        <?php if (!empty($errors)): ?>
        <div class="alert alert-warning">
            <strong>Some rows were not imported.</strong>
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?><li><?php echo htmlspecialchars($error); ?></li><?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <div class="row g-3">
            <div class="col-md-4"><div class="alert alert-success mb-0"><strong><?php echo $created; ?></strong> created</div></div>
            <div class="col-md-4"><div class="alert alert-info mb-0"><strong><?php echo $updated; ?></strong> updated</div></div>
            <div class="col-md-4"><div class="alert alert-secondary mb-0"><strong><?php echo $skipped; ?></strong> skipped</div></div>
        </div>

        <div class="tt-import-actions mt-3">
            <a href="../equipment/list.php" class="btn btn-primary">Back to Equipment</a>
            <a href="jmri_import.php" class="btn btn-outline-primary">Import Another File</a>
            <a href="index.php" class="btn btn-secondary">Import / Export Center</a>
        </div>
    </section>
</div>
<?php include '../includes/footer.php'; ?>
