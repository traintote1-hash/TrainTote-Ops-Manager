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
    trim(
        $_GET['search']
        ?? ''
    );

$page =
    max(
        1,
        (int)(
            $_GET['page']
            ?? 1
        )
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

    'reporting_marks',
    'road_number',
    'equipment_class',
    'created_at'

];

$sort =
    $_GET['sort']
    ?? 'created_at';

if (
    !in_array(
        $sort,
        $allowedSorts
    )
) {

    $sort = 'created_at';

}

$dir =
    strtolower(
        $_GET['dir']
        ?? 'desc'
    );

if (
    !in_array(
        $dir,
        ['asc','desc']
    )
) {

    $dir = 'desc';

}

$orderBy = match ($sort) {

    'road_number' =>
        'road_number',

    'equipment_class' =>
        'equipment_class',

    'created_at' =>
        'created_at',

    default =>
        'reporting_marks'

};

$orderBy .=
    ' ' .
    strtoupper($dir);

$totalRecords = 0;

$totalPages = 1;

$equipment = [];

if ($railroad) {

    $countSql = "

        SELECT COUNT(*)

        FROM equipment

        WHERE railroad_id = :railroad_id

    ";

    if ($search !== '') {

        $countSql .= "

            AND (

                reporting_marks LIKE :search

                OR road_number LIKE :search

                OR equipment_class LIKE :search

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
        (int)
        $countStmt->fetchColumn();
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

        FROM equipment

        WHERE railroad_id = :railroad_id

    ";

    if ($search !== '') {

        $sql .= "

            AND (

                reporting_marks LIKE :search

                OR road_number LIKE :search

                OR equipment_class LIKE :search

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

    $equipment =
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

<title>Print Car Cards</title>

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

<div class="container mt-4">

<h1 class="mb-4">

Print Car Cards

</h1>
<div class="mb-4">

<a
href="list.php"
class="btn btn-secondary">

← Back to Equipment

</a>

</div>
<div class="row mb-4">

<div class="col-md-8">

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

<div class="input-group">

<input
type="text"
name="search"
class="form-control"
placeholder="Search marks, number, class..."
value="<?php echo htmlspecialchars($search); ?>">

<button
class="btn btn-primary"
type="submit">

Search

</button>

<?php if ($search !== ''): ?>

<a
href="print_select_v2.php"
class="btn btn-secondary">

Clear

</a>

<?php endif; ?>

</div>

</form>

</div>

<div class="col-md-4 text-md-end mt-3 mt-md-0">


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

cars

</div>

</div>

</div>

</div>

<form
method="post"
action="print_cards_svg.php">

<div class="mb-3">

<button
type="submit"
class="btn btn-success">

Print Selected Car Cards

</button>

</div>

<script>

document.addEventListener(

    'DOMContentLoaded',

    function() {

        document
        .getElementById('select-all')
        .addEventListener(

            'change',

            function() {

                document
                .querySelectorAll('.car-check')
                .forEach(

                    function(cb) {

                        cb.checked =
                            document
                            .getElementById('select-all')
                            .checked;

                    }

                );

            }

        );

    }

);

</script>
<?php if (count($equipment) === 0): ?>

<div class="alert alert-warning">

No equipment found.

</div>

<?php else: ?>

<div class="table-responsive">

<table class="table table-striped table-hover align-middle">

<thead class="table-dark-header">

<tr>



<th class="text-center">

<input
type="checkbox"
id="select-all"
class="form-check-input">

</th>


<th>Photo</th>

<th>

<a
class="text-white text-decoration-none"
href="?search=<?php echo urlencode($search); ?>&per_page=<?php echo $perPage; ?>&sort=reporting_marks&dir=<?php echo ($sort=='reporting_marks' && $dir=='asc') ? 'desc' : 'asc'; ?>">

Marks

<?php if ($sort=='reporting_marks') echo ($dir=='asc') ? '↑' : '↓'; ?>

</a>

</th>

<th>

<a
class="text-white text-decoration-none"
href="?search=<?php echo urlencode($search); ?>&per_page=<?php echo $perPage; ?>&sort=road_number&dir=<?php echo ($sort=='road_number' && $dir=='asc') ? 'desc' : 'asc'; ?>">

Number

<?php if ($sort=='road_number') echo ($dir=='asc') ? '↑' : '↓'; ?>

</a>

</th>

<th>

<a
class="text-white text-decoration-none"
href="?search=<?php echo urlencode($search); ?>&per_page=<?php echo $perPage; ?>&sort=equipment_class&dir=<?php echo ($sort=='equipment_class' && $dir=='asc') ? 'desc' : 'asc'; ?>">

Equipment Class

<?php if ($sort=='equipment_class') echo ($dir=='asc') ? '↑' : '↓'; ?>

</a>

</th>

<th>

<a
class="text-white text-decoration-none"
href="?search=<?php echo urlencode($search); ?>&per_page=<?php echo $perPage; ?>&sort=created_at&dir=<?php echo ($sort=='created_at' && $dir=='asc') ? 'desc' : 'asc'; ?>">

Date Added

<?php if ($sort=='created_at') echo ($dir=='asc') ? '↑' : '↓'; ?>

</a>

</th>

<th></th>

</tr>

</thead>

<tbody>

<?php foreach ($equipment as $row): ?>

<tr>

<td class="text-center">

<input
type="checkbox"
class="car-check form-check-input"
name="equipment_ids[]"
value="<?php echo $row['id']; ?>">

</td>

<td>

<?php if (!empty($row['photo_filename'])): ?>

<img
src="../uploads/<?php echo htmlspecialchars($row['photo_filename']); ?>"
style="width:100px;max-height:60px;object-fit:contain;">

<?php endif; ?>

</td>

<td>

<?php echo htmlspecialchars($row['reporting_marks']); ?>

</td>

<td>

<?php echo htmlspecialchars($row['road_number']); ?>

</td>

<td>

<?php echo htmlspecialchars($row['equipment_class']); ?>

</td>

<td>

<?php echo date(
    'n/j/Y',
    strtotime($row['created_at'])
); ?>

</td>

<td class="text-nowrap">

<a
href="view.php?id=<?php echo $row['id']; ?>"
class="btn btn-sm btn-primary">

View

</a>

<a
href="edit.php?id=<?php echo $row['id']; ?>"
class="btn btn-sm btn-secondary">

Edit

</a>

</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

</div>

<?php endif; ?>

<div class="mt-4 mb-4">

<button
type="submit"
class="btn btn-success">

Print Selected Car Cards

</button>

</div>



</form>

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

cars

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
