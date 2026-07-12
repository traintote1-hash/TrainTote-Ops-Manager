<?php
session_start();
require_once '../config/database.php';
require_once '../operations/lib.php';
if (!isset($_SESSION['user_id'])) { header('Location: ../login.php'); exit; }
$railroad = ttOperationsRailroad($pdo, (int)$_SESSION['user_id']);
$types = ttJobTypes();
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        ttOperationsRequireCsrf();
        $name = substr(trim((string)($_POST['job_name'] ?? '')), 0, 120);
        $type = (string)($_POST['job_type'] ?? 'local_turn');
        $description = substr(trim((string)($_POST['description'] ?? '')), 0, 5000);
        if ($name === '' || !isset($types[$type])) { throw new RuntimeException('Enter a name and select a valid operating pattern.'); }
        $stmt = $pdo->prepare('INSERT INTO jobs (railroad_id, job_name, job_type, custom_job_type, home_industry_id, description, active) VALUES (?, ?, ?, ?, NULL, ?, ?)');
        $stmt->execute([(int)$railroad['id'], $name, $type, '', $description, ($_POST['active'] ?? '1') === '1' ? 1 : 0]);
        header('Location: list.php'); exit;
    } catch (Throwable $e) { $error = $e->getMessage(); }
}
?>
<?php include '../includes/header.php'; ?><title>Add Job Title</title></head><body>
<?php include '../includes/navbar.php'; ?>
<div class="container mt-4" style="max-width:800px"><p class="text-uppercase text-muted small mb-1">Operations</p><h1>Add Job Title</h1>
<p class="text-muted">A reusable title and default operating pattern. Actual crews, locomotives, locations, and cars are selected per assignment.</p>
<?php if ($error): ?><div class="alert alert-danger"><?= ttHtml($error) ?></div><?php endif; ?>
<form method="post"><input type="hidden" name="csrf_token" value="<?= ttHtml(ttOperationsCsrfToken()) ?>">
<div class="mb-3"><label class="form-label">Job Name</label><input class="form-control" name="job_name" maxlength="120" required></div>
<div class="mb-3"><label class="form-label">Default Operating Pattern</label><select class="form-select" name="job_type"><?php foreach($types as $value=>$label): ?><option value="<?= ttHtml($value) ?>"><?= ttHtml($label) ?></option><?php endforeach; ?></select></div>
<div class="mb-3"><label class="form-label">Status</label><select class="form-select" name="active"><option value="1">Active</option><option value="0">Inactive</option></select></div>
<div class="mb-3"><label class="form-label">Description</label><textarea class="form-control" name="description" rows="5" maxlength="5000"></textarea></div>
<button class="btn btn-success">Save Job Title</button> <a class="btn btn-secondary" href="list.php">Cancel</a></form></div>
<?php include '../includes/footer.php'; ?>
