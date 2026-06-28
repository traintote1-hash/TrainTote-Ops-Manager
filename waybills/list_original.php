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

$waybills = [];

$search = trim($_GET['search'] ?? '');

if ($railroad) {

    if ($search !== '') {

        $stmt = $pdo->prepare("
            SELECT
                w.*,
                wc.commodity,
                wc.status,
                wc.route,
                e.reporting_marks,
                e.road_number,
                oi.industry_name AS origin_name,
                di.industry_name AS destination_name

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

            WHERE w.railroad_id = :railroad_id

            AND (
                e.reporting_marks LIKE :search
                OR e.road_number LIKE :search
                OR oi.industry_name LIKE :search
                OR di.industry_name LIKE :search
                OR wc.commodity LIKE :search
                OR wc.status LIKE :search
            )

            ORDER BY w.id DESC
        ");

        $stmt->execute([
            'railroad_id' => $railroad['id'],
            'search' => '%' . $search . '%'
        ]);

    } else {

        $stmt = $pdo->prepare("
            SELECT
                w.*,
                wc.commodity,
                wc.status,
                wc.route,
                e.reporting_marks,
                e.road_number,
                oi.industry_name AS origin_name,
                di.industry_name AS destination_name

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

            WHERE w.railroad_id = :railroad_id

            ORDER BY w.id DESC
        ");

        $stmt->execute([
            'railroad_id' => $railroad['id']
        ]);
    }

    $waybills = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

?>

<?php include '../includes/header.php'; ?>

<title>Waybills</title>

</head>

<body>

<?php include '../includes/navbar.php'; ?>

<div class="container mt-5">

<h1>Waybills</h1>

<p>

<a
href="add_v2.php"
class="btn btn-primary">

Add Waybill

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
placeholder="Search car, industry, commodity, status..."
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

<?php if (count($waybills) == 0): ?>

<div class="alert alert-warning">

<?php if ($search !== ''): ?>

No matching waybills found.

<?php else: ?>

No waybills created yet.

<?php endif; ?>

</div>

<?php else: ?>

<!-- DESKTOP TABLE -->

<div class="d-none d-md-block">

<div class="table-responsive">

<table class="table table-striped align-middle">

<thead>

<tr>

<th>Car</th>

<th>Origin</th>

<th>Destination</th>

<th>Commodity</th>

<th>Status</th>

<th></th>

</tr>

</thead>

<tbody>
<?php foreach ($waybills as $waybill): ?>

<?php

$badgeClass = 'bg-secondary';

if ($waybill['status'] === 'Loaded') {

    $badgeClass = 'bg-success';

}

?>

<tr
    class="clickable-row"
    data-href="view.php?id=<?php echo $waybill['id']; ?>">

<td>

<strong>

<a
href="view.php?id=<?php echo $waybill['id']; ?>"
style="text-decoration:none;">

<?php

echo htmlspecialchars(

    $waybill['reporting_marks']

    . ' '

    . $waybill['road_number']

);

?>

</a>

</strong>

</td>

<td>

<?php echo htmlspecialchars($waybill['origin_name']); ?>

</td>

<td>

<?php echo htmlspecialchars($waybill['destination_name']); ?>

</td>

<td>

<?php echo htmlspecialchars($waybill['commodity']); ?>

</td>

<td>

<span class="badge <?php echo $badgeClass; ?>">

<?php echo htmlspecialchars($waybill['status']); ?>

</span>

</td>

<td>

<a

href="edit.php?id=<?php echo $waybill['id']; ?>"

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

<?php foreach ($waybills as $waybill): ?>

<?php

$badgeClass = 'bg-secondary';

if ($waybill['status'] === 'Loaded') {

    $badgeClass = 'bg-success';

}

?>

<div class="card mb-3 shadow-sm">

<div class="card-body">

<h5>

<a
href="view.php?id=<?php echo $waybill['id']; ?>"
style="text-decoration:none;">

<?php

echo htmlspecialchars(

    $waybill['reporting_marks']

    . ' '

    . $waybill['road_number']

);

?>

</a>

</h5>

<p>

<span class="badge <?php echo $badgeClass; ?>">

<?php echo htmlspecialchars($waybill['status']); ?>

</span>

</p>

<p>

<strong>Origin:</strong><br>

<?php echo htmlspecialchars($waybill['origin_name']); ?>

</p>

<p>

<strong>Destination:</strong><br>

<?php echo htmlspecialchars($waybill['destination_name']); ?>

</p>

<p>

<strong>Commodity:</strong><br>

<?php echo htmlspecialchars($waybill['commodity']); ?>

</p>

<a
href="edit.php?id=<?php echo $waybill['id']; ?>"
class="btn btn-primary w-100">

Edit Waybill

</a>

</div>

</div>

<?php endforeach; ?>

</div>

<?php endif; ?>

</div>





<?php include '../includes/footer.php'; ?>