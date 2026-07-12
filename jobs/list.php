<?php
session_start();
require_once '../config/database.php';
require_once '../operations/lib.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$railroad = ttOperationsRailroad($pdo, (int)$_SESSION['user_id']);
$railroadId = (int)$railroad['id'];
$types = ttJobTypes();
$error = '';
$message = is_string($_SESSION['job_title_message'] ?? null) ? $_SESSION['job_title_message'] : '';
unset($_SESSION['job_title_message']);

$editId = (int)($_GET['edit'] ?? $_POST['id'] ?? 0);
$editing = null;
if ($editId > 0) {
    $editStmt = $pdo->prepare('SELECT * FROM jobs WHERE id=? AND railroad_id=?');
    $editStmt->execute([$editId, $railroadId]);
    $editing = $editStmt->fetch(PDO::FETCH_ASSOC);
    if (!$editing) {
        $error = 'Job Title not found.';
        $editId = 0;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        ttOperationsRequireCsrf();
        $name = substr(trim((string)($_POST['job_name'] ?? '')), 0, 120);
        $type = (string)($_POST['job_type'] ?? 'local_turn');
        $description = substr(trim((string)($_POST['description'] ?? '')), 0, 5000);
        $active = ($_POST['active'] ?? '1') === '1' ? 1 : 0;
        if ($name === '') {
            throw new RuntimeException('Job Title name is required.');
        }
        if (!isset($types[$type])) {
            throw new RuntimeException('Select a valid default operating pattern.');
        }

        if ($editId > 0) {
            $stmt = $pdo->prepare('UPDATE jobs SET job_name=?,job_type=?,custom_job_type=?,description=?,active=? WHERE id=? AND railroad_id=?');
            $stmt->execute([$name, $type, '', $description, $active, $editId, $railroadId]);
            if (!$stmt->rowCount() && !$editing) {
                throw new RuntimeException('Job Title not found.');
            }
            $_SESSION['job_title_message'] = 'Job Title updated.';
        } else {
            $stmt = $pdo->prepare('INSERT INTO jobs (railroad_id,job_name,job_type,custom_job_type,home_industry_id,description,active) VALUES (?,?,?,?,NULL,?,?)');
            $stmt->execute([$railroadId, $name, $type, '', $description, $active]);
            $_SESSION['job_title_message'] = 'Job Title created.';
        }
        header('Location: list.php');
        exit;
    } catch (Throwable $e) {
        $error = $e->getMessage();
        $editing = [
            'id' => $editId,
            'job_name' => $_POST['job_name'] ?? '',
            'job_type' => $_POST['job_type'] ?? 'local_turn',
            'description' => $_POST['description'] ?? '',
            'active' => ($_POST['active'] ?? '1') === '1'
        ];
    }
}

function jobTitleTableExists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?');
    $stmt->execute([$table]);
    return (int)$stmt->fetchColumn() > 0;
}

$locationCountSql = jobTitleTableExists($pdo, 'job_industries')
    ? '(SELECT COUNT(*) FROM job_industries ji WHERE ji.job_id=j.id)'
    : 'NULL';
$carCountSql = jobTitleTableExists($pdo, 'job_cars')
    ? '(SELECT COUNT(*) FROM job_cars jc WHERE jc.job_id=j.id)'
    : 'NULL';

$search = substr(trim((string)($_GET['search'] ?? '')), 0, 120);
$params = [$railroadId];
$where = 'j.railroad_id=?';
if ($search !== '') {
    $where .= ' AND (j.job_name LIKE ? OR j.job_type LIKE ? OR j.description LIKE ?)';
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like);
}
$stmt = $pdo->prepare("SELECT j.*,i.industry_name home_location,$locationCountSql associated_location_count,$carCountSql associated_car_count FROM jobs j LEFT JOIN industries i ON i.id=j.home_industry_id AND i.railroad_id=j.railroad_id WHERE $where ORDER BY j.active DESC,j.job_name");
$stmt->execute($params);
$jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<?php include '../includes/header.php'; ?>
<title>Operations Job Titles</title>
<link rel="stylesheet" href="../assets/css/list_v2.css">
</head><body>
<?php include '../includes/navbar.php'; ?>
<div class="tt-operations-shell">
<?php include '../assets/components/sidebar.php'; ?>
<section class="tt-ops-page">
    <div class="mb-4"><p class="tt-eyebrow">Operations</p><h1>Job Titles</h1><p class="text-muted">Reusable operating templates. Crews, locomotives, cars, locations, and live work are assigned separately for each operating session.</p></div>
    <?php if ($message): ?><div class="alert alert-success"><?= ttHtml($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= ttHtml($error) ?></div><?php endif; ?>

    <div class="card mb-4" id="job-title-form"><div class="card-header"><h2 class="h4 mb-0"><?= $editing ? 'Edit Job Title' : 'Create Job Title' ?></h2></div><div class="card-body">
        <form method="post" class="row g-3">
            <input type="hidden" name="csrf_token" value="<?= ttHtml(ttOperationsCsrfToken()) ?>">
            <input type="hidden" name="id" value="<?= (int)($editing['id'] ?? 0) ?>">
            <div class="col-md-6"><label class="form-label">Job Title</label><input class="form-control" name="job_name" maxlength="120" required value="<?= ttHtml($editing['job_name'] ?? '') ?>"></div>
            <div class="col-md-6"><label class="form-label">Default Operating Pattern</label><select class="form-select" name="job_type"><?php foreach ($types as $value => $label): ?><option value="<?= ttHtml($value) ?>" <?= ($editing['job_type'] ?? '') === $value ? 'selected' : '' ?>><?= ttHtml($label) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-3"><label class="form-label">Template Status</label><select class="form-select" name="active"><option value="1" <?= !isset($editing['active']) || $editing['active'] ? 'selected' : '' ?>>Active</option><option value="0" <?= isset($editing['active']) && !$editing['active'] ? 'selected' : '' ?>>Inactive</option></select></div>
            <div class="col-md-9"><label class="form-label">Template Description</label><textarea class="form-control" name="description" rows="3" maxlength="5000"><?= ttHtml($editing['description'] ?? '') ?></textarea></div>
            <div><button class="btn btn-success"><?= $editing ? 'Save Job Title' : 'Create Job Title' ?></button><?php if ($editing): ?> <a class="btn btn-secondary" href="list.php#job-title-form">Cancel Edit</a><?php endif; ?></div>
        </form>
    </div></div>

    <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3"><h2 class="h4 mb-0">Current Job Titles</h2><form class="d-flex gap-2" method="get"><input class="form-control" name="search" value="<?= ttHtml($search) ?>" placeholder="Search templates"><button class="btn btn-outline-primary">Search</button></form></div>
    <div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Job Title</th><th>Operating Pattern</th><th>Home / Operating Base</th><th>Associated Locations</th><th>Associated Cars</th><th>Status</th><th></th></tr></thead><tbody>
    <?php foreach ($jobs as $job): $label = $types[$job['job_type']] ?? ($job['custom_job_type'] ?: ucwords(str_replace('_', ' ', $job['job_type']))); ?><tr><td><strong><?= ttHtml($job['job_name']) ?></strong><div class="small text-muted"><?= ttHtml($job['description']) ?></div></td><td><?= ttHtml($label) ?></td><td><?= ttHtml($job['home_location'] ?: 'Not set (selected per assignment)') ?></td><td><?= $job['associated_location_count'] === null ? '—' : (int)$job['associated_location_count'] ?></td><td><?= $job['associated_car_count'] === null ? '—' : (int)$job['associated_car_count'] ?></td><td><span class="badge <?= $job['active'] ? 'bg-success' : 'bg-secondary' ?>"><?= $job['active'] ? 'Active' : 'Inactive' ?></span></td><td class="text-nowrap"><a class="btn btn-sm btn-outline-primary" href="list.php?edit=<?= (int)$job['id'] ?>#job-title-form">Edit</a> <a class="btn btn-sm btn-outline-danger" href="delete.php?id=<?= (int)$job['id'] ?>">Delete</a></td></tr><?php endforeach; ?>
    <?php if (!$jobs): ?><tr><td colspan="7" class="text-center text-muted py-5">No Job Title templates found.</td></tr><?php endif; ?></tbody></table></div></div>
</section></div>
<?php include '../includes/footer.php'; ?>
