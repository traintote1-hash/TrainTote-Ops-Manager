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
| Search
|--------------------------------------------------------------------------
*/

$search = trim($_GET['search'] ?? '');

/*
|--------------------------------------------------------------------------
| Filters
|--------------------------------------------------------------------------
*/

$locationFilters  = array_values(array_filter(array_map('strval', (array)($_GET['location']    ?? []))));
$loadFilters      = array_values(array_filter(array_map('strval', (array)($_GET['load_status'] ?? []))));
$waybillFilters   = array_values(array_filter(array_map('strval', (array)($_GET['waybill']     ?? []))));
$moveFilters      = array_values(array_filter(array_map('strval', (array)($_GET['move']        ?? []))));

/*
|--------------------------------------------------------------------------
| Sorting
|--------------------------------------------------------------------------
*/

$allowedSorts = [
    'car',
    'location',
    'current_track',
    'load_status',
    'commodity',
    'destination',
    'cycle',
    'move'
];

$sort = $_GET['sort'] ?? 'location';

if (!in_array($sort, $allowedSorts)) {
    $sort = 'location';
}

$dir = strtolower($_GET['dir'] ?? 'asc');

if (!in_array($dir, ['asc', 'desc'])) {
    $dir = 'asc';
}

$orderBy = match ($sort) {
    'car'           => 'car',
    'current_track' => 'e.current_track',
    'load_status'   => 'e.load_status',
    'commodity'     => 'w.commodity',
    'destination'   => 'd.industry_name',
    'cycle'         => 'w.current_cycle',
    'move'          => '(w.destination_industry_id IS NOT NULL AND w.destination_industry_id != e.current_industry_id) DESC',
    default         => 'COALESCE(i.industry_name, "ZZZZ")',
};

$orderBy .= match ($sort) {
    'move'  => '',   // already has direction embedded
    default => ' ' . strtoupper($dir),
};

/*
|--------------------------------------------------------------------------
| Per Page
|--------------------------------------------------------------------------
*/

$page    = max(1, (int)($_GET['page'] ?? 1));
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

$locationOptions = $pdo->query("
    SELECT DISTINCT industry_name
    FROM industries
    ORDER BY industry_name
")->fetchAll(PDO::FETCH_COLUMN);

$loadOptions = $pdo->query("
    SELECT DISTINCT load_status
    FROM equipment
    WHERE load_status <> ''
    ORDER BY load_status
")->fetchAll(PDO::FETCH_COLUMN);

/*
|--------------------------------------------------------------------------
| Helper: safe IN() clause
|--------------------------------------------------------------------------
*/

function buildInClause(string $column, array $values, string $prefix, array &$params): string
{
    $placeholders = [];
    foreach ($values as $i => $v) {
        $key            = ':' . $prefix . '_' . $i;
        $placeholders[] = $key;
        $params[$key]   = $v;
    }
    return $column . ' IN (' . implode(',', $placeholders) . ')';
}

/*
|--------------------------------------------------------------------------
| Base WHERE + params
|--------------------------------------------------------------------------
*/

$where  = ["e.railroad_id = :railroad_id"];
$params = [':railroad_id' => $railroad['id']];

if ($search !== '') {
    $where[] = "(
        CONCAT(e.reporting_marks,' ',e.road_number) LIKE :search
        OR i.industry_name  LIKE :search
        OR e.current_track  LIKE :search
        OR e.load_status    LIKE :search
        OR w.commodity      LIKE :search
        OR d.industry_name  LIKE :search
    )";
    $params[':search'] = '%' . $search . '%';
}

if (!empty($locationFilters)) {
    $where[] = buildInClause('i.industry_name', $locationFilters, 'loc', $params);
}

if (!empty($loadFilters)) {
    $where[] = buildInClause('e.load_status', $loadFilters, 'load', $params);
}

if (!empty($waybillFilters)) {
    if (in_array('yes', $waybillFilters) && !in_array('no', $waybillFilters)) {
        $where[] = "w.commodity IS NOT NULL AND w.commodity <> ''";
    } elseif (in_array('no', $waybillFilters) && !in_array('yes', $waybillFilters)) {
        $where[] = "(w.commodity IS NULL OR w.commodity = '')";
    }
}

if (!empty($moveFilters)) {
    if (in_array('yes', $moveFilters) && !in_array('no', $moveFilters)) {
        $where[] = "(w.destination_industry_id IS NOT NULL AND w.destination_industry_id != e.current_industry_id)";
    } elseif (in_array('no', $moveFilters) && !in_array('yes', $moveFilters)) {
        $where[] = "(w.destination_industry_id IS NULL OR w.destination_industry_id = e.current_industry_id)";
    }
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
    LEFT JOIN waybills w   ON e.id = w.equipment_id AND w.active = 1
    LEFT JOIN industries d ON w.destination_industry_id = d.id
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
    $totalPages = max(1, ceil($totalRecords / $perPage));
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
        e.current_industry_id,
        e.current_track,
        e.load_status,
        i.industry_name,
        w.commodity,
        w.current_cycle,
        w.cycle_count,
        w.destination_industry_id,
        d.industry_name AS destination
    FROM equipment e
    LEFT JOIN industries i ON e.current_industry_id = i.id
    LEFT JOIN waybills w   ON e.id = w.equipment_id AND w.active = 1
    LEFT JOIN industries d ON w.destination_industry_id = d.id
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
| Summary Counts (always across all cars, ignoring pagination)
|--------------------------------------------------------------------------
*/

$summaryStmt = $pdo->prepare("
    SELECT
        COUNT(*)                                                         AS total_cars,
        SUM(w.commodity IS NOT NULL AND w.commodity <> '')               AS active_waybills,
        SUM(LOWER(e.load_status) = 'loaded')                             AS loaded_cars,
        SUM(e.current_industry_id IS NOT NULL)                           AS located_cars,
        SUM(w.destination_industry_id IS NOT NULL
            AND w.destination_industry_id != e.current_industry_id)      AS moves_needed
    FROM equipment e
    LEFT JOIN industries i ON e.current_industry_id = i.id
    LEFT JOIN waybills w   ON e.id = w.equipment_id AND w.active = 1
    WHERE e.railroad_id = :railroad_id
");

$summaryStmt->execute([':railroad_id' => $railroad['id']]);
$summary = $summaryStmt->fetch(PDO::FETCH_ASSOC);

?>

<?php include '../includes/header.php'; ?>

<title>Car Status</title>

<link rel="stylesheet" href="../assets/css/list_v2.css">

</head>

<body>

<?php include '../includes/navbar.php'; ?>

<div class="container-fluid mt-4">

<!-- SUMMARY CARDS -->

<div class="row mb-4 g-3">

<div class="col-6 col-md-3">
<div class="card text-center h-100">
<div class="card-body">
<h2 class="mb-0"><?= $summary['total_cars'] ?></h2>
<div class="text-muted small">Total Cars</div>
</div>
</div>
</div>

<div class="col-6 col-md-3">
<div class="card text-center h-100">
<div class="card-body">
<h2 class="mb-0"><?= $summary['active_waybills'] ?></h2>
<div class="text-muted small">Active Waybills</div>
</div>
</div>
</div>

<div class="col-6 col-md-3">
<div class="card text-center h-100">
<div class="card-body">
<h2 class="mb-0"><?= $summary['loaded_cars'] ?></h2>
<div class="text-muted small">Loaded Cars</div>
</div>
</div>
</div>

<div class="col-6 col-md-3">
<div class="card text-center h-100">
<div class="card-body">
<h2 class="mb-0"><?= $summary['moves_needed'] ?></h2>
<div class="text-muted small">Moves Needed</div>
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

<?php foreach ($locationFilters as $filter): ?>
<a class="filter-chip" href="?<?= http_build_query(array_merge($_GET, ['location' => array_values(array_diff($locationFilters, [$filter]))])) ?>">
    × <?= htmlspecialchars($filter) ?>
</a>
<?php endforeach; ?>

<?php foreach ($loadFilters as $filter): ?>
<a class="filter-chip" href="?<?= http_build_query(array_merge($_GET, ['load_status' => array_values(array_diff($loadFilters, [$filter]))])) ?>">
    × <?= htmlspecialchars($filter) ?>
</a>
<?php endforeach; ?>

<?php foreach ($waybillFilters as $filter): ?>
<a class="filter-chip" href="?<?= http_build_query(array_merge($_GET, ['waybill' => array_values(array_diff($waybillFilters, [$filter]))])) ?>">
    × Waybill: <?= $filter === 'yes' ? 'Has Waybill' : 'No Waybill' ?>
</a>
<?php endforeach; ?>

<?php foreach ($moveFilters as $filter): ?>
<a class="filter-chip" href="?<?= http_build_query(array_merge($_GET, ['move' => array_values(array_diff($moveFilters, [$filter]))])) ?>">
    × Move: <?= $filter === 'yes' ? 'Move Needed' : 'No Move' ?>
</a>
<?php endforeach; ?>

</div>

<!-- SEARCH -->

<div class="filter-section">
<div class="section-header"><span class="arrow">►</span> Search</div>
<div class="section-content collapsed">
    <input type="text" name="search" class="form-control form-control-sm"
        value="<?= htmlspecialchars($search) ?>"
        placeholder="Car, location, commodity…">
    <button type="submit" class="btn btn-sm btn-primary mt-2 w-100">Go</button>
</div>
</div>

<!-- LOCATION -->

<div class="filter-section">
<div class="section-header"><span class="arrow">▼</span> Location</div>
<div class="section-content filter-scroll">
<?php foreach ($locationOptions as $option): ?>
<label class="form-check">
    <input class="form-check-input auto-filter" type="checkbox" name="location[]"
        value="<?= htmlspecialchars($option) ?>"
        <?= in_array($option, $locationFilters) ? 'checked' : '' ?>>
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
        <?= in_array($option, $loadFilters) ? 'checked' : '' ?>>
    <span class="form-check-label"><?= htmlspecialchars($option) ?></span>
</label>
<?php endforeach; ?>
</div>
</div>

<!-- WAYBILL -->

<div class="filter-section">
<div class="section-header"><span class="arrow">►</span> Waybill</div>
<div class="section-content collapsed">
<label class="form-check">
    <input class="form-check-input auto-filter" type="checkbox" name="waybill[]" value="yes"
        <?= in_array('yes', $waybillFilters) ? 'checked' : '' ?>>
    <span class="form-check-label">Has Waybill</span>
</label>
<label class="form-check">
    <input class="form-check-input auto-filter" type="checkbox" name="waybill[]" value="no"
        <?= in_array('no', $waybillFilters) ? 'checked' : '' ?>>
    <span class="form-check-label">No Waybill</span>
</label>
</div>
</div>

<!-- MOVE NEEDED -->

<div class="filter-section">
<div class="section-header"><span class="arrow">►</span> Move Needed</div>
<div class="section-content collapsed">
<label class="form-check">
    <input class="form-check-input auto-filter" type="checkbox" name="move[]" value="yes"
        <?= in_array('yes', $moveFilters) ? 'checked' : '' ?>>
    <span class="form-check-label">Move Needed</span>
</label>
<label class="form-check">
    <input class="form-check-input auto-filter" type="checkbox" name="move[]" value="no"
        <?= in_array('no', $moveFilters) ? 'checked' : '' ?>>
    <span class="form-check-label">No Move</span>
</label>
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
    <div class="text-muted mb-3">
        Showing <strong><?= count($cars) ?></strong> of <strong><?= $totalRecords ?></strong> cars
    </div>
</div>

<!-- TOP TOOLBAR -->

<div class="top-toolbar">
<div class="toolbar-right ms-auto"></div>
<div class="toolbar-right">
    <label class="small me-2">Show</label>
    <select id="perPage" class="form-select form-select-sm">
        <option value="10"  <?= $perPage == 10    ? 'selected' : '' ?>>10</option>
        <option value="20"  <?= $perPage == 20    ? 'selected' : '' ?>>20</option>
        <option value="50"  <?= $perPage == 50    ? 'selected' : '' ?>>50</option>
        <option value="100" <?= $perPage == 100   ? 'selected' : '' ?>>100</option>
        <option value="all" <?= $perPage === 'all'? 'selected' : '' ?>>All</option>
    </select>
</div>
</div>

<div class="table-responsive">

<table class="table table-hover align-middle equipment-table">

<thead>
<tr>

<th>
<a class="sort-link" href="?<?= http_build_query(array_merge($_GET, ['sort' => 'car', 'dir' => ($sort === 'car' && $dir === 'asc') ? 'desc' : 'asc'])) ?>">
    Car ▲▼
</a>
</th>

<th>
<a class="sort-link" href="?<?= http_build_query(array_merge($_GET, ['sort' => 'location', 'dir' => ($sort === 'location' && $dir === 'asc') ? 'desc' : 'asc'])) ?>">
    Location ▲▼
</a>
</th>

<th>
<a class="sort-link" href="?<?= http_build_query(array_merge($_GET, ['sort' => 'current_track', 'dir' => ($sort === 'current_track' && $dir === 'asc') ? 'desc' : 'asc'])) ?>">
    Track ▲▼
</a>
</th>

<th>
<a class="sort-link" href="?<?= http_build_query(array_merge($_GET, ['sort' => 'load_status', 'dir' => ($sort === 'load_status' && $dir === 'asc') ? 'desc' : 'asc'])) ?>">
    Status ▲▼
</a>
</th>

<th>
<a class="sort-link" href="?<?= http_build_query(array_merge($_GET, ['sort' => 'commodity', 'dir' => ($sort === 'commodity' && $dir === 'asc') ? 'desc' : 'asc'])) ?>">
    Commodity ▲▼
</a>
</th>

<th>
<a class="sort-link" href="?<?= http_build_query(array_merge($_GET, ['sort' => 'destination', 'dir' => ($sort === 'destination' && $dir === 'asc') ? 'desc' : 'asc'])) ?>">
    Destination ▲▼
</a>
</th>

<th>
<a class="sort-link" href="?<?= http_build_query(array_merge($_GET, ['sort' => 'cycle', 'dir' => ($sort === 'cycle' && $dir === 'asc') ? 'desc' : 'asc'])) ?>">
    Cycle ▲▼
</a>
</th>

<th>
<a class="sort-link" href="?<?= http_build_query(array_merge($_GET, ['sort' => 'move', 'dir' => ($sort === 'move' && $dir === 'asc') ? 'desc' : 'asc'])) ?>">
    Move? ▲▼
</a>
</th>

</tr>
</thead>

<tbody>

<?php foreach ($cars as $car): ?>

<tr class="clickable-row" data-href="view.php?id=<?= $car['id'] ?>">

<td><strong><?= htmlspecialchars($car['car']) ?></strong></td>

<td><?= htmlspecialchars($car['industry_name'] ?: '—') ?></td>

<td><?= htmlspecialchars($car['current_track'] ?: '—') ?></td>

<td>
<?php if (strtolower($car['load_status']) === 'loaded'): ?>
    <span class="badge bg-success">Loaded</span>
<?php else: ?>
    <span class="badge bg-secondary">Empty</span>
<?php endif; ?>
</td>

<td>
<?php if (!empty($car['commodity'])): ?>
    <?= htmlspecialchars($car['commodity']) ?>
<?php else: ?>
    <span class="badge bg-warning text-dark">No Waybill</span>
<?php endif; ?>
</td>

<td><?= htmlspecialchars($car['destination'] ?: '—') ?></td>

<td>
<?php if (!empty($car['current_cycle'])): ?>
    <?= $car['current_cycle'] ?> / <?= $car['cycle_count'] ?>
<?php else: ?>
    —
<?php endif; ?>
</td>

<td>
<?php if (!empty($car['destination_industry_id']) && $car['destination_industry_id'] != $car['current_industry_id']): ?>
    <span class="badge bg-primary">Move Needed</span>
<?php else: ?>
    —
<?php endif; ?>
</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

</div>

<hr>

<div class="pagination-area text-center">

<?php if ($perPage !== 'all' && $totalPages > 1): ?>

    <?php if ($page > 1): ?>
        <a class="btn btn-outline-secondary btn-sm"
           href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">
            &lt;
        </a>
    <?php endif; ?>

    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <a class="btn btn-sm <?= $i === $page ? 'btn-primary' : 'btn-outline-secondary' ?>"
           href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>">
            <?= $i ?>
        </a>
    <?php endfor; ?>

    <?php if ($page < $totalPages): ?>
        <a class="btn btn-outline-secondary btn-sm"
           href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">
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
