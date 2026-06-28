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
| Operations Service Options
|--------------------------------------------------------------------------
*/

$operationsServiceOptions = [
    'Covered Hopper' => [
        'Grain',
        'Cement',
        'Sand / Gravel',
        'Plastic Pellets',
        'Feed',
        'Flour',
        'Fertilizer'
    ],
    'Open Hopper' => [
        'Coal',
        'Gravel',
        'Sand',
        'Aggregate',
        'Ore',
        'Ballast'
    ],
    'Gondola' => [
        'Scrap Metal',
        'Steel / Pipe',
        'Rail',
        'Aggregate',
        'MOW / Riprap'
    ],
    'Tank Car' => [
        'Vegetable Oil',
        'Food Grade Liquid',
        'Fuel',
        'Propane',
        'Chemicals',
        'Asphalt',
        'Corn Syrup'
    ],
    'Boxcar' => [
        'General Freight',
        'Paper',
        'Food Products',
        'Beer',
        'Auto Parts',
        'Furniture',
        'Appliances'
    ],
    'Refrigerator Car' => [
        'General Freight',
        'Food Products'
    ],
    'Mechanical Refrigerator Car' => [
        'General Freight',
        'Food Products'
    ],
    'Flatcar' => [
        'Lumber',
        'Building Materials',
        'Pipe',
        'Machinery',
        'Steel'
    ],
    'Bulkhead Flatcar' => [
        'Lumber',
        'Building Materials',
        'Pipe',
        'Machinery',
        'Steel'
    ],
    'Centerbeam Flatcar' => [
        'Lumber',
        'Building Materials',
        'Pipe',
        'Machinery',
        'Steel'
    ]
];

function ttAddOperationsServiceOption(array &$options, string $equipmentType, string $serviceName): void
{
    $equipmentType = trim($equipmentType);
    $serviceName = trim($serviceName);

    if ($equipmentType === '' || $serviceName === '') {
        return;
    }

    if (!isset($options[$equipmentType])) {
        $options[$equipmentType] = [];
    }

    foreach ($options[$equipmentType] as $existingServiceName) {
        if (strcasecmp($existingServiceName, $serviceName) === 0) {
            return;
        }
    }

    $options[$equipmentType][] = $serviceName;
}

function ttServiceOptionExists(array $options, string $equipmentType, string $serviceName): bool
{
    foreach ($options[$equipmentType] ?? [] as $existingServiceName) {
        if (strcasecmp($existingServiceName, $serviceName) === 0) {
            return true;
        }
    }

    return false;
}

function ttIsLocomotive(array $car): bool
{
    return strcasecmp($car['equipment_class'] ?? '', 'Locomotive') === 0;
}

$stmt = $pdo->prepare("
    SELECT
        equipment_type,
        service_name
    FROM operations_service_options
    WHERE railroad_id = ?
    OR is_default = 1
    ORDER BY equipment_type, service_name
");

$stmt->execute([
    $railroad['id']
]);

foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $serviceOption) {
    ttAddOperationsServiceOption(
        $operationsServiceOptions,
        $serviceOption['equipment_type'] ?? '',
        $serviceOption['service_name'] ?? ''
    );
}

/*
|--------------------------------------------------------------------------
| Update Visible Car Operating Statuses
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $carRows = $_POST['cars'] ?? [];
    $updatedCount = 0;

    if (is_array($carRows) && !empty($carRows)) {
        $updateStmt = $pdo->prepare("
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

        $existingServiceStmt = $pdo->prepare("
            SELECT id
            FROM operations_service_options
            WHERE railroad_id = ?
            AND LOWER(equipment_type) = LOWER(?)
            AND LOWER(service_name) = LOWER(?)
            LIMIT 1
        ");

        $insertServiceStmt = $pdo->prepare("
            INSERT INTO operations_service_options (
                railroad_id,
                equipment_type,
                service_name,
                is_default
            )
            VALUES (?, ?, ?, 0)
        ");

        foreach ($carRows as $equipmentId => $row) {
            if (!is_array($row)) {
                continue;
            }

            $equipmentId = (int)$equipmentId;
            $active = ($row['active'] ?? '0') === '1' ? 1 : 0;
            $currentIndustryId = !empty($row['current_industry_id'])
                ? (int)$row['current_industry_id']
                : null;
            $currentTrack = substr(trim($row['current_track'] ?? ''), 0, 50);
            $loadStatus = substr(trim($row['load_status'] ?? ''), 0, 20);
            $equipmentType = substr(trim($row['equipment_type'] ?? ''), 0, 100);
            $operationsService = trim($row['operations_service'] ?? '');
            $operationsServiceCustom = substr(trim($row['operations_service_custom'] ?? ''), 0, 100);
            $customOperationsServiceEntered = $operationsService === 'Other'
                && $operationsServiceCustom !== '';

            if ($operationsService === 'Other') {
                $operationsService = $operationsServiceCustom;
            }

            $operationsService = substr(trim($operationsService), 0, 100);

            if ($equipmentId <= 0) {
                continue;
            }

            $updateStmt->execute([
                ':active' => $active,
                ':current_industry_id' => $currentIndustryId,
                ':current_track' => $currentTrack,
                ':load_status' => $loadStatus,
                ':operations_service' => $operationsService,
                ':equipment_id' => $equipmentId,
                ':railroad_id' => $railroad['id']
            ]);

            if ($updateStmt->rowCount() > 0) {
                $updatedCount++;
            }

            if (
                $customOperationsServiceEntered
                && $operationsService !== ''
                && $equipmentType !== ''
            ) {
                $existingServiceStmt->execute([
                    $railroad['id'],
                    $equipmentType,
                    $operationsService
                ]);

                if (!$existingServiceStmt->fetchColumn()) {
                    $insertServiceStmt->execute([
                        $railroad['id'],
                        $equipmentType,
                        $operationsService
                    ]);
                }
            }
        }
    }

    $_SESSION['status_message'] = $updatedCount === 1
        ? '1 car status updated.'
        : $updatedCount . ' car statuses updated.';

    $returnQuery = str_replace(["\r", "\n"], '', $_POST['return_query'] ?? '');
    header('Location: status.php' . ($returnQuery !== '' ? '?' . $returnQuery : '') . '#status-board');
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
    $where[] = 'COALESCE(e.active, 0) = 0';
}

if (in_array('missing_service', $statusFilters, true)) {
    $where[] = "e.equipment_class = 'Freight Car'";
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
        SUM(CASE WHEN COALESCE(active, 0) = 0 THEN 1 ELSE 0 END) AS inactive_cars,
        SUM(CASE WHEN active = 1
            AND current_industry_id IS NOT NULL
            AND current_industry_id <> 0
            AND TRIM(COALESCE(operations_service, '')) <> ''
            THEN 1 ELSE 0 END) AS ready_cars,
        SUM(CASE WHEN equipment_class = 'Freight Car'
            AND TRIM(COALESCE(operations_service, '')) = ''
            THEN 1 ELSE 0 END) AS missing_service,
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
<div class="text-muted small">Freight Missing Service</div>
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

<div class="content-card" id="status-board">

<div class="mb-4">
    <h1 class="mb-1">Car Status</h1>
    <div class="text-muted mb-2">
        Active cars are on the layout and available for Generate Session. Inactive cars are stored off-layout.
    </div>
    <div class="text-muted">
        Showing <strong><?= count($cars) ?></strong> of <strong><?= $totalRecords ?></strong> cars
    </div>
</div>

<form method="post" id="statusBulkForm">
<input type="hidden" name="return_query" value="<?= htmlspecialchars($returnQuery) ?>">

<!-- TOP TOOLBAR -->

<div class="top-toolbar">
<div class="toolbar-left">
    <button type="submit" class="btn btn-success">Save All Changes</button>
</div>
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
$equipmentId = (int)$car['id'];
$isLocomotive = ttIsLocomotive($car);
$isReady = (int)$car['active'] === 1
    && !empty($car['current_industry_id'])
    && trim($car['operations_service'] ?? '') !== '';
$serviceOptions = $operationsServiceOptions[$car['equipment_type']] ?? [];
$currentService = trim($car['operations_service'] ?? '');
$matchedService = $currentService === ''
    || ttServiceOptionExists($operationsServiceOptions, $car['equipment_type'] ?? '', $currentService);
$customServiceVisible = $currentService !== '' && !$matchedService;
?>
<tr class="<?= (int)$car['active'] === 1 ? '' : 'table-light' ?>">

<td>
    <strong><?= htmlspecialchars($car['car']) ?></strong>
    <div class="text-muted small">
        <?= htmlspecialchars($car['road_name'] ?: '-') ?>
        <?php if (!empty($car['equipment_class'])): ?>
            - <?= htmlspecialchars($car['equipment_class']) ?>
        <?php endif; ?>
    </div>
</td>

<td style="min-width: 150px;">
    <input type="hidden" name="cars[<?= $equipmentId ?>][equipment_type]" value="<?= htmlspecialchars($car['equipment_type'] ?? '') ?>">
    <select name="cars[<?= $equipmentId ?>][active]" class="form-select form-select-sm">
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
    <?php elseif (!$isLocomotive && trim($car['operations_service'] ?? '') === ''): ?>
        <span class="badge bg-warning text-dark">Needs Service</span>
    <?php elseif ($isLocomotive): ?>
        <span class="badge bg-secondary">On Layout</span>
    <?php else: ?>
        <span class="badge bg-secondary">Review</span>
    <?php endif; ?>
</td>

<td style="min-width: 190px;">
    <select name="cars[<?= $equipmentId ?>][current_industry_id]" class="form-select form-select-sm">
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
    name="cars[<?= $equipmentId ?>][current_track]"
    maxlength="50"
    class="form-control form-control-sm"
    value="<?= htmlspecialchars($car['current_track'] ?? '') ?>"
    placeholder="Track / spot">
</td>

<td style="min-width: 120px;">
    <select name="cars[<?= $equipmentId ?>][load_status]" class="form-select form-select-sm">
        <?php foreach ($loadOptions as $option): ?>
        <option value="<?= htmlspecialchars($option) ?>" <?= $car['load_status'] === $option ? 'selected' : '' ?>>
            <?= htmlspecialchars($option) ?>
        </option>
        <?php endforeach; ?>
    </select>
</td>

<td style="min-width: 220px;">
    <select
    name="cars[<?= $equipmentId ?>][operations_service]"
    class="form-select form-select-sm status-service-select"
    data-custom-target="status-service-custom-<?= $equipmentId ?>">
        <option value="">Select Service</option>
        <?php foreach ($serviceOptions as $option): ?>
        <option value="<?= htmlspecialchars($option) ?>" <?= strcasecmp($currentService, $option) === 0 ? 'selected' : '' ?>>
            <?= htmlspecialchars($option) ?>
        </option>
        <?php endforeach; ?>
        <option value="Other" <?= $customServiceVisible ? 'selected' : '' ?>>Other</option>
    </select>
    <input
    type="text"
    name="cars[<?= $equipmentId ?>][operations_service_custom]"
    id="status-service-custom-<?= $equipmentId ?>"
    maxlength="100"
    class="form-control form-control-sm mt-2 status-service-custom"
    value="<?= htmlspecialchars($customServiceVisible ? $currentService : '') ?>"
    placeholder="Custom operations service"
    style="<?= $customServiceVisible ? '' : 'display:none;' ?>">
</td>

<td>
    <?= htmlspecialchars($car['equipment_type'] ?: '-') ?>
    <?php if (!empty($car['road_number'])): ?>
    <div class="text-muted small">No. <?= htmlspecialchars($car['road_number']) ?></div>
    <?php endif; ?>
</td>

<td>
    <a href="view.php?id=<?= $equipmentId ?>" class="btn btn-sm btn-outline-secondary">View</a>
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

<div class="d-flex justify-content-end mt-3">
    <button type="submit" class="btn btn-success">Save All Changes</button>
</div>

</form>

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
<script>
document.querySelectorAll('.status-service-select').forEach(function(select){
    function toggleCustomService(){
        var target = document.getElementById(select.dataset.customTarget);
        if (!target) {
            return;
        }

        target.style.display = select.value === 'Other' ? '' : 'none';

        if (select.value !== 'Other') {
            target.value = '';
        }
    }

    select.addEventListener('change', toggleCustomService);
    toggleCustomService();
});
</script>

<?php include '../includes/footer.php'; ?>
