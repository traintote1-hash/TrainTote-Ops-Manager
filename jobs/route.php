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
$jobId = (int)($_GET['id'] ?? $_POST['job_id'] ?? 0);
$types = ttJobTypes();
$error = '';
$message = is_string($_SESSION['job_title_message'] ?? null) ? $_SESSION['job_title_message'] : '';
unset($_SESSION['job_title_message']);

$jobStmt = $pdo->prepare("SELECT j.*,COALESCE(jop.work_scope,'entire_railroad') work_scope FROM jobs j LEFT JOIN job_operation_profiles jop ON jop.job_id=j.id AND jop.railroad_id=j.railroad_id WHERE j.id=? AND j.railroad_id=?");
$jobStmt->execute([$jobId, $railroadId]);
$job = $jobStmt->fetch(PDO::FETCH_ASSOC);
if (!$job) {
    http_response_code(404);
    die('Job Title not found.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    try {
        ttOperationsRequireCsrf();
        $pdo->beginTransaction();

        if ($action === 'save_job') {
            $name = substr(trim((string)($_POST['job_name'] ?? '')), 0, 120);
            $type = (string)($_POST['job_type'] ?? 'local_turn');
            $description = substr(trim((string)($_POST['description'] ?? '')), 0, 5000);
            $active = ($_POST['active'] ?? '1') === '1' ? 1 : 0;
            $workScope = (string)($_POST['work_scope'] ?? 'entire_railroad');

            if ($name === '') {
                throw new RuntimeException('Job Title name is required.');
            }
            if (!isset($types[$type])) {
                throw new RuntimeException('Select a valid default operating pattern.');
            }
            if (!in_array($workScope, ['entire_railroad', 'selected_route'], true)) {
                throw new RuntimeException('Select a valid work scope.');
            }

            $update = $pdo->prepare('UPDATE jobs SET job_name=?,job_type=?,custom_job_type=?,description=?,active=? WHERE id=? AND railroad_id=?');
            $update->execute([$name, $type, '', $description, $active, $jobId, $railroadId]);
            $profile = $pdo->prepare('INSERT INTO job_operation_profiles (job_id,railroad_id,work_scope) VALUES (?,?,?) ON DUPLICATE KEY UPDATE railroad_id=VALUES(railroad_id),work_scope=VALUES(work_scope)');
            $profile->execute([$jobId, $railroadId, $workScope]);
            $message = 'Job Title updated.';
        } elseif ($action === 'add_areas') {
            $areasToAdd = array_values(array_unique(array_filter(array_map(
                static fn($value) => trim(substr((string)$value, 0, 255)),
                (array)($_POST['operating_areas'] ?? [])
            ))));
            if (!$areasToAdd) {
                throw new RuntimeException('Select at least one Operating Area.');
            }

            $check = $pdo->prepare("SELECT COUNT(*) FROM industries WHERE railroad_id=? AND active=1 AND NULLIF(TRIM(location),'') IS NOT NULL AND TRIM(location)=?");
            $duplicate = $pdo->prepare('SELECT id FROM job_route_stops WHERE job_id=? AND railroad_id=? AND operating_area=? FOR UPDATE');
            $sequence = $pdo->prepare('SELECT COALESCE(MAX(sequence_number),0)+1 FROM job_route_stops WHERE job_id=? AND railroad_id=? FOR UPDATE');
            $insert = $pdo->prepare('INSERT INTO job_route_stops(railroad_id,job_id,industry_id,operating_area,sequence_number) VALUES(?,?,NULL,?,?)');
            $added = 0;

            foreach ($areasToAdd as $area) {
                $check->execute([$railroadId, $area]);
                if (!(int)$check->fetchColumn()) {
                    throw new RuntimeException('Operating Area "' . $area . '" no longer contains an active industry.');
                }
                $duplicate->execute([$jobId, $railroadId, $area]);
                if ($duplicate->fetchColumn()) {
                    continue;
                }
                $sequence->execute([$jobId, $railroadId]);
                $insert->execute([$railroadId, $jobId, $area, (int)$sequence->fetchColumn()]);
                $added++;
            }

            $pdo->prepare("INSERT INTO job_operation_profiles(job_id,railroad_id,work_scope) VALUES(?,?,'selected_route') ON DUPLICATE KEY UPDATE work_scope='selected_route'")->execute([$jobId, $railroadId]);
            $message = $added . ' Operating Area' . ($added === 1 ? '' : 's') . ' added to the default route.';
        } elseif ($action === 'remove') {
            $stopId = (int)($_POST['stop_id'] ?? 0);
            $pdo->prepare('DELETE FROM job_route_stops WHERE id=? AND job_id=? AND railroad_id=?')->execute([$stopId, $jobId, $railroadId]);
            $rows = $pdo->prepare('SELECT id FROM job_route_stops WHERE job_id=? AND railroad_id=? ORDER BY sequence_number,id');
            $rows->execute([$jobId, $railroadId]);
            $renumber = $pdo->prepare('UPDATE job_route_stops SET sequence_number=? WHERE id=?');
            foreach ($rows->fetchAll(PDO::FETCH_COLUMN) as $index => $id) {
                $renumber->execute([$index + 1, (int)$id]);
            }
            $message = 'Operating Area removed.';
        } elseif (in_array($action, ['up', 'down'], true)) {
            $stopId = (int)($_POST['stop_id'] ?? 0);
            $rows = $pdo->prepare("SELECT id,sequence_number FROM job_route_stops WHERE job_id=? AND railroad_id=? AND NULLIF(TRIM(operating_area),'') IS NOT NULL ORDER BY sequence_number FOR UPDATE");
            $rows->execute([$jobId, $railroadId]);
            $routeStops = $rows->fetchAll(PDO::FETCH_ASSOC);
            $index = null;
            foreach ($routeStops as $candidateIndex => $stop) {
                if ((int)$stop['id'] === $stopId) {
                    $index = $candidateIndex;
                    break;
                }
            }
            $other = $action === 'up' ? $index - 1 : $index + 1;
            if ($index !== null && isset($routeStops[$other])) {
                $pdo->prepare('UPDATE job_route_stops SET sequence_number=0 WHERE id=?')->execute([$stopId]);
                $pdo->prepare('UPDATE job_route_stops SET sequence_number=? WHERE id=?')->execute([(int)$routeStops[$index]['sequence_number'], (int)$routeStops[$other]['id']]);
                $pdo->prepare('UPDATE job_route_stops SET sequence_number=? WHERE id=?')->execute([(int)$routeStops[$other]['sequence_number'], $stopId]);
            }
            $message = 'Default route order updated.';
        } elseif ($action === 'rules') {
            $stopId = (int)($_POST['stop_id'] ?? 0);
            $statuses = ['Any', 'Loaded', 'Empty'];
            $pullModes = ['operating_base', 'yard', 'staging_interchange', 'selected_location', 'next_compatible'];
            $sourceModes = ['operating_base', 'starting_cars', 'prepared_cut', 'staged_group', 'selected_location'];
            $outbound = (string)($_POST['outbound_load_status'] ?? 'Any');
            $inbound = (string)($_POST['inbound_load_status'] ?? 'Any');
            $exchangeEnabled = ($_POST['exchange_enabled'] ?? '') === '1' ? 1 : 0;
            $pullMode = (string)($_POST['pull_destination_mode'] ?? 'yard');
            $sourceMode = (string)($_POST['replacement_source_mode'] ?? 'starting_cars');

            if (!in_array($outbound, $statuses, true) || !in_array($inbound, $statuses, true) || !in_array($pullMode, $pullModes, true) || !in_array($sourceMode, $sourceModes, true)) {
                throw new RuntimeException('Invalid exchange rule.');
            }

            $pullId = (int)($_POST['pull_destination_industry_id'] ?? 0);
            $sourceId = (int)($_POST['replacement_source_industry_id'] ?? 0);
            foreach ([$pullId, $sourceId] as $locationId) {
                if ($locationId > 0) {
                    $check = $pdo->prepare('SELECT id FROM industries WHERE id=? AND railroad_id=?');
                    $check->execute([$locationId, $railroadId]);
                    if (!$check->fetchColumn()) {
                        throw new RuntimeException('Exchange locations must belong to this railroad.');
                    }
                }
            }

            $updateRules = $pdo->prepare('UPDATE job_route_stops SET exchange_enabled=?,outbound_load_status=?,inbound_load_status=?,pull_destination_mode=?,pull_destination_industry_id=?,replacement_source_mode=?,replacement_source_industry_id=? WHERE id=? AND job_id=? AND railroad_id=?');
            $updateRules->execute([$exchangeEnabled, $outbound, $inbound, $pullMode, $pullId ?: null, $sourceMode, $sourceId ?: null, $stopId, $jobId, $railroadId]);
            $message = 'Operating Area rules saved.';
        } else {
            throw new RuntimeException('Invalid Job Title action.');
        }

        $pdo->commit();

        $jobStmt->execute([$jobId, $railroadId]);
        $job = $jobStmt->fetch(PDO::FETCH_ASSOC);
        if (!$job) {
            throw new RuntimeException('Job Title not found after saving.');
        }
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = $exception->getMessage();
        if ($action === 'save_job') {
            $job['job_name'] = $_POST['job_name'] ?? $job['job_name'];
            $job['job_type'] = $_POST['job_type'] ?? $job['job_type'];
            $job['description'] = $_POST['description'] ?? $job['description'];
            $job['active'] = ($_POST['active'] ?? '1') === '1';
            $job['work_scope'] = $_POST['work_scope'] ?? $job['work_scope'];
        }
    }
}

$areaStmt = $pdo->prepare("SELECT TRIM(location) operating_area,COUNT(*) industry_count,GROUP_CONCAT(industry_name ORDER BY industry_name SEPARATOR ' · ') industry_names FROM industries WHERE railroad_id=? AND active=1 AND NULLIF(TRIM(location),'') IS NOT NULL GROUP BY TRIM(location) ORDER BY TRIM(location)");
$areaStmt->execute([$railroadId]);
$areas = $areaStmt->fetchAll(PDO::FETCH_ASSOC);

$industryStmt = $pdo->prepare('SELECT id,industry_name FROM industries WHERE railroad_id=? AND active=1 ORDER BY industry_name');
$industryStmt->execute([$railroadId]);
$industries = $industryStmt->fetchAll(PDO::FETCH_ASSOC);

$stopStmt = $pdo->prepare("SELECT jrs.*,a.industry_count,a.industry_names,legacy.location legacy_location,legacy.industry_name legacy_industry_name FROM job_route_stops jrs LEFT JOIN industries legacy ON legacy.id=jrs.industry_id AND legacy.railroad_id=jrs.railroad_id LEFT JOIN (SELECT railroad_id,TRIM(location) operating_area,COUNT(*) industry_count,GROUP_CONCAT(industry_name ORDER BY industry_name SEPARATOR ' · ') industry_names FROM industries WHERE active=1 AND NULLIF(TRIM(location),'') IS NOT NULL GROUP BY railroad_id,TRIM(location)) a ON a.railroad_id=jrs.railroad_id AND a.operating_area=jrs.operating_area WHERE jrs.job_id=? AND jrs.railroad_id=? ORDER BY jrs.sequence_number");
$stopStmt->execute([$jobId, $railroadId]);
$allStops = $stopStmt->fetchAll(PDO::FETCH_ASSOC);
$stops = [];
$legacyWarnings = [];
foreach ($allStops as $stop) {
    if (trim((string)($stop['operating_area'] ?? '')) !== '') {
        $stops[] = $stop;
    } elseif (trim((string)($stop['legacy_location'] ?? '')) === '') {
        $legacyWarnings[] = $stop['legacy_industry_name'] ?: 'Route entry #' . (int)$stop['id'];
    }
}
$selectedAreas = array_column($stops, 'operating_area');

$pullLabels = [
    'operating_base' => 'Operating base',
    'yard' => 'Yard',
    'staging_interchange' => 'Staging or interchange',
    'selected_location' => 'Another selected location',
    'next_compatible' => 'Next compatible industry in route order',
];
$sourceLabels = [
    'operating_base' => 'Operating base',
    'starting_cars' => 'Starting cars',
    'prepared_cut' => 'Prepared cut',
    'staged_group' => 'Manifest or staged group',
    'selected_location' => 'Another selected location',
];
$routeVisible = ($job['work_scope'] ?? 'entire_railroad') === 'selected_route';
?>
<?php include '../includes/header.php'; ?>
<title>Edit Job Title — <?= ttHtml($job['job_name']) ?></title>
</head><body>
<?php include '../includes/navbar.php'; ?>
<div class="tt-operations-shell">
<?php include '../assets/components/sidebar.php'; ?>
<section class="tt-ops-page">
    <p class="tt-eyebrow">Operations Job Title</p>
    <h1>Edit Job Title — <?= ttHtml($job['job_name']) ?></h1>
    <p class="text-muted">Configure the reusable Job Title and, when selected, its ordered Operating Areas in one place.</p>

    <?php if ($message): ?><div class="alert alert-success"><?= ttHtml($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= ttHtml($error) ?></div><?php endif; ?>

    <div class="card mb-4" id="job-title-fields">
        <div class="card-header"><h2 class="h4 mb-0">Job Title Settings</h2></div>
        <div class="card-body">
            <form method="post" class="row g-3">
                <input type="hidden" name="csrf_token" value="<?= ttHtml(ttOperationsCsrfToken()) ?>">
                <input type="hidden" name="job_id" value="<?= $jobId ?>">
                <input type="hidden" name="action" value="save_job">
                <div class="col-md-6">
                    <label class="form-label">Job Title</label>
                    <input class="form-control" name="job_name" maxlength="120" required value="<?= ttHtml($job['job_name']) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Default Operating Pattern</label>
                    <select class="form-select" name="job_type">
                        <?php foreach ($types as $value => $label): ?>
                            <option value="<?= ttHtml($value) ?>" <?= $job['job_type'] === $value ? 'selected' : '' ?>><?= ttHtml($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Template Status</label>
                    <select class="form-select" name="active">
                        <option value="1" <?= (int)$job['active'] === 1 ? 'selected' : '' ?>>Active</option>
                        <option value="0" <?= (int)$job['active'] === 0 ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Work Scope</label>
                    <select class="form-select" name="work_scope" id="job-work-scope">
                        <option value="entire_railroad" <?= $job['work_scope'] === 'entire_railroad' ? 'selected' : '' ?>>Entire Railroad</option>
                        <option value="selected_route" <?= $job['work_scope'] === 'selected_route' ? 'selected' : '' ?>>Selected Operating Areas</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Template Description</label>
                    <textarea class="form-control" name="description" rows="3" maxlength="5000"><?= ttHtml($job['description']) ?></textarea>
                </div>
                <div class="col-12">
                    <button class="btn btn-success">Save Job Title</button>
                    <a class="btn btn-secondary" href="list.php">Back to Job Titles</a>
                </div>
            </form>
        </div>
    </div>

    <div id="route-scope-note" class="alert alert-info <?= $routeVisible ? 'd-none' : '' ?>">
        Choose <strong>Selected Operating Areas</strong> as the Work Scope and save the Job Title to use its default route. Existing route data is preserved.
    </div>

    <div id="operating-areas" class="<?= $routeVisible ? '' : 'd-none' ?>">
        <div class="mb-3">
            <h2 class="h3 mb-1">Operating Areas</h2>
            <p class="text-muted mb-0">Operating Areas come automatically from unique, nonblank Location values on active industries. The Assignment operating base remains the separate starting point.</p>
        </div>

        <?php if ($legacyWarnings): ?>
            <div class="alert alert-warning">These preserved legacy route entries cannot become Operating Areas because their Industry Location is blank or unavailable: <?= ttHtml(implode(', ', $legacyWarnings)) ?>. Add a nonblank Industry Location, then add the resulting Operating Area to this route.</div>
        <?php endif; ?>

        <div class="card mb-4">
            <div class="card-header"><h3 class="h5 mb-0">Available Operating Areas</h3></div>
            <div class="card-body">
                <form method="post" action="route.php?id=<?= $jobId ?>#operating-areas">
                    <input type="hidden" name="csrf_token" value="<?= ttHtml(ttOperationsCsrfToken()) ?>">
                    <input type="hidden" name="job_id" value="<?= $jobId ?>">
                    <input type="hidden" name="action" value="add_areas">
                    <div class="row g-2 mb-3">
                        <?php foreach ($areas as $area): ?>
                            <?php $selected = in_array($area['operating_area'], $selectedAreas, true); ?>
                            <div class="col-md-6">
                                <label class="border rounded p-2 d-block h-100 <?= $selected ? 'bg-light text-muted' : '' ?>">
                                    <input class="form-check-input me-1" type="checkbox" name="operating_areas[]" value="<?= ttHtml($area['operating_area']) ?>" <?= $selected ? 'disabled' : '' ?>>
                                    <strong><?= ttHtml($area['operating_area']) ?></strong>
                                    <span class="badge text-bg-secondary"><?= (int)$area['industry_count'] ?> active</span>
                                    <small class="d-block mt-1"><?= ttHtml($area['industry_names']) ?></small>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if ($areas): ?>
                        <button class="btn btn-primary">Add Selected Areas</button>
                    <?php else: ?>
                        <div class="alert alert-warning mb-0">No Operating Areas are available. Add a nonblank Location to an active industry.</div>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <h3 class="h4">Selected Default Operating Areas</h3>
        <p class="text-muted">The numbered order is the default route order in which the local works the railroad.</p>

        <?php foreach ($stops as $index => $stop): ?>
            <article class="card mb-3">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div>
                        <h4 class="h5 mb-1"><span class="badge bg-primary me-2"><?= $index + 1 ?></span><?= ttHtml($stop['operating_area']) ?></h4>
                        <small><?= (int)($stop['industry_count'] ?? 0) ?> active industries<?= !empty($stop['industry_names']) ? ' · ' . ttHtml($stop['industry_names']) : '' ?></small>
                    </div>
                    <form method="post" action="route.php?id=<?= $jobId ?>#operating-areas">
                        <input type="hidden" name="csrf_token" value="<?= ttHtml(ttOperationsCsrfToken()) ?>">
                        <input type="hidden" name="job_id" value="<?= $jobId ?>">
                        <input type="hidden" name="stop_id" value="<?= (int)$stop['id'] ?>">
                        <button class="btn btn-sm btn-outline-secondary" name="action" value="up" <?= $index === 0 ? 'disabled' : '' ?>>Move Up</button>
                        <button class="btn btn-sm btn-outline-secondary" name="action" value="down" <?= $index === count($stops) - 1 ? 'disabled' : '' ?>>Move Down</button>
                        <button class="btn btn-sm btn-outline-danger" name="action" value="remove">Remove Area</button>
                    </form>
                </div>

                <?php if (empty($stop['industry_count'])): ?>
                    <div class="alert alert-warning m-3">This Route Area no longer contains any active industries. Update Industry Location values or remove the area before generating.</div>
                <?php endif; ?>

                <div class="card-body">
                    <details>
                        <summary>Area switching rules</summary>
                        <form method="post" action="route.php?id=<?= $jobId ?>#operating-areas" class="row g-3 mt-1">
                            <input type="hidden" name="csrf_token" value="<?= ttHtml(ttOperationsCsrfToken()) ?>">
                            <input type="hidden" name="job_id" value="<?= $jobId ?>">
                            <input type="hidden" name="stop_id" value="<?= (int)$stop['id'] ?>">
                            <input type="hidden" name="action" value="rules">
                            <div class="col-12">
                                <label class="form-check">
                                    <input class="form-check-input" type="checkbox" name="exchange_enabled" value="1" <?= (int)$stop['exchange_enabled'] === 1 ? 'checked' : '' ?>>
                                    Use paired car exchanges throughout this area
                                </label>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Cars to Pull</label>
                                <select class="form-select" name="outbound_load_status">
                                    <?php foreach (['Any', 'Loaded', 'Empty'] as $value): ?>
                                        <option <?= $stop['outbound_load_status'] === $value ? 'selected' : '' ?>><?= $value ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Cars to Spot</label>
                                <select class="form-select" name="inbound_load_status">
                                    <?php foreach (['Any', 'Loaded', 'Empty'] as $value): ?>
                                        <option <?= $stop['inbound_load_status'] === $value ? 'selected' : '' ?>><?= $value ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Pulled Cars Go To</label>
                                <select class="form-select" name="pull_destination_mode">
                                    <?php foreach ($pullLabels as $value => $label): ?>
                                        <option value="<?= $value ?>" <?= $stop['pull_destination_mode'] === $value ? 'selected' : '' ?>><?= ttHtml($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Specific Destination</label>
                                <select class="form-select" name="pull_destination_industry_id">
                                    <option value="">As selected</option>
                                    <?php foreach ($industries as $industry): ?>
                                        <option value="<?= (int)$industry['id'] ?>" <?= (int)$stop['pull_destination_industry_id'] === (int)$industry['id'] ? 'selected' : '' ?>><?= ttHtml($industry['industry_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Replacement Source</label>
                                <select class="form-select" name="replacement_source_mode">
                                    <?php foreach ($sourceLabels as $value => $label): ?>
                                        <option value="<?= $value ?>" <?= $stop['replacement_source_mode'] === $value ? 'selected' : '' ?>><?= ttHtml($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Specific Source</label>
                                <select class="form-select" name="replacement_source_industry_id">
                                    <option value="">As selected</option>
                                    <?php foreach ($industries as $industry): ?>
                                        <option value="<?= (int)$industry['id'] ?>" <?= (int)$stop['replacement_source_industry_id'] === (int)$industry['id'] ? 'selected' : '' ?>><?= ttHtml($industry['industry_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <button class="btn btn-success">Save Area Rules</button>
                            </div>
                        </form>
                    </details>
                </div>
            </article>
        <?php endforeach; ?>

        <?php if (!$stops): ?>
            <div class="alert alert-info">No Operating Areas are selected. Add one or more areas above to define this Job Title's default route.</div>
        <?php endif; ?>
    </div>
</section>
</div>
<?php include '../includes/footer.php'; ?>