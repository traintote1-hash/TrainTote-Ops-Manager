<?php

session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$sessionWaybills =
    $_SESSION['generated_session'] ?? [];

$difficulty =
    $_SESSION['generated_difficulty'] ?? '';

$carCount =
    $_SESSION['generated_car_count'] ?? 0;

?>

<?php include '../includes/header.php'; ?>

<title>Print Switch List</title>

<style>

@media print {

    .no-print {
        display: none;
    }

}

body {
    padding: 20px;
}

</style>

</head>

<body>

<div class="container mt-4">

<div class="no-print mb-4">

<button
onclick="window.print();"
class="btn btn-primary me-2">

Print

</button>

<button
onclick="window.close();"
class="btn btn-secondary">

Close

</button>

</div>

<h1>TrainTote Ops Manager</h1>

<h3>Switch List</h3>

<p>

<strong>Difficulty:</strong>

<?php echo htmlspecialchars(ucfirst($difficulty)); ?>

<br>

<strong>Cars Requested:</strong>

<?php echo (int)$carCount; ?>

</p>

<hr>

<?php if (count($sessionWaybills) == 0): ?>

<p>No generated session found.</p>

<?php else: ?>

<?php foreach ($sessionWaybills as $index => $waybill): ?>

<div class="mb-4">

<h4>

Move <?php echo $index + 1; ?>

</h4>

<p>

<strong>Car:</strong>

<?php
echo htmlspecialchars(
    $waybill['reporting_marks']
    . ' '
    . $waybill['road_number']
);
?>

<br>

<strong>Origin:</strong>

<?php echo htmlspecialchars($waybill['origin_name']); ?>

<br>

<strong>Destination:</strong>

<?php echo htmlspecialchars($waybill['destination_name']); ?>

<br>

<strong>Commodity:</strong>

<?php echo htmlspecialchars($waybill['commodity']); ?>

<br>

<strong>Status:</strong>

<?php echo htmlspecialchars($waybill['status']); ?>

</p>

<hr>

</div>

<?php endforeach; ?>

<?php endif; ?>

</div>

<?php include '../includes/footer.php'; ?>