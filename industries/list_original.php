<?php

session_start();

require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$stmt = $pdo->prepare("
    SELECT id
    FROM railroads
    WHERE user_id = :user_id
    LIMIT 1
");

$stmt->execute([
    'user_id' => $_SESSION['user_id']
]);

$railroad = $stmt->fetch(PDO::FETCH_ASSOC);

$search =
    trim($_GET['search'] ?? '');

$page =
    max(
        1,
        (int)($_GET['page'] ?? 1)
    );

$perPageParam =
    strtolower(
        $_GET['per_page']
        ?? '20'
    );

if ($perPageParam === 'all') {

    $perPage = 'all';

}
else {

    $allowedPerPage =
        [10,20,50,100];

    $perPage =
        (int)$perPageParam;

    if (
        !in_array(
            $perPage,
            $allowedPerPage
        )
    ) {

        $perPage = 20;

    }

}

$allowedSorts = [

    'industry_name',
    'industry_type',
    'location',
    'track_capacity'

];

$sort =
    $_GET['sort']
    ?? 'industry_name';

if (
    !in_array(
        $sort,
        $allowedSorts
    )
) {

    $sort =
        'industry_name';

}

$dir =
    strtolower(
        $_GET['dir']
        ?? 'asc'
    );

if (
    !in_array(
        $dir,
        ['asc','desc']
    )
) {

    $dir =
        'asc';

}

$orderBy = match ($sort) {

    'industry_type' =>
        'industry_type',

    'location' =>
        'location',

    'track_capacity' =>
        'track_capacity',

    default =>
        'industry_name'

};

$orderBy .=
    ' ' .
    strtoupper($dir);

$totalRecords = 0;
$totalPages = 1;
$industries = [];

if ($railroad) {

    $countSql = "

        SELECT COUNT(*)

        FROM industries

        WHERE railroad_id = :railroad_id

    ";

    if ($search !== '') {

        $countSql .= "

            AND (

                industry_name LIKE :search

                OR industry_type LIKE :search

                OR location LIKE :search

            )

        ";

    }

    $countStmt =
        $pdo->prepare(
            $countSql
        );

    $countStmt->bindValue(
        ':railroad_id',
        $railroad['id'],
        PDO::PARAM_INT
    );

    if ($search !== '') {

        $countStmt->bindValue(
            ':search',
            '%' . $search . '%'
        );

    }

    $countStmt->execute();

    $totalRecords =
        (int)$countStmt->fetchColumn();
    if ($perPage !== 'all') {

        $totalPages =
            max(
                1,
                ceil(
                    $totalRecords /
                    $perPage
                )
            );

        if (
            $page > $totalPages
        ) {

            $page =
                $totalPages;

        }

        $offset =
            ($page - 1)
            * $perPage;

    }

    $sql = "

        SELECT *

        FROM industries

        WHERE railroad_id = :railroad_id

    ";

    if ($search !== '') {

        $sql .= "

            AND (

                industry_name LIKE :search

                OR industry_type LIKE :search

                OR location LIKE :search

            )

        ";

    }

    $sql .= "

        ORDER BY

        $orderBy

    ";

    if ($perPage !== 'all') {

        $sql .=

            " LIMIT $offset, $perPage";

    }

    $stmt =
        $pdo->prepare(
            $sql
        );

    $stmt->bindValue(
        ':railroad_id',
        $railroad['id'],
        PDO::PARAM_INT
    );

    if ($search !== '') {

        $stmt->bindValue(
            ':search',
            '%' . $search . '%'
        );

    }

    $stmt->execute();

    $industries =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );

}

if ($perPage === 'all') {

    $startRecord =
        $totalRecords > 0
        ? 1
        : 0;

    $endRecord =
        $totalRecords;

}
else {

    $startRecord =
        $totalRecords > 0
        ? (($page - 1) * $perPage) + 1
        : 0;

    $endRecord =
        min(
            $page * $perPage,
            $totalRecords
        );

}

?>

<?php include '../includes/header.php'; ?>

<title>Industries</title>

<style>

.table-dark-header th{
    background:#333;
    color:#fff;
    vertical-align:middle;
}

.table-dark-header th a{
    color:#fff;
    text-decoration:none;
}

.table-dark-header th a:hover{
    color:#ffc107;
}

</style>

</head>

<body>

<?php include '../includes/navbar.php'; ?>

<div class="container mt-5">

<h1>

Industries

</h1>

<p>

<a
href="add.php"
class="btn btn-primary">

Add Industry

</a>

</p>
<div class="card mb-4">

<div class="card-body">

<form method="get">

<input
type="hidden"
name="sort"
value="<?php echo $sort; ?>">

<input
type="hidden"
name="dir"
value="<?php echo $dir; ?>">

<input
type="hidden"
name="per_page"
value="<?php echo $perPage; ?>">

<div class="row g-2">

<div class="col-md-8">

<input
type="text"
name="search"
class="form-control"
placeholder="Search industry name, type, location..."
value="<?php echo htmlspecialchars($search); ?>">

</div>

<div class="col-md-4">

<button
type="submit"
class="btn btn-primary me-2">

Search

</button>

<a
href="list.php"
class="btn btn-secondary">

Clear

</a>

</div>

</div>

</form>

</div>

</div>

<div class="card mb-4">

<div class="card-body">

<div class="row align-items-center">

<div class="col-md-6">

<label class="me-2">

<strong>Show:</strong>

</label>

<select
class="form-select d-inline-block w-auto"
onchange="window.location=this.value;">

<option
value="?search=<?php echo urlencode($search); ?>&sort=<?php echo $sort; ?>&dir=<?php echo $dir; ?>&per_page=10"
<?php if ($perPage===10) echo 'selected'; ?>>

10

</option>

<option
value="?search=<?php echo urlencode($search); ?>&sort=<?php echo $sort; ?>&dir=<?php echo $dir; ?>&per_page=20"
<?php if ($perPage===20) echo 'selected'; ?>>

20

</option>

<option
value="?search=<?php echo urlencode($search); ?>&sort=<?php echo $sort; ?>&dir=<?php echo $dir; ?>&per_page=50"
<?php if ($perPage===50) echo 'selected'; ?>>

50

</option>

<option
value="?search=<?php echo urlencode($search); ?>&sort=<?php echo $sort; ?>&dir=<?php echo $dir; ?>&per_page=100"
<?php if ($perPage===100) echo 'selected'; ?>>

100

</option>

<option
value="?search=<?php echo urlencode($search); ?>&sort=<?php echo $sort; ?>&dir=<?php echo $dir; ?>&per_page=all"
<?php if ($perPage==='all') echo 'selected'; ?>>

All

</option>

</select>

</div>

<div class="col-md-6 text-md-end">

Showing

<strong>

<?php echo $startRecord; ?>

-

<?php echo $endRecord; ?>

</strong>

of

<strong>

<?php echo $totalRecords; ?>

</strong>

industries

</div>

</div>

</div>

</div>

<?php if ($search !== ''): ?>

<div class="alert alert-info">

Showing results for:

<strong>

<?php echo htmlspecialchars($search); ?>

</strong>

</div>

<?php endif; ?>

<?php if (count($industries) == 0): ?>

<div class="alert alert-warning">

No matching industries found.

</div>

<?php else: ?>

<!-- DESKTOP TABLE -->

<div class="d-none d-md-block">

<div class="table-responsive">

<table class="table table-striped align-middle">

<thead class="table-dark-header">

<tr>

<th>Photo</th>

<th>

<a
class="text-white text-decoration-none"
href="?search=<?php echo urlencode($search); ?>&per_page=<?php echo $perPage; ?>&sort=industry_name&dir=<?php echo ($sort=='industry_name' && $dir=='asc') ? 'desc' : 'asc'; ?>">

Industry

<?php if ($sort=='industry_name') echo ($dir=='asc') ? '↑' : '↓'; ?>

</a>

</th>

<th>

<a
class="text-white text-decoration-none"
href="?search=<?php echo urlencode($search); ?>&per_page=<?php echo $perPage; ?>&sort=industry_type&dir=<?php echo ($sort=='industry_type' && $dir=='asc') ? 'desc' : 'asc'; ?>">

Type

<?php if ($sort=='industry_type') echo ($dir=='asc') ? '↑' : '↓'; ?>

</a>

</th>

<th>

<a
class="text-white text-decoration-none"
href="?search=<?php echo urlencode($search); ?>&per_page=<?php echo $perPage; ?>&sort=location&dir=<?php echo ($sort=='location' && $dir=='asc') ? 'desc' : 'asc'; ?>">

Location

<?php if ($sort=='location') echo ($dir=='asc') ? '↑' : '↓'; ?>

</a>

</th>

<th>

<a
class="text-white text-decoration-none"
href="?search=<?php echo urlencode($search); ?>&per_page=<?php echo $perPage; ?>&sort=track_capacity&dir=<?php echo ($sort=='track_capacity' && $dir=='asc') ? 'desc' : 'asc'; ?>">

Capacity

<?php if ($sort=='track_capacity') echo ($dir=='asc') ? '↑' : '↓'; ?>

</a>

</th>

<th></th>

</tr>

</thead>

<tbody>

<?php foreach($industries as $industry): ?>

<tr
class="clickable-row"
data-href="view.php?id=<?php echo $industry['id']; ?>">

<td width="120">

<?php if (!empty($industry['photo_filename'])): ?>

<a
href="view.php?id=<?php echo $industry['id']; ?>">

<img
src="../uploads/<?php echo htmlspecialchars($industry['photo_filename']); ?>?v=<?php echo filemtime(dirname(__DIR__) . '/uploads/' . $industry['photo_filename']); ?>"
class="img-thumbnail"
style="width:100px;height:60px;object-fit:contain;">

</a>

<?php else: ?>

<div
class="border rounded text-center bg-light"
style="width:100px;height:60px;line-height:60px;">

📷

</div>

<?php endif; ?>

</td>

<td>

<strong>

<a
href="view.php?id=<?php echo $industry['id']; ?>"
style="text-decoration:none;">

<?php echo htmlspecialchars($industry['industry_name']); ?>

</a>

</strong>

<br>

<small class="text-muted">

<?php echo htmlspecialchars($industry['industry_type']); ?>

</small>

</td>

<td>

<?php echo htmlspecialchars($industry['industry_type']); ?>

</td>

<td>

<?php echo htmlspecialchars($industry['location']); ?>

</td>

<td>

<?php echo htmlspecialchars($industry['track_capacity']); ?>

</td>

<td onclick="event.stopPropagation();">

<a
href="edit.php?id=<?php echo $industry['id']; ?>"
class="btn btn-sm btn-outline-primary">

Edit

</a>

</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

</div>

</div>

<!-- MOBILE CARD VIEW -->

<div class="d-md-none">

<?php foreach($industries as $industry): ?>

<div class="card mb-3 shadow-sm">

<div class="card-body">

<div class="text-center mb-3">

<?php if (!empty($industry['photo_filename'])): ?>

<a href="view.php?id=<?php echo $industry['id']; ?>">

<img
src="../uploads/<?php echo htmlspecialchars($industry['photo_filename']); ?>"
class="img-fluid rounded border"
style="max-height:150px;">

</a>

<?php else: ?>

<div class="border rounded p-4 bg-light">

📷 No Photo

</div>

<?php endif; ?>

</div>

<h5>

<a
href="view.php?id=<?php echo $industry['id']; ?>"
style="text-decoration:none;">

<?php echo htmlspecialchars($industry['industry_name']); ?>

</a>

</h5>

<p class="text-muted">

<?php echo htmlspecialchars($industry['industry_type']); ?>

</p>

<p>

<strong>Location:</strong>

<?php echo htmlspecialchars($industry['location']); ?>

</p>

<p>

<strong>Track Capacity:</strong>

<?php echo htmlspecialchars($industry['track_capacity']); ?>

cars

</p>

<a
href="edit.php?id=<?php echo $industry['id']; ?>"
class="btn btn-primary w-100">

Edit Industry

</a>

</div>

</div>

<?php endforeach; ?>

</div>

<?php endif; ?>
<hr>

<div class="row align-items-center mb-4">

<div class="col-md-4">

Showing

<strong>

<?php echo $startRecord; ?>

-

<?php echo $endRecord; ?>

</strong>

of

<strong>

<?php echo $totalRecords; ?>

</strong>

industries

</div>

<div class="col-md-4 text-center">

<?php if ($perPage !== 'all'): ?>

Page

<strong>

<?php echo $page; ?>

</strong>

of

<strong>

<?php echo $totalPages; ?>

</strong>

<?php endif; ?>

</div>

<div class="col-md-4 text-end">

<?php if ($perPage !== 'all' && $totalPages > 1): ?>

<?php if ($page > 1): ?>

<a
href="?search=<?php echo urlencode($search); ?>&sort=<?php echo $sort; ?>&dir=<?php echo $dir; ?>&per_page=<?php echo $perPage; ?>&page=<?php echo $page - 1; ?>"
class="btn btn-outline-primary">

← Previous

</a>

<?php endif; ?>

<?php if ($page < $totalPages): ?>

<a
href="?search=<?php echo urlencode($search); ?>&sort=<?php echo $sort; ?>&dir=<?php echo $dir; ?>&per_page=<?php echo $perPage; ?>&page=<?php echo $page + 1; ?>"
class="btn btn-outline-primary">

Next →

</a>

<?php endif; ?>

<?php endif; ?>

</div>

</div>

</div>

<?php include '../includes/footer.php'; ?>