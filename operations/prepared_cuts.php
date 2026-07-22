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
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        ttOperationsRequireCsrf();
        $action = (string)($_POST['action'] ?? 'create');

        if ($action === 'dissolve') {
            $cutId = (int)($_POST['cut_id'] ?? 0);
            $stmt = $pdo->prepare("UPDATE prepared_cuts SET status='dissolved' WHERE id=? AND railroad_id=? AND status IN('ready','assigned','released')");
            $stmt->execute([$cutId, $railroadId]);
            if (!$stmt->rowCount()) {
                throw new RuntimeException('This prepared cut cannot be dissolved.');
            }
            header('Location: prepared_cuts.php');
            exit;
        }

        if ($action !== 'create') {
            throw new RuntimeException('Invalid action.');
        }

        $name = substr(trim((string)($_POST['name'] ?? '')), 0, 120);
        $industryId = (int)($_POST['current_industry_id'] ?? 0);
        $track = substr(trim((string)($_POST['current_track'] ?? '')), 0, 120);
        $carIds = ttPreparedCutCarIds($_POST['car_ids'] ?? []);

        if ($name === '') {
            throw new RuntimeException('Name required.');
        }
        if ($industryId <= 0) {
            throw new RuntimeException('Location required.');
        }
        $pdo->beginTransaction();
        $industryStmt = $pdo->prepare('SELECT id FROM industries WHERE id=? AND railroad_id=?');
        $industryStmt->execute([$industryId, $railroadId]);
        if (!$industryStmt->fetchColumn()) {
            throw new RuntimeException('Location required.');
        }

        $jobId = ttPreparedCutValidateJob($pdo, (int)($_POST['intended_job_template_id'] ?? 0), $railroadId);

        $number = ttNextScopedNumber($pdo, 'prepared_cuts', 'cut_number', $railroadId, 'CUT-', 5);
        $insertCut = $pdo->prepare('INSERT INTO prepared_cuts (railroad_id,cut_number,name,current_industry_id,current_track,intended_job_template_id,notes) VALUES (?,?,?,?,?,?,?)');
        $insertCut->execute([
            $railroadId,
            $number,
            $name,
            $industryId,
            $track,
            $jobId,
            substr(trim((string)($_POST['notes'] ?? '')), 0, 5000)
        ]);
        $cutId = (int)$pdo->lastInsertId();

        ttPreparedCutReplaceCars($pdo, $cutId, $railroadId, $industryId, $carIds);

        $pdo->commit();
        header('Location: prepared_cut.php?id=' . $cutId);
        exit;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = $e->getMessage();
    }
}

$cutStmt = $pdo->prepare("SELECT c.*,i.industry_name,j.job_name,COUNT(pc.equipment_id) car_count FROM prepared_cuts c JOIN industries i ON i.id=c.current_industry_id LEFT JOIN jobs j ON j.id=c.intended_job_template_id LEFT JOIN prepared_cut_cars pc ON pc.prepared_cut_id=c.id WHERE c.railroad_id=? GROUP BY c.id ORDER BY FIELD(c.status,'ready','assigned','in_use','released','dissolved'),c.id DESC");
$cutStmt->execute([$railroadId]);
$cuts = $cutStmt->fetchAll(PDO::FETCH_ASSOC);

$industryStmt = $pdo->prepare('SELECT id,industry_name FROM industries WHERE railroad_id=? ORDER BY industry_name');
$industryStmt->execute([$railroadId]);
$industries = $industryStmt->fetchAll(PDO::FETCH_ASSOC);

$jobStmt = $pdo->prepare('SELECT id,job_name FROM jobs WHERE railroad_id=? AND active=1 ORDER BY job_name');
$jobStmt->execute([$railroadId]);
$jobs = $jobStmt->fetchAll(PDO::FETCH_ASSOC);

$carStmt = $pdo->prepare("SELECT e.id,e.reporting_marks,e.road_number,e.equipment_type,e.load_status,e.operations_service,e.current_industry_id,e.current_track,e.photo_filename,i.industry_name FROM equipment e JOIN industries i ON i.id=e.current_industry_id WHERE e.railroad_id=? AND e.active=1 AND e.equipment_class IN ('Freight Car','Passenger Car','MOW') AND NOT EXISTS (SELECT 1 FROM prepared_cut_cars pc JOIN prepared_cuts c ON c.id=pc.prepared_cut_id WHERE pc.equipment_id=e.id AND c.status IN('ready','assigned','in_use')) ORDER BY i.industry_name,e.current_track,e.reporting_marks,e.road_number");
$carStmt->execute([$railroadId]);
$cars = $carStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<?php include '../includes/header.php'; ?>
<title>Prepared Cuts</title>
<link rel="stylesheet" href="../assets/css/operations.css">
</head><body>
<?php include '../includes/navbar.php'; ?>
<div class="tt-operations-shell">
<?php include '../assets/components/sidebar.php'; ?>
<section class="tt-ops-page">
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
        <div><p class="tt-eyebrow">Operations</p><h1>Prepared Trains &amp; Cuts</h1><p class="text-muted mb-0">Saved physical groups of cars. Managing a cut never moves equipment or changes waybills.</p></div>
        <button class="btn btn-primary" data-bs-toggle="collapse" data-bs-target="#newCut">Create Prepared Cut</button>
    </div>
    <?php if ($error): ?><div class="alert alert-danger"><?= ttHtml($error) ?></div><?php endif; ?>

    <div class="collapse show mb-4" id="newCut"><div class="card card-body">
        <h2 class="h4">Create Prepared Cut</h2>
        <form method="post" id="cutForm" class="row g-3">
            <input type="hidden" name="csrf_token" value="<?= ttHtml(ttOperationsCsrfToken()) ?>">
            <input type="hidden" name="action" value="create">
            <div class="col-md-6"><label class="form-label">Name</label><input class="form-control" name="name" maxlength="120" required></div>
            <div class="col-md-6"><label class="form-label">Intended Job Title <span class="text-muted">(optional)</span></label><select class="form-select" name="intended_job_template_id"><option value="">None</option><?php foreach ($jobs as $job): ?><option value="<?= (int)$job['id'] ?>"><?= ttHtml($job['job_name']) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-6"><label class="form-label">Current Location</label><select class="form-select" name="current_industry_id" id="cutLocation" required><option value="">Choose…</option><?php foreach ($industries as $industry): ?><option value="<?= (int)$industry['id'] ?>"><?= ttHtml($industry['industry_name']) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-6"><label class="form-label">Track / Spot <span class="text-muted">(optional)</span></label><input class="form-control" name="current_track" maxlength="120" placeholder="Where the completed cut will be staged"></div>
            <div class="col-12">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                    <label class="form-label mb-0">Cars <span class="text-muted">(saved in displayed order)</span></label>
                    <div class="d-flex align-items-center gap-2"><button class="btn btn-sm btn-outline-primary" type="button" id="selectAllCars">Select All</button><button class="btn btn-sm btn-outline-secondary" type="button" id="clearCars">Clear Selection</button><strong id="selectedCarCount" aria-live="polite">0 cars selected</strong></div>
                </div>
                <div class="tt-car-picker" id="cutCarPicker">
                <?php foreach ($cars as $car): ?>
                    <label class="tt-car-choice" data-location="<?= (int)$car['current_industry_id'] ?>">
                        <input type="checkbox" name="car_ids[]" value="<?= (int)$car['id'] ?>">
                        <?php if ($url = ttPhotoUrl($car['photo_filename'])): ?><img src="<?= ttHtml($url) ?>" alt="<?= ttHtml(trim($car['reporting_marks'] . ' ' . $car['road_number'])) ?>"><?php else: ?><span class="tt-no-photo">No Photo</span><?php endif; ?>
                        <span><strong><?= ttHtml(trim($car['reporting_marks'] . ' ' . $car['road_number'])) ?></strong><small><?= ttHtml($car['equipment_type'] . ' · ' . $car['load_status'] . ' · ' . $car['operations_service'] . ' · ' . $car['industry_name'] . ($car['current_track'] !== '' ? ' / ' . $car['current_track'] : '')) ?></small></span>
                    </label>
                <?php endforeach; ?>
                </div>
            </div>
            <div class="col-12"><label class="form-label">Notes</label><textarea class="form-control" name="notes" rows="2"></textarea></div>
            <div><button class="btn btn-success">Save Ready Cut</button></div>
        </form>
    </div></div>

    <div class="card"><div class="card-header"><h2 class="h4 mb-0">Saved Cuts</h2></div><div class="table-responsive"><table class="table table-hover align-middle mb-0">
        <thead><tr><th>Cut</th><th>Location</th><th>Track / Spot</th><th>Cars</th><th>Intended Job Title</th><th>Status</th><th></th></tr></thead>
        <tbody><?php foreach ($cuts as $cut): ?><tr><td><strong><?= ttHtml($cut['cut_number']) ?></strong><br><small><?= ttHtml($cut['name']) ?></small></td><td><?= ttHtml($cut['industry_name']) ?></td><td><?= ttHtml($cut['current_track'] !== '' ? $cut['current_track'] : '—') ?></td><td><?= (int)$cut['car_count'] ?></td><td><?= ttHtml($cut['job_name'] ?: '—') ?></td><td><span class="badge tt-status-<?= ttHtml($cut['status']) ?>"><?= ttHtml(ucwords($cut['status'])) ?></span></td><td class="text-nowrap"><a class="btn btn-sm btn-outline-primary" href="prepared_cut.php?id=<?= (int)$cut['id'] ?>">View</a><?php if ($cut['status'] === 'ready'): ?><a class="btn btn-sm btn-primary" href="prepared_cut.php?id=<?= (int)$cut['id'] ?>&amp;edit=1">Edit</a><?php endif; ?><?php if (in_array($cut['status'], ['ready','assigned','released'], true)): ?><form method="post" class="d-inline" onsubmit="return confirm('Dissolve this grouping? No equipment will move.')"><input type="hidden" name="csrf_token" value="<?= ttHtml(ttOperationsCsrfToken()) ?>"><input type="hidden" name="action" value="dissolve"><input type="hidden" name="cut_id" value="<?= (int)$cut['id'] ?>"><button class="btn btn-sm btn-outline-danger">Dissolve</button></form><?php endif; ?></td></tr><?php endforeach; ?><?php if (!$cuts): ?><tr><td colspan="7" class="text-center text-muted py-4">No prepared cuts saved.</td></tr><?php endif; ?></tbody>
    </table></div></div>
</section></div>
<script>
(function () {
    const location = document.getElementById('cutLocation');
    const choices = Array.from(document.querySelectorAll('.tt-car-choice'));
    const count = document.getElementById('selectedCarCount');
    const visibleBoxes = () => choices.filter(choice => !choice.hidden).map(choice => choice.querySelector('input'));
    function updateCount() {
        const selected = choices.filter(choice => choice.querySelector('input').checked).length;
        count.textContent = selected + (selected === 1 ? ' car selected' : ' cars selected');
    }
    function filterByLocation() {
        const selectedLocation = location.value;
        choices.forEach(choice => {
            const show = selectedLocation !== '' && choice.dataset.location === selectedLocation;
            choice.hidden = !show;
            if (!show) choice.querySelector('input').checked = false;
        });
        updateCount();
    }
    location.addEventListener('change', filterByLocation);
    document.getElementById('selectAllCars').addEventListener('click', () => { visibleBoxes().forEach(box => box.checked = true); updateCount(); });
    document.getElementById('clearCars').addEventListener('click', () => { choices.forEach(choice => choice.querySelector('input').checked = false); updateCount(); });
    choices.forEach(choice => choice.querySelector('input').addEventListener('change', updateCount));
    filterByLocation();
})();
</script>
<?php include '../includes/footer.php'; ?>
