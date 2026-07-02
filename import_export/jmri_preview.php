<?php
session_start();
require_once '../config/database.php';
require_once '../includes/jmri_import_helpers.php';
$railroad = ttJmriGetRailroad($pdo);
$error = '';
$rows = [];
$importType = $_POST['import_type'] ?? '';
$duplicateBehavior = $_POST['duplicate_behavior'] ?? 'skip';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: jmri_import.php');
    exit;
}

if (!in_array($importType, ['cars', 'locomotives'], true)) {
    $error = 'Choose cars or locomotives.';
}
elseif (!in_array($duplicateBehavior, ['skip', 'update', 'add'], true)) {
    $error = 'Choose a valid duplicate behavior.';
}
else {
    try {
        $rawRows = ttJmriReadImportRows($_FILES['jmri_file'] ?? []);
        $industryLookup = ttJmriLoadIndustries($pdo, (int)$railroad['id']);

        foreach ($rawRows as $index => $rawRow) {
            $temporaryRow = ttJmriMapRow($rawRow, $importType, $index + 1, $industryLookup);
            $duplicateId = null;

            if ($temporaryRow['reporting_marks'] !== '' && $temporaryRow['road_number'] !== '') {
                $duplicateId = ttJmriFindDuplicate(
                    $pdo,
                    (int)$railroad['id'],
                    $temporaryRow['reporting_marks'],
                    $temporaryRow['road_number'],
                    $temporaryRow['equipment_class']
                );
            }

            $rows[] = ttJmriMapRow($rawRow, $importType, $index + 1, $industryLookup, $duplicateId);
        }

        $_SESSION['jmri_import'] = [
            'import_type' => $importType,
            'duplicate_behavior' => $duplicateBehavior,
            'rows' => $rows,
            'created_at' => time()
        ];
    }
    catch (RuntimeException $exception) {
        $error = $exception->getMessage();
    }
}
?>
<?php include '../includes/header.php'; ?>
<title>Preview JMRI Import</title>
<link rel="stylesheet" href="../assets/css/import_export.css">
</head>
<body>
<?php include '../includes/navbar.php'; ?>
<div class="container-fluid mt-4 mb-5 tt-import-export-page">
    <section class="tt-import-hero">
        <h1>Preview JMRI Import</h1>
        <p>Review parsed rows before importing them into TrainTote equipment.</p>
    </section>

    <?php if ($error !== ''): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <div class="tt-import-actions">
        <a href="jmri_import.php" class="btn btn-primary">Try Again</a>
        <a href="index.php" class="btn btn-secondary">Back to Import / Export</a>
    </div>
    <?php else: ?>
    <form method="post" action="jmri_commit.php" class="tt-import-card">
        <div class="tt-import-actions mb-2">
            <button type="submit" class="btn btn-success">Import Selected Rows</button>
            <a href="jmri_import.php" class="btn btn-secondary">Start Over</a>
        </div>

        <div class="table-responsive">
            <table class="table table-sm table-striped align-middle tt-preview-table">
                <thead>
                    <tr>
                        <th><input type="checkbox" id="selectAllRows" checked></th>
                        <th>Row</th>
                        <th>Road / Marks</th>
                        <th>Number</th>
                        <th>Type / Model</th>
                        <th>Length</th>
                        <?php if ($importType === 'cars'): ?><th>Color</th><?php endif; ?>
                        <th>Location</th>
                        <th>Track</th>
                        <th>Status</th>
                        <th>Messages</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $index => $row): ?>
                    <?php $valid = $row['status'] !== 'error'; ?>
                    <tr>
                        <td>
                            <?php if ($valid): ?>
                            <input type="checkbox" class="row-check" name="selected_rows[]" value="<?php echo (int)$index; ?>" checked>
                            <?php endif; ?>
                        </td>
                        <td><?php echo (int)$row['row_number']; ?></td>
                        <td><?php echo htmlspecialchars($row['reporting_marks']); ?><br><small><?php echo htmlspecialchars($row['road_name']); ?></small></td>
                        <td><?php echo htmlspecialchars($row['road_number']); ?></td>
                        <td><?php echo htmlspecialchars($row['equipment_class'] === 'Locomotive' ? $row['prototype'] : $row['equipment_type']); ?></td>
                        <td><?php echo htmlspecialchars($row['length_ft']); ?></td>
                        <?php if ($importType === 'cars'): ?><td><?php echo htmlspecialchars($row['color']); ?></td><?php endif; ?>
                        <td><?php echo htmlspecialchars($row['location']); ?></td>
                        <td><?php echo htmlspecialchars($row['current_track']); ?></td>
                        <td class="tt-status-<?php echo htmlspecialchars($row['status']); ?>"><?php echo htmlspecialchars(ucfirst($row['status'])); ?></td>
                        <td><?php echo htmlspecialchars(implode('; ', $row['messages'])); ?><?php if ($row['duplicate_id']): ?>Existing equipment #<?php echo (int)$row['duplicate_id']; ?><?php endif; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </form>

    <script>
    document.getElementById('selectAllRows')?.addEventListener('change', function() {
        document.querySelectorAll('.row-check').forEach((checkbox) => checkbox.checked = this.checked);
    });
    </script>
    <?php endif; ?>
</div>
<?php include '../includes/footer.php'; ?>
