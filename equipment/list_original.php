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

$typeFilters         = $_GET['type'] ?? [];
$scaleFilters        = $_GET['scale'] ?? [];
$manufacturerFilters = $_GET['manufacturer'] ?? [];
$roadFilters         = $_GET['road_name'] ?? [];
$locationFilters     = $_GET['location'] ?? [];
$loadFilters         = $_GET['load_status'] ?? [];
$activeFilters       = $_GET['active'] ?? [];

/*
|--------------------------------------------------------------------------
| Sorting
|--------------------------------------------------------------------------
*/

$allowedSorts = [
    'reporting_marks',
    'road_number',
    'road_name',
    'created_at',
    'equipment_class',
    'equipment_type',
    'manufacturer',
    'load_status',
    'current_location',
    'current_track',
    'active'
];

$sort = $_GET['sort'] ?? 'reporting_marks';

if (!in_array($sort, $allowedSorts)) {
    $sort = 'reporting_marks';
}

$dir = strtolower($_GET['dir'] ?? 'asc');

if (!in_array($dir, ['asc', 'desc'])) {
    $dir = 'asc';
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

    if (!in_array($perPage, [10,20,50,100])) {
        $perPage = 20;
    }

}

/*
|--------------------------------------------------------------------------
| Sort SQL
|--------------------------------------------------------------------------
*/

$orderBy = match ($sort) {

    'road_number'      => 'e.road_number',
    'road_name'        => 'e.road_name',
    'created_at'       => 'e.created_at',
    'equipment_class'  => 'e.equipment_class',
    'equipment_type'   => 'e.equipment_type',
    'manufacturer'     => 'e.manufacturer',
    'load_status'      => 'e.load_status',
    'current_location' => 'i.industry_name',
    'current_track'    => 'e.current_track',
    'active'           => 'e.active',

    default            => 'e.reporting_marks'
};

$orderBy .= ' ' . strtoupper($dir);

/*
|--------------------------------------------------------------------------
| Filter Lists
|--------------------------------------------------------------------------
*/

$typeOptions = $pdo->query("
    SELECT DISTINCT equipment_type
    FROM equipment
    WHERE equipment_type <> ''
    ORDER BY equipment_type
")->fetchAll(PDO::FETCH_COLUMN);

$scaleOptions = $pdo->query("
    SELECT DISTINCT scale
    FROM equipment
    WHERE scale <> ''
    ORDER BY scale
")->fetchAll(PDO::FETCH_COLUMN);

$manufacturerOptions = $pdo->query("
    SELECT DISTINCT manufacturer
    FROM equipment
    WHERE manufacturer <> ''
    ORDER BY manufacturer
")->fetchAll(PDO::FETCH_COLUMN);

$roadOptions = $pdo->query("
    SELECT DISTINCT road_name
    FROM equipment
    WHERE road_name <> ''
    ORDER BY road_name
")->fetchAll(PDO::FETCH_COLUMN);

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
| Base WHERE
|--------------------------------------------------------------------------
*/

$where = [
    "e.railroad_id = :railroad_id"
];

$params = [
    ':railroad_id' => $railroad['id']
];

/*
|--------------------------------------------------------------------------
| Search
|--------------------------------------------------------------------------
*/

if ($search !== '') {

    $where[] = "(
        e.reporting_marks LIKE :search
        OR e.road_number LIKE :search
        OR e.road_name LIKE :search
        OR e.equipment_type LIKE :search
        OR e.prototype LIKE :search
        OR i.industry_name LIKE :search
    )";

    $params[':search'] = '%' . $search . '%';
}

/*
|--------------------------------------------------------------------------
| Type Filter
|--------------------------------------------------------------------------
*/

if (!empty($typeFilters)) {

    $where[] =
        "e.equipment_type IN ('" .
        implode(
            "','",
            array_map('addslashes', $typeFilters)
        ) .
        "')";

}

/*
|--------------------------------------------------------------------------
| Scale Filter
|--------------------------------------------------------------------------
*/

if (!empty($scaleFilters)) {

    $where[] =
        "e.scale IN ('" .
        implode(
            "','",
            array_map('addslashes', $scaleFilters)
        ) .
        "')";

}

/*
|--------------------------------------------------------------------------
| Manufacturer Filter
|--------------------------------------------------------------------------
*/

if (!empty($manufacturerFilters)) {

    $where[] =
        "e.manufacturer IN ('" .
        implode(
            "','",
            array_map('addslashes', $manufacturerFilters)
        ) .
        "')";

}

/*
|--------------------------------------------------------------------------
| Road Name Filter
|--------------------------------------------------------------------------
*/

if (!empty($roadFilters)) {

    $where[] =
        "e.road_name IN ('" .
        implode(
            "','",
            array_map('addslashes', $roadFilters)
        ) .
        "')";

}

/*
|--------------------------------------------------------------------------
| Location Filter
|--------------------------------------------------------------------------
*/

if (!empty($locationFilters)) {

    $where[] =
        "i.industry_name IN ('" .
        implode(
            "','",
            array_map('addslashes', $locationFilters)
        ) .
        "')";

}

/*
|--------------------------------------------------------------------------
| Load Filter
|--------------------------------------------------------------------------
*/

if (!empty($loadFilters)) {

    $where[] =
        "e.load_status IN ('" .
        implode(
            "','",
            array_map('addslashes', $loadFilters)
        ) .
        "')";

}

/*
|--------------------------------------------------------------------------
| Active Filter
|--------------------------------------------------------------------------
*/

if (!empty($activeFilters)) {

    $where[] =
        "e.active IN ('" .
        implode(
            "','",
            array_map('addslashes', $activeFilters)
        ) .
        "')";

}

/*
|--------------------------------------------------------------------------
| Count Query
|--------------------------------------------------------------------------
*/

$countSql = "
SELECT COUNT(*)
FROM equipment e
LEFT JOIN industries i
    ON e.current_industry_id = i.id
WHERE
" . implode(' AND ', $where);

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

    $totalPages = max(
        1,
        ceil($totalRecords / $perPage)
    );

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
    e.*,
    i.industry_name AS current_location
FROM equipment e
LEFT JOIN industries i
    ON e.current_industry_id = i.id
WHERE
" . implode(' AND ', $where) . "
ORDER BY
$orderBy
";

if ($perPage !== 'all') {

    $sql .= "
    LIMIT
        $offset,
        $perPage
    ";

}

$stmt = $pdo->prepare($sql);

$stmt->execute($params);

$equipment = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<?php include '../includes/header.php'; ?>

<title>Equipment</title>

<link
rel="stylesheet"
href="../assets/css/list_v2.css">

</head>

<body>

<?php include '../includes/navbar.php'; ?>

<div class="container-fluid mt-4">

<div class="list-layout">

<aside class="sidebar">

<div class="sidebar-card">

<form
method="get"
id="filterForm">

<h5 class="mb-3">

Filters

</h5>

<!-- ACTIVE FILTERS -->

<div class="active-filters">

<div class="d-flex justify-content-between align-items-center mb-2">

<strong>

Now Filtering

</strong>

<a
href="list.php"
class="btn btn-sm btn-outline-secondary">

Clear All

</a>

</div>

<?php foreach ($typeFilters as $filter): ?>

<a
class="filter-chip"
href="?<?= http_build_query(array_merge($_GET,
[
'type'=>array_values(array_diff($typeFilters,[$filter]))
])) ?>">

× <?php echo htmlspecialchars($filter); ?>

</a>

<?php endforeach; ?>

<?php foreach ($scaleFilters as $filter): ?>

<span class="filter-chip">

× <?php echo htmlspecialchars($filter); ?>

</span>

<?php endforeach; ?>

<?php foreach ($manufacturerFilters as $filter): ?>

<span class="filter-chip">

× <?php echo htmlspecialchars($filter); ?>

</span>

<?php endforeach; ?>

<?php foreach ($roadFilters as $filter): ?>

<span class="filter-chip">

× <?php echo htmlspecialchars($filter); ?>

</span>

<?php endforeach; ?>

<?php foreach ($locationFilters as $filter): ?>

<span class="filter-chip">

× <?php echo htmlspecialchars($filter); ?>

</span>

<?php endforeach; ?>

<?php foreach ($loadFilters as $filter): ?>

<span class="filter-chip">

× <?php echo htmlspecialchars($filter); ?>

</span>

<?php endforeach; ?>

</div>

<!-- SEARCH -->

<div class="filter-section">

<div class="section-header">

<span class="arrow">►</span>

Search

</div>

<div class="section-content collapsed">

<input
type="text"
name="search"
class="form-control form-control-sm"
value="<?php echo htmlspecialchars($search); ?>"
placeholder="Search">

</div>

</div>

<!-- EQUIPMENT TYPE -->

<div class="filter-section">

<div class="section-header">

<span class="arrow">▼</span>

Equipment Type

</div>

<div class="section-content filter-scroll">

<?php foreach ($typeOptions as $option): ?>

<label class="form-check">

<input
class="form-check-input auto-filter"
type="checkbox"
name="type[]"
value="<?php echo htmlspecialchars($option); ?>"
<?php if (in_array($option,$typeFilters)) echo 'checked'; ?>>

<span class="form-check-label">

<?php echo htmlspecialchars($option); ?>

</span>

</label>

<?php endforeach; ?>

</div>

</div>

<!-- SCALE -->

<div class="filter-section">

<div class="section-header">

<span class="arrow">►</span>

Scale

</div>

<div class="section-content filter-scroll collapsed">

<?php foreach ($scaleOptions as $option): ?>

<label class="form-check">

<input
class="form-check-input auto-filter"
type="checkbox"
name="scale[]"
value="<?php echo htmlspecialchars($option); ?>"
<?php if (in_array($option,$scaleFilters)) echo 'checked'; ?>>

<span class="form-check-label">

<?php echo htmlspecialchars($option); ?>

</span>

</label>

<?php endforeach; ?>

</div>

</div>

<!-- MANUFACTURER -->

<div class="filter-section">

<div class="section-header">

<span class="arrow">►</span>

Manufacturer

</div>

<div class="section-content filter-scroll collapsed">

<?php foreach ($manufacturerOptions as $option): ?>

<label class="form-check">

<input
class="form-check-input auto-filter"
type="checkbox"
name="manufacturer[]"
value="<?php echo htmlspecialchars($option); ?>"
<?php if (in_array($option,$manufacturerFilters)) echo 'checked'; ?>>

<span class="form-check-label">

<?php echo htmlspecialchars($option); ?>

</span>

</label>

<?php endforeach; ?>

</div>

</div>

<!-- ROAD NAME -->

<div class="filter-section">

<div class="section-header">

<span class="arrow">►</span>

Road Name

</div>

<div class="section-content filter-scroll collapsed">

<?php foreach ($roadOptions as $option): ?>

<label class="form-check">

<input
class="form-check-input auto-filter"
type="checkbox"
name="road_name[]"
value="<?php echo htmlspecialchars($option); ?>"
<?php if (in_array($option,$roadFilters)) echo 'checked'; ?>>

<span class="form-check-label">

<?php echo htmlspecialchars($option); ?>

</span>

</label>

<?php endforeach; ?>

</div>

</div>

<!-- LOCATION -->

<div class="filter-section">

<div class="section-header">

<span class="arrow">►</span>

Location

</div>

<div class="section-content filter-scroll collapsed">

<?php foreach ($locationOptions as $option): ?>

<label class="form-check">

<input
class="form-check-input auto-filter"
type="checkbox"
name="location[]"
value="<?php echo htmlspecialchars($option); ?>"
<?php if (in_array($option,$locationFilters)) echo 'checked'; ?>>

<span class="form-check-label">

<?php echo htmlspecialchars($option); ?>

</span>

</label>

<?php endforeach; ?>

</div>

</div>

<!-- LOAD STATUS -->

<div class="filter-section">

<div class="section-header">

<span class="arrow">►</span>

Load Status

</div>

<div class="section-content filter-scroll collapsed">

<?php foreach ($loadOptions as $option): ?>

<label class="form-check">

<input
class="form-check-input auto-filter"
type="checkbox"
name="load_status[]"
value="<?php echo htmlspecialchars($option); ?>"
<?php if (in_array($option,$loadFilters)) echo 'checked'; ?>>

<span class="form-check-label">

<?php echo htmlspecialchars($option); ?>

</span>

</label>

<?php endforeach; ?>

</div>

</div>

<!-- ACTIVE -->

<div class="filter-section">

<div class="section-header">

<span class="arrow">►</span>

Active

</div>

<div class="section-content collapsed">

<label class="form-check">

<input
class="form-check-input auto-filter"
type="checkbox"
name="active[]"
value="1"
<?php if (in_array('1',$activeFilters)) echo 'checked'; ?>>

<span class="form-check-label">

Active

</span>

</label>

<label class="form-check">

<input
class="form-check-input auto-filter"
type="checkbox"
name="active[]"
value="0"
<?php if (in_array('0',$activeFilters)) echo 'checked'; ?>>

<span class="form-check-label">

Inactive

</span>

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

    <h1 class="mb-1">

        Equipment

    </h1>

    <div class="text-muted mb-3">

        Showing
        <strong><?= count($equipment) ?></strong>
        of
        <strong><?= $totalRecords ?></strong>
        equipment

    </div>

<div class="d-flex flex-column align-items-start gap-2 mb-4">

    <a
        href="add_select.php"
        class="btn btn-primary">

        Add Equipment

    </a>

    <button
        type="submit"
        form="printForm"
        class="btn btn-success">

        Print Selected Car Cards

    </button>

</div>


</div>



<!-- TOP TOOLBAR -->

<div class="top-toolbar">

<div class="toolbar-right ms-auto">

</div>

<div class="toolbar-right">

<label class="small me-2">

Show

</label>

<select
id="perPage"
class="form-select form-select-sm">

<option value="10" <?php if($perPage==10) echo 'selected'; ?>>10</option>

<option value="20" <?php if($perPage==20) echo 'selected'; ?>>20</option>

<option value="50" <?php if($perPage==50) echo 'selected'; ?>>50</option>

<option value="100" <?php if($perPage==100) echo 'selected'; ?>>100</option>

<option value="all" <?php if($perPage==='all') echo 'selected'; ?>>All</option>

</select>

</div>

</div>

<form
id="printForm"
method="post"
action="print_cards_svg.php">

<div class="table-responsive">

<table class="table table-hover align-middle equipment-table">

<thead>

<tr>

<th width="40">

<input
type="checkbox"
id="selectAll">

</th>

<th>

Photo

</th>

<th>
<a class="sort-link"
href="?<?= http_build_query(array_merge($_GET,
[
'sort'=>'reporting_marks',
'dir'=>($sort=='reporting_marks' && $dir=='asc') ? 'desc':'asc'
])) ?>">
Marks ▲▼
</a>
</th>

<th>
<a class="sort-link"
href="?<?= http_build_query(array_merge($_GET,
[
'sort'=>'road_number',
'dir'=>($sort=='road_number' && $dir=='asc') ? 'desc':'asc'
])) ?>">
Number ▲▼
</a>
</th>

<th>
<a class="sort-link"
href="?<?= http_build_query(array_merge($_GET,
[
'sort'=>'road_name',
'dir'=>($sort=='road_name' && $dir=='asc') ? 'desc':'asc'
])) ?>">
Road Name ▲▼
</a>
</th>

<th>
<a class="sort-link"
href="?<?= http_build_query(array_merge($_GET,
[
'sort'=>'created_at',
'dir'=>($sort=='created_at' && $dir=='asc') ? 'desc':'asc'
])) ?>">
Date Added ▲▼
</a>
</th>

<th>
<a class="sort-link"
href="?<?= http_build_query(array_merge($_GET,
[
'sort'=>'equipment_class',
'dir'=>($sort=='equipment_class' && $dir=='asc') ? 'desc':'asc'
])) ?>">
Class ▲▼
</a>
</th>

<th>
<a class="sort-link"
href="?<?= http_build_query(array_merge($_GET,
[
'sort'=>'equipment_type',
'dir'=>($sort=='equipment_type' && $dir=='asc') ? 'desc':'asc'
])) ?>">
Type ▲▼
</a>
</th>

<th>
<a class="sort-link"
href="?<?= http_build_query(array_merge($_GET,
[
'sort'=>'manufacturer',
'dir'=>($sort=='manufacturer' && $dir=='asc') ? 'desc':'asc'
])) ?>">
Manufacturer ▲▼
</a>
</th>

<th>
<a class="sort-link"
href="?<?= http_build_query(array_merge($_GET,
[
'sort'=>'load_status',
'dir'=>($sort=='load_status' && $dir=='asc') ? 'desc':'asc'
])) ?>">
Load ▲▼
</a>
</th>

<th>
<a class="sort-link"
href="?<?= http_build_query(array_merge($_GET,
[
'sort'=>'current_location',
'dir'=>($sort=='current_location' && $dir=='asc') ? 'desc':'asc'
])) ?>">
Location ▲▼
</a>
</th>

<th>
Track
</th>

<th>
<a class="sort-link"
href="?<?= http_build_query(array_merge($_GET,
[
'sort'=>'active',
'dir'=>($sort=='active' && $dir=='asc') ? 'desc':'asc'
])) ?>">
Active ▲▼
</a>
</th>

<th></th>

</tr>

</thead>

<tbody>

<?php foreach ($equipment as $item): ?>

<tr
class="clickable-row"
data-href="view.php?id=<?= $item['id'] ?>">

<td onclick="event.stopPropagation();">

<input
type="checkbox"
name="equipment_ids[]"
value="<?= $item['id'] ?>">

</td>

<td>

<?php if (!empty($item['photo_filename'])): ?>

<img
src="../uploads/<?= htmlspecialchars($item['photo_filename']) ?>"
class="img-thumbnail equipment-thumb">

<?php endif; ?>

</td>

<td><?= htmlspecialchars($item['reporting_marks']) ?></td>

<td><?= htmlspecialchars($item['road_number']) ?></td>

<td><?= htmlspecialchars($item['road_name']) ?></td>

<td>

<?php
if (!empty($item['created_at'])) {
    echo date('m/d/Y', strtotime($item['created_at']));
}
?>

</td>

<td><?= htmlspecialchars($item['equipment_class']) ?></td>

<td><?= htmlspecialchars($item['equipment_type']) ?></td>

<td><?= htmlspecialchars($item['manufacturer']) ?></td>

<td><?= htmlspecialchars($item['load_status']) ?></td>

<td><?= htmlspecialchars($item['current_location'] ?: 'Not Assigned') ?></td>

<td><?= htmlspecialchars($item['current_track']) ?></td>

<td>

<?php if ($item['active']): ?>

<span class="badge bg-success">

Active

</span>

<?php else: ?>

<span class="badge bg-secondary">

Inactive

</span>

<?php endif; ?>

</td>

<td onclick="event.stopPropagation();">

<a
href="edit.php?id=<?= $item['id'] ?>"
class="btn btn-sm btn-outline-primary">

Edit

</a>

</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

</div>

<div class="mt-3">

<button
type="submit"
class="btn btn-success">

Print Selected Car Cards

</button>

</div>

</form>

<hr>

<div class="pagination-area text-center">

<?php if ($perPage !== 'all' && $totalPages > 1): ?>

<?php if ($page > 1): ?>

<a
class="btn btn-outline-secondary btn-sm"
href="?<?= http_build_query(array_merge($_GET,['page'=>$page-1])) ?>">

&lt;

</a>

<?php endif; ?>

<?php for ($i=1; $i<=$totalPages; $i++): ?>

<a
class="btn btn-sm <?= ($i==$page) ? 'btn-primary' : 'btn-outline-secondary' ?>"
href="?<?= http_build_query(array_merge($_GET,['page'=>$i])) ?>">

<?= $i ?>

</a>

<?php endfor; ?>

<?php if ($page < $totalPages): ?>

<a
class="btn btn-outline-secondary btn-sm"
href="?<?= http_build_query(array_merge($_GET,['page'=>$page+1])) ?>">

&gt;

</a>

<?php endif; ?>

<?php endif; ?>

</div>

</div>

</main>

</div>

<script src="../assets/js/list_v2.js"></script>

<?php include '../includes/footer.php'; ?>