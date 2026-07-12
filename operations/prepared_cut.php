<?php
session_start();
require_once '../config/database.php';
require_once 'lib.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$railroad = ttOperationsRailroad($pdo, (int)$_SESSION['user_id']);
$railroadId = (int)$railroad['id'];
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$error = '';

function loadPreparedCut(PDO $pdo, int $id, int $railroadId): array
{
    $stmt = $pdo->prepare('SELECT c.*,i.industry_name,j.job_name FROM prepared_cuts c JOIN industries i ON i.id=c.current_industry_id LEFT JOIN jobs j ON j.id=c.intended_job_template_id WHERE c.id=? AND c.railroad_id=?');
    $stmt->execute([$id, $railroadId]);
    $cut = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$cut) {
        http_response_code(404);
        die('Prepared cut not found.');
    }
    return $cut;
}

$cut = loadPreparedCut($pdo, $id, $railroadId);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        ttOperationsRequireCsrf();
        $action = (string)($_POST['action'] ?? '');
        if (!in_array($action, ['release', 'dissolve', 'save'], true)) {
            throw new RuntimeException('Invalid action.');
        }

        $pdo->beginTransaction();
        if ($action === 'save') {
            if ($cut['status'] !== 'ready') {
                throw new RuntimeException('Only a Ready cut can be edited.');
            }
            $name = substr(trim((string)($_POST['name'] ?? '')), 0, 120);
            if ($name === '') {
                throw new RuntimeException('Name required.');
            }
            $jobId = (int)($_POST['intended_job_template_id'] ?? 0);
            if ($jobId > 0) {
                $jobStmt = $pdo->prepare('SELECT id FROM jobs WHERE id=? AND railroad_id=?');
                $jobStmt->execute([$jobId, $railroadId]);
                if (!$jobStmt->fetchColumn()) {
                    throw new RuntimeException('Invalid intended Job Title.');
                }
            }
            $stmt = $pdo->prepare('UPDATE prepared_cuts SET name=?,current_track=?,intended_job_template_id=?,notes=? WHERE id=? AND railroad_id=?');
            $stmt->execute([
                $name,
                substr(trim((string)($_POST['current_track'] ?? '')), 0, 120),
                $jobId > 0 ? $jobId : null,
                substr(trim((string)($_POST['notes'] ?? '')), 0, 5000),
                $id,
                $railroadId
            ]);
        } else {
            $status = $action === 'release' ? 'released' : 'dissolved';
            $stmt = $pdo->prepare("UPDATE prepared_cuts SET status=? WHERE id=? AND railroad_id=? AND status<>'in_use'");
            $stmt->execute([$status, $id, $railroadId]);
        }
        $pdo->commit();
        header('Location: prepared_cut.php?id=' . $id);
        exit;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = $e->getMessage();
    }
}

$cut = loadPreparedCut($pdo, $id, $railroadId);
$carStmt = $pdo->prepare('SELECT e.*,pc.position,i.industry_name FROM prepared_cut_cars pc JOIN equipment e ON e.id=pc.equipment_id AND e.railroad_id=? LEFT JOIN industries i ON i.id=e.current_industry_id WHERE pc.prepared_cut_id=? ORDER BY pc.position');
$carStmt->execute([$railroadId, $id]);
$cars = $carStmt->fetchAll(PDO::FETCH_ASSOC);
$jobStmt = $pdo->prepare('SELECT id,job_name FROM jobs WHERE railroad_id=? ORDER BY active DESC,job_name');
$jobStmt->execute([$railroadId]);
$jobs = $jobStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<?php include '../includes/header.php'; ?>
<title><?= ttHtml($cut['cut_number']) ?> Prepared Cut</title>
<link rel="stylesheet" href="../assets/css/operations.css">
</head><body>
<?php include '../includes/navbar.php'; ?>
<div class="tt-operations-shell">
<?php include '../assets/components/sidebar.php'; ?>
<section class="tt-ops-page">
    <p class="tt-eyebrow"><?= ttHtml($cut['cut_number']) ?></p>
    <div class="d-flex justify-content-between gap-3"><h1><?= ttHtml($cut['name']) ?></h1><span class="badge tt-status-<?= ttHtml($cut['status']) ?>"><?= ttHtml(ucwords($cut['status'])) ?></span></div>
    <p class="text-muted"><?= ttHtml($cut['industry_name']) ?><?= $cut['current_track'] !== '' ? ' · ' . ttHtml($cut['current_track']) : '' ?> · <?= count($cars) ?> cars</p>
    <?php if ($error): ?><div class="alert alert-danger"><?= ttHtml($error) ?></div><?php endif; ?>

    <div class="card mb-4"><div class="card-body"><form method="post" class="row g-3">
        <input type="hidden" name="csrf_token" value="<?= ttHtml(ttOperationsCsrfToken()) ?>"><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="save">
        <div class="col-md-6"><label class="form-label">Name</label><input class="form-control" name="name" value="<?= ttHtml($cut['name']) ?>" <?= $cut['status'] === 'ready' ? '' : 'disabled' ?>></div>
        <div class="col-md-6"><label class="form-label">Current Location</label><input class="form-control" value="<?= ttHtml($cut['industry_name']) ?>" disabled><div class="form-text">Changing cut details never changes any car's physical location.</div></div>
        <div class="col-md-6"><label class="form-label">Track / Spot <span class="text-muted">(optional)</span></label><input class="form-control" name="current_track" maxlength="120" value="<?= ttHtml($cut['current_track']) ?>" <?= $cut['status'] === 'ready' ? '' : 'disabled' ?>></div>
        <div class="col-md-6"><label class="form-label">Intended Job Title <span class="text-muted">(optional)</span></label><select class="form-select" name="intended_job_template_id" <?= $cut['status'] === 'ready' ? '' : 'disabled' ?>><option value="">None</option><?php foreach ($jobs as $job): ?><option value="<?= (int)$job['id'] ?>" <?= (int)$cut['intended_job_template_id'] === (int)$job['id'] ? 'selected' : '' ?>><?= ttHtml($job['job_name']) ?></option><?php endforeach; ?></select></div>
        <div class="col-12"><label class="form-label">Notes</label><textarea class="form-control" name="notes" <?= $cut['status'] === 'ready' ? '' : 'disabled' ?>><?= ttHtml($cut['notes']) ?></textarea></div>
        <?php if ($cut['status'] === 'ready'): ?><div><button class="btn btn-primary">Save Details</button></div><?php endif; ?>
    </form></div></div>

    <div class="card mb-4"><div class="card-header"><h2 class="h4 mb-0">Cars in saved order</h2></div><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>#</th><th>Photo</th><th>Car</th><th>Type</th><th>Load</th><th>Service</th><th>Physical location</th></tr></thead><tbody><?php foreach ($cars as $car): ?><tr><td><?= (int)$car['position'] ?></td><td><?php if ($url = ttPhotoUrl($car['photo_filename'])): ?><img class="tt-car-thumb" src="<?= ttHtml($url) ?>" alt="<?= ttHtml(trim($car['reporting_marks'] . ' ' . $car['road_number'])) ?>"><?php else: ?><span class="tt-no-photo">No Photo</span><?php endif; ?></td><td><strong><?= ttHtml(trim($car['reporting_marks'] . ' ' . $car['road_number'])) ?></strong></td><td><?= ttHtml($car['equipment_type']) ?></td><td><?= ttHtml($car['load_status']) ?></td><td><?= ttHtml($car['operations_service']) ?></td><td><?= ttHtml(($car['industry_name'] ?: 'Unassigned') . ($car['current_track'] !== '' ? ' / ' . $car['current_track'] : '')) ?></td></tr><?php endforeach; ?></tbody></table></div></div>

    <?php if (in_array($cut['status'], ['ready','assigned'], true)): ?><div class="d-flex gap-2"><form method="post"><input type="hidden" name="csrf_token" value="<?= ttHtml(ttOperationsCsrfToken()) ?>"><input type="hidden" name="id" value="<?= $id ?>"><button class="btn btn-outline-warning" name="action" value="release">Release</button></form><form method="post" onsubmit="return confirm('Dissolve this grouping? No equipment will move.')"><input type="hidden" name="csrf_token" value="<?= ttHtml(ttOperationsCsrfToken()) ?>"><input type="hidden" name="id" value="<?= $id ?>"><button class="btn btn-outline-danger" name="action" value="dissolve">Dissolve</button></form></div><?php endif; ?>
</section></div>
<?php include '../includes/footer.php'; ?>
