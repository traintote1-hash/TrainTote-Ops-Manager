<?php

session_start();

require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Railroad
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT id
    FROM railroads
    WHERE user_id = ?
    LIMIT 1
");

$stmt->execute([$_SESSION['user_id']]);

$railroad = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$railroad) {
    die('No railroad found.');
}

/*
|--------------------------------------------------------------------------
| Update Car Operating Status
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $equipmentId = (int)($_POST['equipment_id'] ?? 0);
    $active = ($_POST['active'] ?? '0') === '1' ? 1 : 0;
    $currentIndustryId = !empty($_POST['current_industry_id'])
        ? (int)$_POST['current_industry_id']
        : null;
    $currentTrack = substr(trim($_POST['current_track'] ?? ''), 0, 50);
    $loadStatus = substr(trim($_POST['load_status'] ?? ''), 0, 20);
    $operationsService = substr(trim($_POST['operations_service'] ?? ''), 0, 100);

    if ($equipmentId > 0) {
        $stmt = $pdo->prepare("
            UPDATE equipment
            SET
                active = :active,
                current_industry_id = :current_industry_id,
                current_track = :current_track,
                load_status = :load_status,
                operations_service = :operations_service
            WHERE id = :equipment_id
            AND railroad_id = :railroad_id
        ");

        $stmt->execute([
            ':active' => $active,
            ':current_industry_id' => $currentIndustryId,
            ':current_track' => $currentTrack,
            ':load_status' => $loadStatus,
            ':operations_service' => $operationsService,
            ':equipment_id' => $equipmentId,
            ':railroad_id' => $railroad['id']
        ]);

        $_SESSION['status_message'] = 'Car status updated.';
    }

    $returnQuery = str_replace(["\r", "\n"], '', $_POST['return_query'] ?? '');
    header('Location: status.php' . ($returnQuery !== '' ? '?' . $returnQuery : ''));
    exit;
}

$statusMessage = $_SESSION['status_message'] ?? '';
unset($_SESSION['status_message']);

/*
|--------------------------------------------------------------------------
| Search
|--------------------------------------------------------------------------
*/

$search = trim($_GET['search'] ?? '');

/*
|--------------------------------------------------------------------------
| Filters
|--------------------------------------------------------------------------
*/

$statusFilters = array_values(array_filter(array_map('strval', (array)($_GET['status'] ?? []))));
$locationFilters = array_values(array_filter(array_map('strval', (array)($_GET['location'] ?? []))));
$loadFilters = array_values(array_filter(array_map('strval', (array)($_GET['load_status'] ?? []))));

$allowedStatusFilters = [
    'active',
    'inactive',
    'missing_service',
    'missing_location',
    'ready'
];

$statusFilters = array_values(array_intersect($statusFilters, $allowedStatusFilters));

/*
|--------------------------------------------------------------------------
| Sorting
|--------------------------------------------------------------------------
*/

$allowedSorts = [
    'car',
    'active',
    'ready',
    'location',
    'current_track',
    'load_status',
    'operations_service',
    'equipment_type',
    'road_name'
];

$sort = $_GET['sort'] ?? 'active';

if (!in_array($sort, $allowedSorts)) {
    $sort = 'active';
}

$dir = strtolower($_GET['dir'] ?? 'asc');

if (!in_array($dir, ['asc', 'desc'])) {
    $dir = 'asc';
}

$orderBy = match ($sort) {
    'car' => "CONCAT(e.reporting_marks, ' ', e.road_number)",
    'ready' => "CASE WHEN e.active = 1 AND e.current_industry_id IS NOT NULL AND e.current_industry_id <> 0 AND TRIM(COALESCE(e.operations_service, '')) <> '' THEN 0 ELSE 1 END",
    'location' => 'COALESCE(i.industry_name, "ZZZZ")',
    'current_track' => 'e.current_track',
    'load_status' => 'e.load_status',
    'operations_service' => 'e.operations_service',
    'equipment_type' => 'e.equipment_type',
    'road_name' => 'e.road_name',
    default => 'e.active'
};

$orderBy .= ' ' . strtoupper($dir);

if ($sort === 'active') {
    $orderBy .= ", COALESCE(i.industry_name, 'ZZZZ'), e.reporting_marks, e.road_number";
}

/*
|--------------------------------------------------------------------------
| Per Page
|--------------------------------------------------------------------------
*/

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = $_GET['per_page'] ?? 20;

if ($perPage !== 'all') {
    $perPage = (int)$perPage;
    if (!in_array($perPage, [10, 20, 50, 100])) {
        $perPage = 20;
    }
}

/*
|--------------------------------------------------------------------------
| Filter option lists
|--------------------------------------------------------------------------
*/

$locationStmt = $pdo->prepare("
    SELECT id, industry_name
    FROM industries
    WHERE railroad_id = :railroad_id
    ORDER BY industry_name
");

$locationStmt->execute([':railroad_id' => $railroad['id']]);
$industryOptions = $locationStmt->fetchAll(PDO::FETCH_ASSOC);
$locationOptions = array_column($industryOptions, 'industry_name');

$loadStmt = $pdo->prepare("
    SELECT DISTINCT load_status
    FROM equipment
    WHERE railroad_id = :railroad_id
    AND load_status <> ''
    ORDER BY load_status
");

$loadStmt->execute([':railroad_id' => $railroad['id']]);
$loadOptions = array_values(array_unique(array_merge(
    ['Empty', 'Loaded'],
    $loadStmt->fetchAll(PDO::FETCH_COLUMN)
)));

$serviceStmt = $pdo->prepare("
    SELECT DISTINCT operations_service
    FROM equipment
    WHERE railroad_id = :railroad_id
    AND operations_service <> ''
    ORDER BY operations_service
");

$serviceStmt->execute([':railroad_id' => $railroad['id']]);
$operationsServiceOptions = $serviceStmt->fetchAll(PDO::FETCH_COLUMN);

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function buildInClause(string $column, array $values, string $prefix, array &$params): string
{
    $placeholders = [];
    foreach ($values as $i => $v) {
        $key = ':' . $prefix . '_' . $i;
        $placeholders[] = $key;
        $params[$key] = $v;
    }
    return $column . ' IN (' . implode(',', $placeholders) . ')';
}

function filterUrl(array $overrides): string
{
    return '?' . http_build_query(array_merge($_GET, $overrides));
}

function statusFilterLabel(string $filter): string
{
    return match ($filter) {
        'active' => 'Active Cars',
        'inactive' => 'Inactive Cars',
        'missing_service' => 'Missing Operations Service',
        'missing_location' => 'Missing Location',
        'ready' => 'Ready for Session',
        default => $filter
    };
}

/*
|--------------------------------------------------------------------------
| Base WHERE + params
|--------------------------------------------------------------------------
*/

$where = ['e.railroad_id = :railroad_id'];
$params = [':railroad_id' => $railroad['id']];

if ($search !== '') {
    $where[] = "(
        CONCAT(e.reporting_marks, ' ', e.road_number) LIKE :search
        OR e.road_name LIKE :search
        OR e.equipment_type LIKE :search
        OR e.current_track LIKE :search
        OR e.load_status LIKE :search
        OR e.operations_service LIKE :search
        OR i.industry_name LIKE :search
    )";
    $params[':search'] = '%' . $search . '%';
}

if (!empty($locationFilters)) {
    $where[] = buildInClause('i.industry_name', $locationFilters, 'loc', $params);
}

if (!empty($loadFilters)) {
    $where[] = buildInClause('e.load_status', $loadFilters, 'load', $params);
}

$activeSelected = in_array('active', $statusFilters, true);
$inactiveSelected = in_array('inactive', $statusFilters, true);

if ($activeSelected && !$inactiveSelected) {
    $where[] = 'e.active = 1';
}
elseif ($inactiveSelected && !$activeSelected) {
    $where[] = 'e.active = 0';
}

if (in_array('missing_service', $statusFilters, true)) {
    $where[] = "TRIM(COALESCE(e.operations_service, '')) = ''";
}

if (in_array('missing_location', $statusFilters, true)) {
    $where[] = '(e.current_industry_id IS NULL OR e.current_industry_id = 0)';
}

if (in_array('ready', $statusFilters, true)) {
    $where[] = 'e.active = 1';
    $where[] = 'e.current_industry_id IS NOT NULL';
    $where[] = 'e.current_industry_id <> 0';
    $where[] = "TRIM(COALESCE(e.operations_service, '')) <> ''";
}

$whereSQL = implode(' AND ', $where);

/*
|--------------------------------------------------------------------------
| Count Query
|--------------------------------------------------------------------------
*/

$countSql = "
    SELECT COUNT(*)
    FROM equipment e
    LEFT JOIN industries i ON e.current_industry_id = i.id
    WHERE $whereSQL
";

$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalRecords = (int)$countStmt->fetchColumn();

/*
|--------------------------------------------------------------------------
| Pagination
|--------------------------------------------------------------------------
*/

$totalPages = 1;

if ($perPage !== 'all') {
    $totalPages = max(1, (int)ceil($totalRecords / $perPage));
    if ($page > $totalPages) {
        $page = $totalPages;
    }
    $offset = ($page - 1) * $perPage;
}

/*
|--------------------------------------------------------------------------
| Main Query
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        e.id,
        CONCAT(e.reporting_marks, ' ', e.road_number) AS car,
        e.reporting_marks,
        e.road_number,
        e.road_name,
        e.equipment_class,
        e.equipment_type,
        e.active,
        e.current_industry_id,
        e.current_track,
        e.load_status,
        e.operations_service,
        i.industry_name
    FROM equipment e
    LEFT JOIN industries i ON e.current_industry_id = i.id
    WHERE $whereSQL
    ORDER BY $orderBy
";

if ($perPage !== 'all') {
    $sql .= " LIMIT $offset, $perPage";
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$cars = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Summary Counts
|--------------------------------------------------------------------------
*/

$summaryStmt = $pdo->prepare("
    SELECT
        COUNT(*) AS total_cars,
        SUM(CASE WHEN active = 1 THEN 1 ELSE 0 END) AS active_cars,
        SUM(CASE WHEN active = 0 THEN 1 ELSE 0 END) AS inactive_cars,
        SUM(CASE WHEN active = 1
            AND current_industry_id IS NOT NULL
            AND current_industry_id <> 0
            AND TRIM(COALESCE(operations_service, '')) <> ''
            THEN 1 ELSE 0 END) AS ready_cars,
        SUM(CASE WHEN TRIM(COALESCE(operations_service, '')) = '' THEN 1 ELSE 0 END) AS missing_service,
        SUM(CASE WHEN current_industry_id IS NULL OR current_industry_id = 0 THEN 1 ELSE 0 END) AS missing_location
    FROM equipment
    WHERE railroad_id = :railroad_id
");

$summaryStmt->execute([':railroad_id' => $railroad['id']]);
$summary = $summaryStmt->fetch(PDO::FETCH_ASSOC);

$returnQuery = $_SERVER['QUERY_STRING'] ?? '';

?>

<?php include '../includes/header.php'; ?>

<title>Car Status</title>

<link rel="stylesheet" href="../assets/css/list_v2.css">

</head>

<body>

<?php include '../includes/navbar.php'; ?>

<div class="container-fluid mt-4">

<?php if ($statusMessage !== ''): ?>
<div class="alert alert-success">
    <?= htmlspecialchars($statusMessage) ?>
</div>
<?php endif; ?>

<!-- SUMMARY CARDS -->

<div class="row mb-4 g-3">

<div class="col-6 col-md-2">
<div class="card text-center h-100">
<div class="card-body">
<h2 class="mb-0"><?= (int)$summary['total_cars'] ?></h2>
<div class="text-muted small">Total Cars</div>
</div>
</div>
</div>

<div class="col-6 col-md-2">
<div class="card text-center h-100">
<div class="card-body">
<h2 class="mb-0"><?= (int)$summary['active_cars'] ?></h2>
<div class="text-muted small">Active / On Layout</div>
</div>
</div>
</div>

<div class="col-6 col-md-2">
<div class="card text-center h-100">
<div class="card-body">
<h2 class="mb-0"><?= (int)$summary['inactive_cars'] ?></h2>
<div class="text-muted small">Inactive / Off Layout</div>
</div>
</div>
</div>

<div class="col-6 col-md-2">
<div class="card text-center h-100">
<div class="card-body">
<h2 class="mb-0"><?= (int)$summary['ready_cars'] ?></h2>
<div class="text-muted small">Ready for Session</div>
</div>
</div>
</div>

<div class="col-6 col-md-2">
<div class="card text-center h-100">
<div class="card-body">
<h2 class="mb-0"><?= (int)$summary['missing_service'] ?></h2>
<div class="text-muted small">Missing Service</div>
</div>
</div>
</div>

<div class="col-6 col-md-2">
<div class="card text-center h-100">
<div class="card-body">
<h2 class="mb-0"><?= (int)$summary['missing_location'] ?></h2>
<div class="text-muted small">Missing Location</div>
</div>
</div>
</div>

</div>

<!-- MAIN LIST LAYOUT -->

<div class="list-layout">

<!-- =====================================================
SIDEBAR
===================================================== -->

<aside class="sidebar">

<div class="sidebar-card">

<form method="get" id="filterForm">

<h5 class="mb-3">Filters</h5>

<!-- ACTIVE FILTER CHIPS -->

<div class="active-filters">

<div class="d-flex justify-content-between align-items-center mb-2">
    <strong>Now Filtering</strong>
    <a href="status.php" class="btn btn-sm btn-outline-secondary">Clear All</a>
</div>

<?php foreach ($statusFilters as $filter): ?>
<a class="filter-chip" href="<?= filterUrl(['status' => array_values(array_diff($statusFilters, [$filter])), 'page' => 1]) ?>">
    &times; <?= htmlspecialchars(statusFilterLabel($filter)) ?>
</a>
<?php endforeach; ?>

<?php foreach ($locationFilters as $filter): ?>
<a class="filter-chip" href="<?= filterUrl(['location' => array_values(array_diff($locationFilters, [$filter])), 'page' => 1]) ?>">
    &times; <?= htmlspecialchars($filter) ?>
</a>
<?php endforeach; ?>

<?php foreach ($loadFilters as $filter): ?>
<a class="filter-chip" href="<?= filterUrl(['load_status' => array_values(array_diff($loadFilters, [$filter])), 'page' => 1]) ?>">
    &times; <?= htmlspecialchars($filter) ?>
</a>
<?php endforeach; ?>

</div>

<!-- SEARCH -->

<div class="filter-section">
<div class="section-header"><span class="arrow">►</span> Search</div>
<div class="section-content collapsed">
    <input type="text" name="search" class="form-control form-control-sm"
        value="<?= htmlspecialchars($search) ?>"
        placeholder="Car, location, service...">
    <button type="submit" class="btn btn-sm btn-primary mt-2 w-100">Go</button>
</div>
</div>

<!-- OPERATING STATUS -->

<div class="filter-section">
<div class="section-header"><span class="arrow">▼</span> Operating Status</div>
<div class="section-content">
<?php foreach ($allowedStatusFilters as $option): ?>
<label class="form-check">
    <input class="form-check-input auto-filter" type="checkbox" name="status[]"
        value="<?= htmlspecialchars($option) ?>"
        <?= in_array($option, $statusFilters, true) ? 'checked' : '' ?>>
    <span class="form-check-label"><?= htmlspecialchars(statusFilterLabel($option)) ?></span>
</label>
<?php endforeach; ?>
</div>
</div>

<!-- LOCATION -->

<div class="filter-section">
<div class="section-header"><span class="arrow">►</span> Location</div>
<div class="section-content collapsed filter-scroll">
<?php foreach ($locationOptions as $option): ?>
<label class="form-check">
    <input class="form-check-input auto-filter" type="checkbox" name="location[]"
        value="<?= htmlspecialchars($option) ?>"
        <?= in_array($option, $locationFilters, true) ? 'checked' : '' ?>>
    <span class="form-check-label"><?= htmlspecialchars($option) ?></span>
</label>
<?php endforeach; ?>
</div>
</div>

<!-- LOAD STATUS -->

<div class="filter-section">
<div class="section-header"><span class="arrow">►</span> Load Status</div>
<div class="section-content collapsed">
<?php foreach ($loadOptions as $option): ?>
<label class="form-check">
    <input class="form-check-input auto-filter" type="checkbox" name="load_status[]"
        value="<?= htmlspecialchars($option) ?>"
        <?= in_array($option, $loadFilters, true) ? 'checked' : '' ?>>
    <span class="form-check-label"><?= htmlspecialchars($option) ?></span>
</label>
<?php endforeach; ?>
</div>
</div>

</form>

</div>

</aside>

<!-- =====================================================
MAIN CONTENT
===================================================== -->

<main class="main-content">

<div class="content-card">

<div class="mb-4">
    <h1 class="mb-1">Car Status</h1>
    <div class="text-muted mb-2">
        Active cars are on the layout and available for Generate Session. Inactive cars are stored off-layout.
    </div>
    <div class="text-muted">
        Showing <strong><?= count($cars) ?></strong> of <strong><?= $totalRecords ?></strong> cars
    </div>
</div>

<!-- TOP TOOLBAR -->

<div class="top-toolbar">
<div class="toolbar-right ms-auto"></div>
<div class="toolbar-right">
    <label class="small me-2">Show</label>
    <select id="perPage" class="form-select form-select-sm">
        <option value="10"  <?= $perPage == 10 ? 'selected' : '' ?>>10</option>
        <option value="20"  <?= $perPage == 20 ? 'selected' : '' ?>>20</option>
        <option value="50"  <?= $perPage == 50 ? 'selected' : '' ?>>50</option>
        <option value="100" <?= $perPage == 100 ? 'selected' : '' ?>>100</option>
        <option value="all" <?= $perPage === 'all' ? 'selected' : '' ?>>All</option>
    </select>
</div>
</div>

<datalist id="operationsServiceOptions">
<?php foreach ($operationsServiceOptions as $option): ?>
    <option value="<?= htmlspecialchars($option) ?>">
<?php endforeach; ?>
</datalist>

<div class="table-responsive">

<table class="table table-hover align-middle equipment-table">

<thead>
<tr>
<th>
<a class="sort-link" href="<?= filterUrl(['sort' => 'car', 'dir' => ($sort === 'car' && $dir === 'asc') ? 'desc' : 'asc']) ?>">
    Car ▲▼
</a>
</th>
<th>
<a class="sort-link" href="<?= filterUrl(['sort' => 'active', 'dir' => ($sort === 'active' && $dir === 'asc') ? 'desc' : 'asc']) ?>">
    Active ▲▼
</a>
</th>
<th>
<a class="sort-link" href="<?= filterUrl(['sort' => 'ready', 'dir' => ($sort === 'ready' && $dir === 'asc') ? 'desc' : 'asc']) ?>">
    Ready ▲▼
</a>
</th>
<th>
<a class="sort-link" href="<?= filterUrl(['sort' => 'location', 'dir' => ($sort === 'location' && $dir === 'asc') ? 'desc' : 'asc']) ?>">
    Location ▲▼
</a>
</th>
<th>
<a class="sort-link" href="<?= filterUrl(['sort' => 'current_track', 'dir' => ($sort === 'current_track' && $dir === 'asc') ? 'desc' : 'asc']) ?>">
    Track ▲▼
</a>
</th>
<th>
<a class="sort-link" href="<?= filterUrl(['sort' => 'load_status', 'dir' => ($sort === 'load_status' && $dir === 'asc') ? 'desc' : 'asc']) ?>">
    Load ▲▼
</a>
</th>
<th>
<a class="sort-link" href="<?= filterUrl(['sort' => 'operations_service', 'dir' => ($sort === 'operations_service' && $dir === 'asc') ? 'desc' : 'asc']) ?>">
    Operations Service ▲▼
</a>
</th>
<th>
<a class="sort-link" href="<?= filterUrl(['sort' => 'equipment_type', 'dir' => ($sort === 'equipment_type' && $dir === 'asc') ? 'desc' : 'asc']) ?>">
    Type ▲▼
</a>
</th>
<th></th>
</tr>
</thead>

<tbody>

<?php foreach ($cars as $car): ?>
<?php
$formId = 'car-status-form-' . (int)$car['id'];
$isReady = (int)$car['active'] === 1
    && !empty($car['current_industry_id'])
    && trim($car['operations_service'] ?? '') !== '';
?>
<tr class="<?= (int)$car['active'] === 1 ? '' : 'table-light' ?>">

<td>
    <strong><?= htmlspecialchars($car['car']) ?></strong>
    <div class="text-muted small">
        <?= htmlspecialchars($car['road_name'] ?: '-') ?>
        <?php if (!empty($car['equipment_class'])): ?>
            · <?= htmlspecialchars($car['equipment_class']) ?>
        <?php endif; ?>
    </div>
</td>

<td style="min-width: 150px;">
    <select name="active" class="form-select form-select-sm" form="<?= $formId ?>">
        <option value="1" <?= (int)$car['active'] === 1 ? 'selected' : '' ?>>Active - On Layout</option>
        <option value="0" <?= (int)$car['active'] === 0 ? 'selected' : '' ?>>Inactive - Off Layout</option>
    </select>
</td>

<td>
    <?php if ($isReady): ?>
        <span class="badge bg-success">Ready</span>
    <?php elseif ((int)$car['active'] !== 1): ?>
        <span class="badge bg-secondary">Inactive</span>
    <?php elseif (empty($car['current_industry_id'])): ?>
        <span class="badge bg-warning text-dark">Needs Location</span>
    <?php elseif (trim($car['operations_service'] ?? '') === ''): ?>
        <span class="badge bg-warning text-dark">Needs Service</span>
    <?php else: ?>
        <span class="badge bg-secondary">Review</span>
    <?php endif; ?>
</td>

<td style="min-width: 190px;">
    <select name="current_industry_id" class="form-select form-select-sm" form="<?= $formId ?>">
        <option value="">No location</option>
        <?php foreach ($industryOptions as $industry): ?>
        <option value="<?= (int)$industry['id'] ?>" <?= (string)$car['current_industry_id'] === (string)$industry['id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($industry['industry_name']) ?>
        </option>
        <?php endforeach; ?>
    </select>
</td>

<td style="min-width: 130px;">
    <input
    type="text"
    name="current_track"
    maxlength="50"
    class="form-control form-control-sm"
    value="<?= htmlspecialchars($car['current_track'] ?? '') ?>"
    placeholder="Track / spot"
    form="<?= $formId ?>">
</td>

<td style="min-width: 120px;">
    <select name="load_status" class="form-select form-select-sm" form="<?= $formId ?>">
        <?php foreach ($loadOptions as $option): ?>
        <option value="<?= htmlspecialchars($option) ?>" <?= $car['load_status'] === $option ? 'selected' : '' ?>>
            <?= htmlspecialchars($option) ?>
        </option>
        <?php endforeach; ?>
    </select>
</td>

<td style="min-width: 190px;">
    <input
    type="text"
    name="operations_service"
    maxlength="100"
    class="form-control form-control-sm"
    list="operationsServiceOptions"
    value="<?= htmlspecialchars($car['operations_service'] ?? '') ?>"
    placeholder="Operations Service"
    form="<?= $formId ?>">
</td>

<td>
    <?= htmlspecialchars($car['equipment_type'] ?: '-') ?>
    <?php if (!empty($car['road_number'])): ?>
    <div class="text-muted small">No. <?= htmlspecialchars($car['road_number']) ?></div>
    <?php endif; ?>
</td>

<td>
    <form id="<?= $formId ?>" method="post" class="d-flex gap-2 align-items-center">
        <input type="hidden" name="equipment_id" value="<?= (int)$car['id'] ?>">
        <input type="hidden" name="return_query" value="<?= htmlspecialchars($returnQuery) ?>">
        <button type="submit" class="btn btn-sm btn-primary">Save</button>
        <a href="view.php?id=<?= (int)$car['id'] ?>" class="btn btn-sm btn-outline-secondary">View</a>
    </form>
</td>

</tr>

<?php endforeach; ?>

<?php if (empty($cars)): ?>
<tr>
    <td colspan="9" class="text-center text-muted py-4">No cars match the current filters.</td>
</tr>
<?php endif; ?>

</tbody>

</table>

</div>

<hr>

<div class="pagination-area text-center">

<?php if ($perPage !== 'all' && $totalPages > 1): ?>

    <?php if ($page > 1): ?>
        <a class="btn btn-outline-secondary btn-sm"
           href="<?= filterUrl(['page' => $page - 1]) ?>">
            &lt;
        </a>
    <?php endif; ?>

    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <a class="btn btn-sm <?= $i === $page ? 'btn-primary' : 'btn-outline-secondary' ?>"
           href="<?= filterUrl(['page' => $i]) ?>">
            <?= $i ?>
        </a>
    <?php endfor; ?>

    <?php if ($page < $totalPages): ?>
        <a class="btn btn-outline-secondary btn-sm"
           href="<?= filterUrl(['page' => $page + 1]) ?>">
            &gt;
        </a>
    <?php endif; ?>

<?php endif; ?>

</div>

</div>

</main>

</div>

</div>

<script src="../assets/js/list_v2.js"></script>

<?php include '../includes/footer.php'; ?>
