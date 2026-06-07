<?php

session_start();

require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$stmt = $pdo->prepare("
    SELECT r.id
    FROM railroads r
    WHERE r.user_id = :user_id
    LIMIT 1
");

$stmt->execute([
    'user_id' => $_SESSION['user_id']
]);

$railroad = $stmt->fetch(PDO::FETCH_ASSOC);

$equipment = [];

$search = trim($_GET['search'] ?? '');

if ($railroad) {

    if ($search !== '') {

        $stmt = $pdo->prepare("
            SELECT

                e.*,

                i.industry_name AS current_location

            FROM equipment e

            LEFT JOIN industries i
                ON e.current_industry_id = i.id

            WHERE e.railroad_id = :railroad_id

            AND (

                e.reporting_marks LIKE :search

                OR e.road_number LIKE :search

                OR e.road_name LIKE :search

                OR e.equipment_type LIKE :search

                OR e.prototype LIKE :search

                OR i.industry_name LIKE :search

            )

            ORDER BY e.reporting_marks, e.road_number
        ");

        $stmt->execute([

            'railroad_id' => $railroad['id'],

            'search' => '%' . $search . '%'

        ]);

    } else {

        $stmt = $pdo->prepare("
            SELECT

                e.*,

                i.industry_name AS current_location

            FROM equipment e

            LEFT JOIN industries i
                ON e.current_industry_id = i.id

            WHERE e.railroad_id = :railroad_id

            ORDER BY e.reporting_marks, e.road_number
        ");

        $stmt->execute([

            'railroad_id' => $railroad['id']

        ]);
    }

    $equipment = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

?>

<?php include '../includes/header.php'; ?>

<title>Equipment</title>

</head>

<body>

<?php include '../includes/navbar.php'; ?>

<div class="container mt-5">

<h1>Equipment</h1>

<p>

<a
href="add_select.php"
class="btn btn-primary">

Add Equipment

</a>

</p>

<div class="card mb-4">

<div class="card-body">

<form method="get">

<div class="row g-2">

<div class="col-md-8">

<input
type="text"
name="search"
class="form-control"
placeholder="Search reporting marks, road number, type..."
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

<?php if ($search !== ''): ?>

<div class="alert alert-info">

Showing results for:

<strong>

<?php echo htmlspecialchars($search); ?>

</strong>

</div>

<?php endif; ?>

<?php if (count($equipment) == 0): ?>

<div class="alert alert-warning">

No matching equipment found.

</div>

<?php else: ?>

<form
method="post"
action="print_selected_v2.php">

<div class="d-none d-md-block">

<div class="table-responsive">

<table class="table table-striped align-middle">

<thead>

<tr>

<th width="40">

<input
type="checkbox"
id="selectAll">

</th>

<th>Photo</th>

<th>Equipment</th>

<th>Class</th>

<th>Type</th>

<th>Location</th>

<th></th>

</tr>

</thead>

<tbody>
<?php foreach($equipment as $item): ?>

<?php

$class = $item['equipment_class'];

$badge = 'bg-secondary';

switch ($class) {

    case 'Freight Car':
        $badge = 'bg-primary';
        break;

    case 'Locomotive':
        $badge = 'bg-success';
        break;

    case 'Passenger Car':
        $badge = 'bg-secondary';
        break;

    case 'Caboose':
        $badge = 'bg-warning text-dark';
        break;

    case 'MOW':
        $badge = 'bg-danger';
        break;
}

?>

<tr
class="clickable-row"
data-href="view.php?id=<?php echo $item['id']; ?>"
style="cursor:pointer;">

<td>

<input
type="checkbox"
name="equipment_ids[]"
value="<?php echo $item['id']; ?>">

</td>

<td width="120">

<?php if (!empty($item['photo_filename'])): ?>

<a href="view.php?id=<?php echo $item['id']; ?>">

<img
src="../uploads/<?php echo htmlspecialchars($item['photo_filename']); ?>?v=<?php echo filemtime(dirname(__DIR__) . '/uploads/' . $item['photo_filename']); ?>"
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
href="view.php?id=<?php echo $item['id']; ?>"
style="text-decoration:none;">

<?php echo htmlspecialchars(
    $item['reporting_marks'] . ' ' . $item['road_number']
); ?>

</a>

</strong>

<br>

<small class="text-muted">

<?php echo htmlspecialchars($item['road_name']); ?>

</small>

</td>

<td>

<span class="badge <?php echo $badge; ?>">

<?php echo htmlspecialchars($class); ?>

</span>

</td>

<td>

<?php echo htmlspecialchars($item['equipment_type']); ?>

</td>

<td>

<?php

echo htmlspecialchars(
    $item['current_location'] ?: 'Not Assigned'
);

?>

<br>

<small class="text-muted">

<?php

echo !empty($item['current_track'])
    ? htmlspecialchars($item['current_track'])
    : '';

?>

</small>

</td>

<td>

<a
href="edit.php?id=<?php echo $item['id']; ?>"
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

<div class="mb-4">

<button
type="submit"
class="btn btn-success">

Print Selected Car Cards

</button>

</div>

</form>

<!-- MOBILE CARD VIEW -->

<div class="d-md-none">

<?php foreach($equipment as $item): ?>

<?php

$class = $item['equipment_class'];

$badge = 'bg-secondary';

switch ($class) {

    case 'Freight Car':
        $badge = 'bg-primary';
        break;

    case 'Locomotive':
        $badge = 'bg-success';
        break;

    case 'Passenger Car':
        $badge = 'bg-secondary';
        break;

    case 'Caboose':
        $badge = 'bg-warning text-dark';
        break;

    case 'MOW':
        $badge = 'bg-danger';
        break;
}

?>

<div class="card mb-3 shadow-sm">

<div class="card-body">

<div class="text-center mb-3">
<?php if (!empty($item['photo_filename'])): ?>

<a href="view.php?id=<?php echo $item['id']; ?>">

<img
src="../uploads/<?php echo htmlspecialchars($item['photo_filename']); ?>"
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
href="view.php?id=<?php echo $item['id']; ?>"
style="text-decoration:none;">

<?php echo htmlspecialchars(
    $item['reporting_marks'] . ' ' . $item['road_number']
); ?>

</a>

</h5>

<p class="text-muted">

<?php echo htmlspecialchars($item['road_name']); ?>

</p>

<p>

<span class="badge <?php echo $badge; ?>">

<?php echo htmlspecialchars($class); ?>

</span>

</p>

<p>

<strong>Type:</strong>

<?php echo htmlspecialchars($item['equipment_type']); ?>

</p>

<p>

<strong>Location:</strong><br>

<?php

echo htmlspecialchars(
    $item['current_location'] ?: 'Not Assigned'
);

?>

<?php if (!empty($item['current_track'])): ?>

<br>

<small class="text-muted">

<?php echo htmlspecialchars($item['current_track']); ?>

</small>

<?php endif; ?>

</p>

<a
href="edit.php?id=<?php echo $item['id']; ?>"
class="btn btn-primary w-100">

Edit Equipment

</a>

</div>

</div>

<?php endforeach; ?>

</div>

<?php endif; ?>

</div>



<?php include '../includes/footer.php'; ?>