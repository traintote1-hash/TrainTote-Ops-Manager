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

$typeFilters   = array_values(array_filter(array_map('strval', (array)($_GET['job_type'] ?? []))));
$activeFilters = array_values(array_filter(array_map('strval', (array)($_GET['active']   ?? [])), fn($v) => $v === '0' || $v === '1'));

/*
|--------------------------------------------------------------------------
| Sorting
|--------------------------------------------------------------------------
*/

$allowedSorts = [
    'job_name',
    'job_type',
    'home_location',
    'active'
];

$sort = $_GET['sort'] ?? 'job_name';

if (!in_array($sort, $allowedSorts)) {
    $sort = 'job_name';
}

$dir = strtolower($_GET['dir'] ?? 'asc');

if (!in_array($dir, ['asc', 'desc'])) {
    $dir = 'asc';
}

$orderBy = match ($sort) {
    'job_type'      => 'j.job_type',
    'home_location' => 'i.industry_name',
    'active'        => 'j.active',
    default         => 'j.job_name',
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

$typeOptions = $pdo->query("
    SELECT DISTINCT job_type
    FROM jobs
    WHERE job_type <> ''
    ORDER BY job_type
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

$where  = ["j.railroad_id = :railroad_id"];
$params = [':railroad_id' => $railroad['id']];

if ($search !== '') {
    $where[] = "(
        j.job_name LIKE :search
        OR j.job_type LIKE :search
        OR i.industry_name LIKE :search
    )";
    $params[':search'] = '%' . $search . '%';
}

if (!empty($typeFilters)) {
    $where[] = buildInClause('j.job_type', $typeFilters, 'type', $params);
}

if (!empty($activeFilters)) {
    $where[] = buildInClause('j.active', $activeFilters, 'active', $params);
}

$whereSQL = implode(' AND ', $where);

$fromJoin = "
    FROM jobs j
    LEFT JOIN industries i ON j.home_industry_id = i.id
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
        j.*,
        i.industry_name AS home_location
    $fromJoin
    WHERE $whereSQL
    ORDER BY $orderBy
";

if ($perPage !== 'all') {
    $sql .= " LIMIT $offset, $perPage";
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<?php include '../includes/header.php'; ?>

<title>Jobs</title>

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

<?php foreach ($typeFilters as $filter): ?>
<a class="filter-chip" href="?<?= http_build_query(array_merge($_GET, ['job_type' => array_values(array_diff($typeFilters, [$filter]))])) ?>">
    × <?= htmlspecialchars($filter) ?>
</a>
<?php endforeach; ?>

<?php foreach ($activeFilters as $filter): ?>
<a class="filter-chip" href="?<?= http_build_query(array_merge($_GET, ['active' => array_values(array_diff($activeFilters, [$filter]))])) ?>">
    × <?= $filter === '1' ? 'Active' : 'Inactive' ?>
</a>
<?php endforeach; ?>

</div>

<!-- SEARCH -->

<div class="filter-section">
<div class="section-header"><span class="arrow">►</span> Search</div>
<div class="section-content collapsed">
    <input type="text" name="search" class="form-control form-control-sm"
        value="<?= htmlspecialchars($search) ?>"
        placeholder="Job name, type, location…">
    <button type="submit" class="btn btn-sm btn-primary mt-2 w-100">Go</button>
</div>
</div>

<!-- JOB TYPE -->

<div class="filter-section">
<div class="section-header"><span class="arrow">▼</span> Job Type</div>
<div class="section-content filter-scroll">
<?php foreach ($typeOptions as $option): ?>
<label class="form-check">
    <input class="form-check-input auto-filter" type="checkbox" name="job_type[]"
        value="<?= htmlspecialchars($option) ?>"
        <?= in_array($option, $typeFilters) ? 'checked' : '' ?>>
    <span class="form-check-label"><?= htmlspecialchars(ucfirst($option)) ?></span>
</label>
<?php endforeach; ?>
</div>
</div>

<!-- ACTIVE -->

<div class="filter-section">
<div class="section-header"><span class="arrow">►</span> Status</div>
<div class="section-content collapsed">
<label class="form-check">
    <input class="form-check-input auto-filter" type="checkbox" name="active[]" value="1"
        <?= in_array('1', $activeFilters) ? 'checked' : '' ?>>
    <span class="form-check-label">Active</span>
</label>
<label class="form-check">
    <input class="form-check-input auto-filter" type="checkbox" name="active[]" value="0"
        <?= in_array('0', $activeFilters) ? 'checked' : '' ?>>
    <span class="form-check-label">Inactive</span>
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
    <h1 class="mb-1">Jobs</h1>
    <div class="text-muted mb-3">
        Showing <strong><?= count($jobs) ?></strong> of <strong><?= $totalRecords ?></strong> jobs
    </div>
    <a href="add.php" class="btn btn-primary mb-4">Add Job</a>
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
<a class="sort-link" href="?<?= http_build_query(array_merge($_GET, ['sort' => 'job_name', 'dir' => ($sort === 'job_name' && $dir === 'asc') ? 'desc' : 'asc'])) ?>">
    Job Name ▲▼
</a>
</th>

<th>
<a class="sort-link" href="?<?= http_build_query(array_merge($_GET, ['sort' => 'job_type', 'dir' => ($sort === 'job_type' && $dir === 'asc') ? 'desc' : 'asc'])) ?>">
    Type ▲▼
</a>
</th>

<th>
<a class="sort-link" href="?<?= http_build_query(array_merge($_GET, ['sort' => 'home_location', 'dir' => ($sort === 'home_location' && $dir === 'asc') ? 'desc' : 'asc'])) ?>">
    Home Location ▲▼
</a>
</th>

<th>
<a class="sort-link" href="?<?= http_build_query(array_merge($_GET, ['sort' => 'active', 'dir' => ($sort === 'active' && $dir === 'asc') ? 'desc' : 'asc'])) ?>">
    Status ▲▼
</a>
</th>

<th></th>

</tr>
</thead>

<tbody>

<?php foreach ($jobs as $job): ?>

<tr class="clickable-row" data-href="view.php?id=<?= $job['id'] ?>">

<td>
    <strong><?= htmlspecialchars($job['job_name']) ?></strong>
</td>

<td>
<?php
    echo htmlspecialchars(
        $job['job_type'] === 'custom'
            ? ($job['custom_job_type'] ?: 'Custom')
            : ucfirst($job['job_type'])
    );
?>
</td>

<td><?= htmlspecialchars($job['home_location'] ?? '—') ?></td>

<td>
<?php if ($job['active']): ?>
    <span class="badge bg-success">Active</span>
<?php else: ?>
    <span class="badge bg-secondary">Inactive</span>
<?php endif; ?>
</td>

<td onclick="event.stopPropagation();">
    <a href="edit.php?id=<?= $job['id'] ?>" class="btn btn-sm btn-outline-primary me-1">Edit</a>
    <a href="delete.php?id=<?= $job['id'] ?>" class="btn btn-sm btn-outline-danger"
       onclick="return confirm('Delete this job?')">Delete</a>
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
