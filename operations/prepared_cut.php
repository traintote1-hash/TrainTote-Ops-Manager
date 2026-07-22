<?php
session_start();
require_once '../config/database.php';
require_once 'lib.php';
require_once 'prepared_cut_service.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$railroad = ttOperationsRailroad($pdo, (int)$_SESSION['user_id']);
$railroadId = (int)$railroad['id'];
ttOperationsRequireRailroadOwner($pdo, $railroadId, (int)$_SESSION['user_id']);
ttOperationsRequireModule($pdo, $railroadId, 'advanced_roles');
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$error = '';

function loadPreparedCut(PDO $pdo, int $id, int $railroadId, bool $lock = false): array
{
    $stmt = $pdo->prepare('SELECT c.*,i.industry_name,j.job_name FROM prepared_cuts c JOIN industries i ON i.id=c.current_industry_id AND i.railroad_id=c.railroad_id LEFT JOIN jobs j ON j.id=c.intended_job_template_id AND j.railroad_id=c.railroad_id WHERE c.id=? AND c.railroad_id=?' . ($lock ? ' FOR UPDATE' : ''));
    $stmt->execute([$id, $railroadId]);
    $cut = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$cut) {
        http_response_code(404);
        throw new RuntimeException('Prepared cut not found.');
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
        $lockedCut = loadPreparedCut($pdo, $id, $railroadId, true);
        if ($action === 'save') {
            if (!ttPreparedCutIsEditable($lockedCut)) {
                http_response_code(409);
                throw new RuntimeException('This prepared cut is locked and can no longer be edited.');
            }
            $name = substr(trim((string)($_POST['name'] ?? '')), 0, 120);
            if ($name === '') {
                throw new RuntimeException('Name required.');
            }
            $carIds = ttPreparedCutCarIds($_POST['car_ids'] ?? []);
            $jobId = ttPreparedCutValidateJob($pdo, (int)($_POST['intended_job_template_id'] ?? 0), $railroadId);
            ttPreparedCutReplaceCars(
                $pdo,
                $id,
                $railroadId,
                (int)$lockedCut['current_industry_id'],
                $carIds
            );
            $stmt = $pdo->prepare("UPDATE prepared_cuts SET name=?,current_track=?,intended_job_template_id=?,notes=? WHERE id=? AND railroad_id=? AND status='ready'");
            $stmt->execute([
                $name,
                substr(trim((string)($_POST['current_track'] ?? '')), 0, 120),
                $jobId,
                substr(trim((string)($_POST['notes'] ?? '')), 0, 5000),
                $id,
                $railroadId,
            ]);
        } else {
            $status = $action === 'release' ? 'released' : 'dissolved';
            $stmt = $pdo->prepare("UPDATE prepared_cuts SET status=? WHERE id=? AND railroad_id=? AND status<>'in_use'");
            $stmt->execute([$status, $id, $railroadId]);
            if ($stmt->rowCount() !== 1) {
                throw new RuntimeException('This prepared cut is locked by an active operating workflow.');
            }
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
$editable = ttPreparedCutIsEditable($cut);
$editing = $editable && (string)($_GET['edit'] ?? '') === '1';
$carStmt = $pdo->prepare('SELECT e.*,pc.position,i.industry_name FROM prepared_cut_cars pc JOIN equipment e ON e.id=pc.equipment_id AND e.railroad_id=? LEFT JOIN industries i ON i.id=e.current_industry_id AND i.railroad_id=e.railroad_id WHERE pc.prepared_cut_id=? ORDER BY pc.position');
$carStmt->execute([$railroadId, $id]);
$cars = $carStmt->fetchAll(PDO::FETCH_ASSOC);
$jobStmt = $pdo->prepare('SELECT id,job_name FROM jobs WHERE railroad_id=? ORDER BY active DESC,job_name');
$jobStmt->execute([$railroadId]);
$jobs = $jobStmt->fetchAll(PDO::FETCH_ASSOC);
$eligibleCars = [];
if ($editing) {
    $eligibleStmt = $pdo->prepare("SELECT e.id,e.reporting_marks,e.road_number,e.equipment_type,e.load_status,e.operations_service,e.current_track,e.photo_filename,own.position FROM equipment e LEFT JOIN prepared_cut_cars own ON own.prepared_cut_id=? AND own.equipment_id=e.id WHERE e.railroad_id=? AND e.active=1 AND e.current_industry_id=? AND e.equipment_class IN ('Freight Car','Passenger Car','MOW') AND (own.equipment_id IS NOT NULL OR NOT EXISTS (SELECT 1 FROM prepared_cut_cars pc JOIN prepared_cuts c ON c.id=pc.prepared_cut_id WHERE pc.equipment_id=e.id AND pc.prepared_cut_id<>? AND c.railroad_id=? AND c.status IN('ready','assigned','in_use'))) ORDER BY CASE WHEN own.position IS NULL THEN 1 ELSE 0 END,own.position,e.current_track,e.reporting_marks,e.road_number");
    $eligibleStmt->execute([$id, $railroadId, (int)$cut['current_industry_id'], $id, $railroadId]);
    $eligibleCars = $eligibleStmt->fetchAll(PDO::FETCH_ASSOC);
}
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
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3"><div><h1><?= ttHtml($cut['name']) ?></h1><p class="text-muted"><?= ttHtml($cut['industry_name']) ?><?= $cut['current_track'] !== '' ? ' · ' . ttHtml($cut['current_track']) : '' ?> · <?= count($cars) ?> cars</p></div><div><span class="badge tt-status-<?= ttHtml($cut['status']) ?>"><?= ttHtml(ucwords($cut['status'])) ?></span><?php if ($editable && !$editing): ?> <a class="btn btn-sm btn-primary" href="prepared_cut.php?id=<?= $id ?>&amp;edit=1">Edit Prepared Cut</a><?php endif; ?></div></div>
    <?php if ($error): ?><div class="alert alert-danger"><?= ttHtml($error) ?></div><?php endif; ?>
    <?php if (!$editable): ?><div class="alert alert-warning">This prepared cut is read-only because it is <?= ttHtml(str_replace('_', ' ', $cut['status'])) ?>. Cars can only be added or removed while a cut is Ready and not assigned to an operating workflow.</div><?php endif; ?>

    <?php if ($editing): ?>
    <div class="card mb-4"><div class="card-body"><form method="post" class="row g-3" id="editCutForm">
        <input type="hidden" name="csrf_token" value="<?= ttHtml(ttOperationsCsrfToken()) ?>"><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="save">
        <div class="col-md-6"><label class="form-label">Name</label><input class="form-control" name="name" maxlength="120" required value="<?= ttHtml($cut['name']) ?>"></div>
        <div class="col-md-6"><label class="form-label">Current Location</label><input class="form-control" value="<?= ttHtml($cut['industry_name']) ?>" disabled><div class="form-text">Editing this plan never changes any car's physical location.</div></div>
        <div class="col-md-6"><label class="form-label">Track / Spot <span class="text-muted">(optional)</span></label><input class="form-control" name="current_track" maxlength="120" value="<?= ttHtml($cut['current_track']) ?>"></div>
        <div class="col-md-6"><label class="form-label">Intended Job Title <span class="text-muted">(optional)</span></label><select class="form-select" name="intended_job_template_id"><option value="">None</option><?php foreach ($jobs as $job): ?><option value="<?= (int)$job['id'] ?>" <?= (int)$cut['intended_job_template_id'] === (int)$job['id'] ? 'selected' : '' ?>><?= ttHtml($job['job_name']) ?></option><?php endforeach; ?></select></div>
        <div class="col-12"><div class="d-flex flex-wrap justify-content-between gap-2 mb-2"><label class="form-label mb-0">Cars <span class="text-muted">(saved in displayed order)</span></label><strong id="selectedCarCount" aria-live="polite"></strong></div><div class="tt-car-picker"><?php foreach ($eligibleCars as $car): ?><label class="tt-car-choice"><input type="checkbox" name="car_ids[]" value="<?= (int)$car['id'] ?>" <?= $car['position'] !== null ? 'checked' : '' ?>><?php if ($url = ttPhotoUrl($car['photo_filename'])): ?><img src="<?= ttHtml($url) ?>" alt="<?= ttHtml(trim($car['reporting_marks'] . ' ' . $car['road_number'])) ?>"><?php else: ?><span class="tt-no-photo">No Photo</span><?php endif; ?><span><strong><?= ttHtml(trim($car['reporting_marks'] . ' ' . $car['road_number'])) ?></strong><small><?= ttHtml($car['equipment_type'] . ' · ' . $car['load_status'] . ' · ' . $car['operations_service'] . ($car['current_track'] !== '' ? ' · ' . $car['current_track'] : '')) ?></small></span></label><?php endforeach; ?></div></div>
        <div class="col-12"><label class="form-label">Notes</label><textarea class="form-control" name="notes" rows="2"><?= ttHtml($cut['notes']) ?></textarea></div>
        <div><button class="btn btn-primary">Save Prepared Cut</button> <a class="btn btn-secondary" href="prepared_cut.php?id=<?= $id ?>">Cancel</a></div>
    </form></div></div>
    <?php else: ?>
    <div class="card mb-4"><div class="card-body"><dl class="row mb-0"><dt class="col-sm-4">Intended Job Title</dt><dd class="col-sm-8"><?= ttHtml($cut['job_name'] ?: 'None') ?></dd><dt class="col-sm-4">Notes</dt><dd class="col-sm-8"><?= nl2br(ttHtml($cut['notes'] ?: '—')) ?></dd></dl></div></div>
    <?php endif; ?>

    <div class="card mb-4"><div class="card-header"><h2 class="h4 mb-0">Cars in saved order</h2></div><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>#</th><th>Photo</th><th>Car</th><th>Type</th><th>Load</th><th>Service</th><th>Physical location</th></tr></thead><tbody><?php foreach ($cars as $car): ?><tr><td><?= (int)$car['position'] ?></td><td><?php if ($url = ttPhotoUrl($car['photo_filename'])): ?><img class="tt-car-thumb" src="<?= ttHtml($url) ?>" alt="<?= ttHtml(trim($car['reporting_marks'] . ' ' . $car['road_number'])) ?>"><?php else: ?><span class="tt-no-photo">No Photo</span><?php endif; ?></td><td><strong><?= ttHtml(trim($car['reporting_marks'] . ' ' . $car['road_number'])) ?></strong></td><td><?= ttHtml($car['equipment_type']) ?></td><td><?= ttHtml($car['load_status']) ?></td><td><?= ttHtml($car['operations_service']) ?></td><td><?= ttHtml(($car['industry_name'] ?: 'Unassigned') . ($car['current_track'] !== '' ? ' / ' . $car['current_track'] : '')) ?></td></tr><?php endforeach; ?></tbody></table></div></div>

    <?php if (in_array($cut['status'], ['ready','assigned'], true)): ?><div class="d-flex gap-2"><form method="post"><input type="hidden" name="csrf_token" value="<?= ttHtml(ttOperationsCsrfToken()) ?>"><input type="hidden" name="id" value="<?= $id ?>"><button class="btn btn-outline-warning" name="action" value="release">Release</button></form><form method="post" onsubmit="return confirm('Dissolve this grouping? No equipment will move.')"><input type="hidden" name="csrf_token" value="<?= ttHtml(ttOperationsCsrfToken()) ?>"><input type="hidden" name="id" value="<?= $id ?>"><button class="btn btn-outline-danger" name="action" value="dissolve">Dissolve</button></form></div><?php endif; ?>
</section></div>
<?php if ($editing): ?><script src="../assets/js/prepared-cut-edit.js"></script><?php endif; ?>
<?php include '../includes/footer.php'; ?>
