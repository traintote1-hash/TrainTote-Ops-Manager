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

$typeFilters     = array_values(array_filter(array_map('strval', (array)($_GET['industry_type'] ?? []))));
$locationFilters = array_values(array_filter(array_map('strval', (array)($_GET['location']      ?? []))));
$activeFilters   = array_values(array_filter(
    array_map('strval', (array)($_GET['active'] ?? [])),
    static fn(string $value): bool => $value === '0' || $value === '1'
));

/*
|--------------------------------------------------------------------------
| Sorting
|--------------------------------------------------------------------------
*/

$allowedSorts = [
    'industry_name',
    'industry_type',
    'location',
    'track_capacity',
    'active'
];

$sort = $_GET['sort'] ?? 'industry_name';

if (!in_array($sort, $allowedSorts)) {
    $sort = 'industry_name';
}

$dir = strtolower($_GET['dir'] ?? 'asc');

if (!in_array($dir, ['asc', 'desc'])) {
    $dir = 'asc';
}

$orderBy = match ($sort) {
    'industry_type'  => 'industry_type',
    'location'       => 'location',
    'track_capacity' => 'track_capacity',
    'active'         => 'active',
    default          => 'industry_name',
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
    SELECT DISTINCT industry_type
    FROM industries
    WHERE industry_type <> ''
    ORDER BY industry_type
")->fetchAll(PDO::FETCH_COLUMN);

$locationOptions = $pdo->query("
    SELECT DISTINCT location
    FROM industries
    WHERE location <> ''
    ORDER BY location
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

$where  = ["railroad_id = :railroad_id"];
$params = [':railroad_id' => $railroad['id']];

if ($search !== '') {
    $where[] = "(
        industry_name LIKE :search
        OR industry_type LIKE :search
        OR location LIKE :search
    )";
    $params[':search'] = '%' . $search . '%';
}

if (!empty($typeFilters)) {
    $where[] = buildInClause('industry_type', $typeFilters, 'type', $params);
}

if (!empty($locationFilters)) {
    $where[] = buildInClause('location', $locationFilters, 'loc', $params);
}

if (!empty($activeFilters)) {
    $where[] = buildInClause('active', $activeFilters, 'active', $params);
}

$whereSQL = implode(' AND ', $where);

/*
|--------------------------------------------------------------------------
| Count
|--------------------------------------------------------------------------
*/

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM industries WHERE $whereSQL");
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

$sql = "SELECT * FROM industries WHERE $whereSQL ORDER BY $orderBy";

if ($perPage !== 'all') {
    $sql .= " LIMIT $offset, $perPage";
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$industries = $stmt->fetchAll(PDO::FETCH_ASSOC);

$bulkMessageCode = $_GET['bulk_message'] ?? '';
$bulkCount = max(0, (int)($_GET['bulk_count'] ?? 0));
$bulkMessage = '';
$bulkMessageType = 'success';

switch ($bulkMessageCode) {
    case 'none_selected':
        $bulkMessage = 'Select at least one industry before applying a bulk action.';
        $bulkMessageType = 'warning';
        break;
    case 'invalid_action':
        $bulkMessage = 'Choose a valid bulk action.';
        $bulkMessageType = 'warning';
        break;
    case 'none_authorized':
        $bulkMessage = 'None of the selected industries could be updated.';
        $bulkMessageType = 'warning';
        break;
    case 'active_set':
        $bulkMessage = $bulkCount . ($bulkCount === 1 ? ' industry was' : ' industries were') . ' set to Active.';
        break;
    case 'inactive_set':
        $bulkMessage = $bulkCount . ($bulkCount === 1 ? ' industry was' : ' industries were') . ' set to Inactive.';
        break;
    case 'industries_deleted':
        $bulkMessage = $bulkCount . ($bulkCount === 1 ? ' industry was' : ' industries were') . ' deleted.';
        break;
    case 'bulk_edit_complete':
        $bulkMessage = 'Bulk Edit completed.';
        break;
    case 'delete_failed':
        $bulkMessage = 'The selected industries could not be deleted. No industries were removed.';
        $bulkMessageType = 'danger';
        break;
}

$returnUrl = 'list.php' . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '');

?>

<?php include '../includes/header.php'; ?>

<title>Industries</title>

<link rel="stylesheet" href="../assets/css/list_v2.css">

</head>

<body>

<?php include '../includes/navbar.php'; ?>

<div class="container-fluid tt-list-page">

<?php if ($bulkMessage !== ''): ?>
<div class="alert alert-<?= htmlspecialchars($bulkMessageType) ?>">
    <?= htmlspecialchars($bulkMessage) ?>
</div>
<?php endif; ?>

<div class="list-layout">

<!-- =====================================================
SIDEBAR
===================================================== -->

<aside class="sidebar">

<div class="sidebar-card">

<form method="get" id="filterForm">

<h5 class="sidebar-title">Filters</h5>

<!-- ACTIVE FILTER CHIPS -->

<div class="active-filters">

<div class="d-flex justify-content-between align-items-center mb-2">
    <strong>Now Filtering</strong>
    <a href="list.php" class="btn btn-sm btn-outline-secondary">Clear All</a>
</div>

<?php foreach ($typeFilters as $filter): ?>
<a class="filter-chip" href="?<?= http_build_query(array_merge($_GET, ['industry_type' => array_values(array_diff($typeFilters, [$filter]))])) ?>">
    × <?= htmlspecialchars($filter) ?>
</a>
<?php endforeach; ?>

<?php foreach ($locationFilters as $filter): ?>
<a class="filter-chip" href="?<?= http_build_query(array_merge($_GET, ['location' => array_values(array_diff($locationFilters, [$filter]))])) ?>">
    × <?= htmlspecialchars($filter) ?>
</a>
<?php endforeach; ?>

<?php foreach ($activeFilters as $filter): ?>
<a class="filter-chip" href="?<?= http_build_query(array_merge($_GET, ['active' => array_values(array_diff($activeFilters, [$filter])), 'page' => 1])) ?>">
    &times; <?= $filter === '1' ? 'Active' : 'Inactive' ?>
</a>
<?php endforeach; ?>

</div>

<!-- SEARCH -->

<div class="filter-section">
<div class="section-header"><span class="arrow">►</span> Search</div>
<div class="section-content collapsed">
    <input type="text" name="search" class="form-control form-control-sm"
        value="<?= htmlspecialchars($search) ?>"
        placeholder="Name, type, location…">
    <button type="submit" class="btn btn-sm btn-primary mt-2 w-100">Go</button>
</div>
</div>

<!-- INDUSTRY TYPE -->

<div class="filter-section">
<div class="section-header"><span class="arrow">▼</span> Industry Type</div>
<div class="section-content filter-scroll">
<?php foreach ($typeOptions as $option): ?>
<label class="form-check">
    <input class="form-check-input auto-filter" type="checkbox" name="industry_type[]"
        value="<?= htmlspecialchars($option) ?>"
        <?= in_array($option, $typeFilters) ? 'checked' : '' ?>>
    <span class="form-check-label"><?= htmlspecialchars($option) ?></span>
</label>
<?php endforeach; ?>
</div>
</div>

<!-- LOCATION -->

<div class="filter-section">
<div class="section-header"><span class="arrow">►</span> Location</div>
<div class="section-content filter-scroll collapsed">
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

<!-- ACTIVE STATUS -->

<div class="filter-section">
<div class="section-header"><span class="arrow">&#9658;</span> Status</div>
<div class="section-content collapsed">
<label class="form-check">
    <input class="form-check-input auto-filter" type="checkbox" name="active[]" value="1"
        <?= in_array('1', $activeFilters, true) ? 'checked' : '' ?>>
    <span class="form-check-label">Active</span>
</label>
<label class="form-check">
    <input class="form-check-input auto-filter" type="checkbox" name="active[]" value="0"
        <?= in_array('0', $activeFilters, true) ? 'checked' : '' ?>>
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

<div class="tt-list-header">
    <div class="tt-list-title-block">
        <span class="tt-list-kicker">Railroad Builder</span>
        <h1 class="tt-list-title">Industries</h1>
        <div class="tt-list-count">
        Showing <strong><?= count($industries) ?></strong> of <strong><?= $totalRecords ?></strong> industries
        </div>
    </div>
    <div class="tt-list-actions">
        <a href="add.php" class="btn btn-primary">Add Industry</a>
    </div>
</div>

<!-- TOP TOOLBAR -->

<form method="post" action="bulk_action.php" id="industryBulkForm">
<input type="hidden" name="return_url" value="<?= htmlspecialchars($returnUrl, ENT_QUOTES, 'UTF-8') ?>">

<div class="top-toolbar">
<div class="toolbar-left">
    <div class="tt-list-actions">
        <select name="bulk_action" class="form-select bulk-action-select">
            <option value="">Bulk Action</option>
            <option value="bulk_edit">Bulk Edit</option>
            <option value="set_active">Set Active</option>
            <option value="set_inactive">Set Inactive</option>
            <option value="delete_selected">Delete Selected</option>
        </select>
        <button type="submit" class="btn btn-success">Apply Bulk Action</button>
    </div>
</div>
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

<th width="40">
    <input type="checkbox" id="selectAll">
</th>

<th>Photo</th>

<th>
<a class="sort-link" href="?<?= http_build_query(array_merge($_GET, ['sort' => 'industry_name', 'dir' => ($sort === 'industry_name' && $dir === 'asc') ? 'desc' : 'asc'])) ?>">
    Industry ▲▼
</a>
</th>

<th>
<a class="sort-link" href="?<?= http_build_query(array_merge($_GET, ['sort' => 'industry_type', 'dir' => ($sort === 'industry_type' && $dir === 'asc') ? 'desc' : 'asc'])) ?>">
    Type ▲▼
</a>
</th>

<th>
<a class="sort-link" href="?<?= http_build_query(array_merge($_GET, ['sort' => 'location', 'dir' => ($sort === 'location' && $dir === 'asc') ? 'desc' : 'asc'])) ?>">
    Location ▲▼
</a>
</th>

<th>
<a class="sort-link" href="?<?= http_build_query(array_merge($_GET, ['sort' => 'track_capacity', 'dir' => ($sort === 'track_capacity' && $dir === 'asc') ? 'desc' : 'asc'])) ?>">
    Capacity ▲▼
</a>
</th>

<th>
<a class="sort-link" href="?<?= http_build_query(array_merge($_GET, ['sort' => 'active', 'dir' => ($sort === 'active' && $dir === 'asc') ? 'desc' : 'asc'])) ?>">
    Status &#9650;&#9660;
</a>
</th>

<th></th>

</tr>
</thead>

<tbody>

<?php foreach ($industries as $industry): ?>

<tr class="clickable-row" data-href="view.php?id=<?= $industry['id'] ?>">

<td onclick="event.stopPropagation();">
    <input type="checkbox" name="industry_ids[]" value="<?= (int)$industry['id'] ?>">
</td>

<td>
<?php if (!empty($industry['photo_filename'])): ?>
    <img src="../uploads/<?= htmlspecialchars($industry['photo_filename']) ?>"
         class="img-thumbnail equipment-thumb">
<?php else: ?>
    <div class="border rounded text-center bg-light equipment-thumb d-flex align-items-center justify-content-center">
        📷
    </div>
<?php endif; ?>
</td>

<td>
    <strong><?= htmlspecialchars($industry['industry_name']) ?></strong>
</td>

<td><?= htmlspecialchars($industry['industry_type']) ?></td>

<td><?= htmlspecialchars($industry['location']) ?></td>

<td><?= htmlspecialchars($industry['track_capacity']) ?> cars</td>

<td>
<?php if ((int)($industry['active'] ?? 1) === 1): ?>
    <span class="badge bg-success">Active</span>
<?php else: ?>
    <span class="badge bg-secondary">Inactive</span>
<?php endif; ?>
</td>

<td onclick="event.stopPropagation();">
    <a href="edit.php?id=<?= $industry['id'] ?>" class="btn btn-sm btn-outline-primary">Edit</a>
</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

</div>

</form>

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
<script>
const industrySelectAll = document.getElementById('selectAll');

if (industrySelectAll) {
    industrySelectAll.addEventListener('change', function () {
        document.querySelectorAll('input[name="industry_ids[]"]').forEach(function (checkbox) {
            checkbox.checked = industrySelectAll.checked;
        });
    });
}
</script>

<?php include '../includes/footer.php'; ?>
