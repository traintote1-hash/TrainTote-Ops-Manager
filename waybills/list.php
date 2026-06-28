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

$statusFilters = array_values(array_filter(array_map('strval', (array)($_GET['status']      ?? []))));
$originFilters = array_values(array_filter(array_map('strval', (array)($_GET['origin']      ?? []))));
$destFilters   = array_values(array_filter(array_map('strval', (array)($_GET['destination'] ?? []))));

/*
|--------------------------------------------------------------------------
| Sorting
|--------------------------------------------------------------------------
*/

$allowedSorts = [
    'car',
    'origin',
    'destination',
    'commodity',
    'status'
];

$sort = $_GET['sort'] ?? 'car';

if (!in_array($sort, $allowedSorts)) {
    $sort = 'car';
}

$dir = strtolower($_GET['dir'] ?? 'asc');

if (!in_array($dir, ['asc', 'desc'])) {
    $dir = 'asc';
}

$orderBy = match ($sort) {
    'origin'      => 'oi.industry_name',
    'destination' => 'di.industry_name',
    'commodity'   => 'wc.commodity',
    'status'      => 'wc.status',
    default       => 'e.reporting_marks',
};

$orderBy .= ' ' . strtoupper($dir);

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

$statusOptions = $pdo->query("
    SELECT DISTINCT status
    FROM waybill_cycles
    WHERE status <> ''
    ORDER BY status
")->fetchAll(PDO::FETCH_COLUMN);

$originOptions = $pdo->query("
    SELECT DISTINCT i.industry_name
    FROM waybill_cycles wc
    JOIN industries i ON wc.origin_industry_id = i.id
    ORDER BY i.industry_name
")->fetchAll(PDO::FETCH_COLUMN);

$destOptions = $pdo->query("
    SELECT DISTINCT i.industry_name
    FROM waybill_cycles wc
    JOIN industries i ON wc.destination_industry_id = i.id
    ORDER BY i.industry_name
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
| WHERE + params
|--------------------------------------------------------------------------
*/

$where  = ["w.railroad_id = :railroad_id"];
$params = [':railroad_id' => $railroad['id']];

if ($search !== '') {
    $where[] = "(
        e.reporting_marks   LIKE :search
        OR e.road_number    LIKE :search
        OR oi.industry_name LIKE :search
        OR di.industry_name LIKE :search
        OR wc.commodity     LIKE :search
        OR wc.status        LIKE :search
    )";
    $params[':search'] = '%' . $search . '%';
}

if (!empty($statusFilters)) {
    $where[] = buildInClause('wc.status', $statusFilters, 'status', $params);
}

if (!empty($originFilters)) {
    $where[] = buildInClause('oi.industry_name', $originFilters, 'orig', $params);
}

if (!empty($destFilters)) {
    $where[] = buildInClause('di.industry_name', $destFilters, 'dest', $params);
}

$whereSQL = implode(' AND ', $where);

/*
|--------------------------------------------------------------------------
| Base FROM/JOIN (reused in count + main query)
|--------------------------------------------------------------------------
*/

$fromJoin = "
    FROM waybills w
    JOIN equipment e
        ON w.equipment_id = e.id
    JOIN waybill_cycles wc
        ON wc.waybill_id = w.id
        AND wc.cycle_number = w.current_cycle
    LEFT JOIN industries oi
        ON wc.origin_industry_id = oi.id
    LEFT JOIN industries di
        ON wc.destination_industry_id = di.id
";

/*
|--------------------------------------------------------------------------
| Count
|--------------------------------------------------------------------------
*/

$countStmt = $pdo->prepare("SELECT COUNT(*) $fromJoin WHERE $whereSQL");
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
        w.*,
        wc.commodity,
        wc.status,
        wc.route,
        e.reporting_marks,
        e.road_number,
        oi.industry_name AS origin_name,
        di.industry_name AS destination_name
    $fromJoin
    WHERE $whereSQL
    ORDER BY $orderBy
";

if ($perPage !== 'all') {
    $sql .= " LIMIT $offset, $perPage";
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$waybills = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<?php include '../includes/header.php'; ?>

<title>Waybills</title>

<link rel="stylesheet" href="../assets/css/list_v2.css">

</head>

<body>

<?php include '../includes/navbar.php'; ?>

<div class="container-fluid mt-4">

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
    <a href="list.php" class="btn btn-sm btn-outline-secondary">Clear All</a>
</div>

<?php foreach ($statusFilters as $filter): ?>
<a class="filter-chip" href="?<?= http_build_query(array_merge($_GET, ['status' => array_values(array_diff($statusFilters, [$filter]))])) ?>">
    × <?= htmlspecialchars($filter) ?>
</a>
<?php endforeach; ?>

<?php foreach ($originFilters as $filter): ?>
<a class="filter-chip" href="?<?= http_build_query(array_merge($_GET, ['origin' => array_values(array_diff($originFilters, [$filter]))])) ?>">
    × Origin: <?= htmlspecialchars($filter) ?>
</a>
<?php endforeach; ?>

<?php foreach ($destFilters as $filter): ?>
<a class="filter-chip" href="?<?= http_build_query(array_merge($_GET, ['destination' => array_values(array_diff($destFilters, [$filter]))])) ?>">
    × Dest: <?= htmlspecialchars($filter) ?>
</a>
<?php endforeach; ?>

</div>

<!-- SEARCH -->

<div class="filter-section">
<div class="section-header"><span class="arrow">►</span> Search</div>
<div class="section-content collapsed">
    <input type="text" name="search" class="form-control form-control-sm"
        value="<?= htmlspecialchars($search) ?>"
        placeholder="Car, industry, commodity…">
    <button type="submit" class="btn btn-sm btn-primary mt-2 w-100">Go</button>
</div>
</div>

<!-- STATUS -->

<div class="filter-section">
<div class="section-header"><span class="arrow">▼</span> Status</div>
<div class="section-content filter-scroll">
<?php foreach ($statusOptions as $option): ?>
<label class="form-check">
    <input class="form-check-input auto-filter" type="checkbox" name="status[]"
        value="<?= htmlspecialchars($option) ?>"
        <?= in_array($option, $statusFilters) ? 'checked' : '' ?>>
    <span class="form-check-label"><?= htmlspecialchars($option) ?></span>
</label>
<?php endforeach; ?>
</div>
</div>

<!-- ORIGIN -->

<div class="filter-section">
<div class="section-header"><span class="arrow">►</span> Origin</div>
<div class="section-content filter-scroll collapsed">
<?php foreach ($originOptions as $option): ?>
<label class="form-check">
    <input class="form-check-input auto-filter" type="checkbox" name="origin[]"
        value="<?= htmlspecialchars($option) ?>"
        <?= in_array($option, $originFilters) ? 'checked' : '' ?>>
    <span class="form-check-label"><?= htmlspecialchars($option) ?></span>
</label>
<?php endforeach; ?>
</div>
</div>

<!-- DESTINATION -->

<div class="filter-section">
<div class="section-header"><span class="arrow">►</span> Destination</div>
<div class="section-content filter-scroll collapsed">
<?php foreach ($destOptions as $option): ?>
<label class="form-check">
    <input class="form-check-input auto-filter" type="checkbox" name="destination[]"
        value="<?= htmlspecialchars($option) ?>"
        <?= in_array($option, $destFilters) ? 'checked' : '' ?>>
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
    <h1 class="mb-1">Waybills</h1>
    <div class="text-muted mb-3">
        Showing <strong><?= count($waybills) ?></strong> of <strong><?= $totalRecords ?></strong> waybills
    </div>
    <a href="add_v2.php" class="btn btn-primary mb-4">Add Waybill</a>
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
<a class="sort-link" href="?<?= http_build_query(array_merge($_GET, ['sort' => 'origin', 'dir' => ($sort === 'origin' && $dir === 'asc') ? 'desc' : 'asc'])) ?>">
    Origin ▲▼
</a>
</th>

<th>
<a class="sort-link" href="?<?= http_build_query(array_merge($_GET, ['sort' => 'destination', 'dir' => ($sort === 'destination' && $dir === 'asc') ? 'desc' : 'asc'])) ?>">
    Destination ▲▼
</a>
</th>

<th>
<a class="sort-link" href="?<?= http_build_query(array_merge($_GET, ['sort' => 'commodity', 'dir' => ($sort === 'commodity' && $dir === 'asc') ? 'desc' : 'asc'])) ?>">
    Commodity ▲▼
</a>
</th>

<th>
<a class="sort-link" href="?<?= http_build_query(array_merge($_GET, ['sort' => 'status', 'dir' => ($sort === 'status' && $dir === 'asc') ? 'desc' : 'asc'])) ?>">
    Status ▲▼
</a>
</th>

<th></th>

</tr>
</thead>

<tbody>

<?php foreach ($waybills as $waybill): ?>

<tr class="clickable-row" data-href="view.php?id=<?= $waybill['id'] ?>">

<td>
    <strong><?= htmlspecialchars($waybill['reporting_marks'] . ' ' . $waybill['road_number']) ?></strong>
</td>

<td><?= htmlspecialchars($waybill['origin_name']) ?></td>

<td><?= htmlspecialchars($waybill['destination_name']) ?></td>

<td><?= htmlspecialchars($waybill['commodity']) ?></td>

<td>
<?php
    $badgeClass = match (strtolower($waybill['status'])) {
        'loaded' => 'bg-success',
        'empty'  => 'bg-secondary',
        default  => 'bg-warning text-dark',
    };
?>
    <span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($waybill['status']) ?></span>
</td>

<td onclick="event.stopPropagation();">
    <a href="edit.php?id=<?= $waybill['id'] ?>" class="btn btn-sm btn-outline-primary">Edit</a>
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
