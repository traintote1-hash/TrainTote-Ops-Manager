<?php

session_start();

require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

if (!isset($_GET['id'])) {
    die('Waybill ID missing.');
}

$id = (int)$_GET['id'];

$stmt = $pdo->prepare("
    SELECT

        w.*,

        e.reporting_marks,

        e.road_number

    FROM waybills w

    JOIN equipment e
        ON w.equipment_id = e.id

    WHERE w.id = :id

    LIMIT 1
");

$stmt->execute([
    'id' => $id
]);

$waybill = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$waybill) {
    die('Waybill not found.');
}

$cycleStmt = $pdo->prepare("
    SELECT

        wc.*,

        oi.industry_name AS origin_name,

        di.industry_name AS destination_name

    FROM waybill_cycles wc

    LEFT JOIN industries oi
        ON wc.origin_industry_id = oi.id

    LEFT JOIN industries di
        ON wc.destination_industry_id = di.id

    WHERE wc.waybill_id = :waybill_id

    ORDER BY wc.cycle_number
");

$cycleStmt->execute([
    'waybill_id' => $id
]);

$cycles = $cycleStmt->fetchAll(PDO::FETCH_ASSOC);

?>

<?php include '../includes/header.php'; ?>

<title>Waybill Details</title>

</head>

<body>

<?php include '../includes/navbar.php'; ?>

<div class="container mt-5">

<h1>Waybill Details</h1>

<div class="card mb-4">

<div class="card-body">

<p>

<strong>Equipment:</strong>

<?php
echo htmlspecialchars(
    $waybill['reporting_marks']
    . ' '
    . $waybill['road_number']
);
?>

</p>

<p>

<strong>Current Cycle:</strong>

<?php
echo (int)$waybill['current_cycle'];
?>

of

<?php
echo (int)$waybill['cycle_count'];
?>

</p>

</div>

</div>

<div class="card">

<div class="card-body">

<h4>Waybill Cycles</h4>

<div class="table-responsive">

<table class="table table-striped table-bordered">

<thead>

<tr>

<th>Cycle</th>
<th>Origin</th>
<th>Destination</th>
<th>Commodity</th>
<th>Route</th>
<th>Status</th>

</tr>

</thead>

<tbody>

<?php foreach ($cycles as $cycle): ?>

<tr
<?php
if ($cycle['cycle_number'] == $waybill['current_cycle']) {
    echo 'class="table-primary"';
}
?>
>

<td>

<strong>

<?php echo $cycle['cycle_number']; ?>

</strong>

</td>

<td>

<?php echo htmlspecialchars($cycle['origin_name'] ?? ''); ?>

</td>

<td>

<?php echo htmlspecialchars($cycle['destination_name'] ?? ''); ?>

</td>

<td>

<?php echo htmlspecialchars($cycle['commodity'] ?? ''); ?>

</td>

<td>

<?php echo htmlspecialchars($cycle['route'] ?? ''); ?>

</td>

<td>

<?php echo htmlspecialchars($cycle['status'] ?? ''); ?>

</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

</div>

</div>

</div>

<div class="mt-3">

<a
href="edit.php?id=<?php echo $waybill['id']; ?>"
class="btn btn-primary me-2">

Edit Waybill

</a>

<a
href="add_v2.php"
class="btn btn-info me-2">

Add Another

</a>

<a
href="print_v2.php?id=<?php echo $waybill['id']; ?>"
class="btn btn-success me-2">

Print Waybill

</a>

<a
href="delete.php?id=<?php echo $waybill['id']; ?>"
class="btn btn-danger me-2">

Delete Waybill

</a>

<a
href="list.php"
class="btn btn-secondary">

Back to Waybills

</a>

</div>

</div>

<?php include '../includes/footer.php'; ?>