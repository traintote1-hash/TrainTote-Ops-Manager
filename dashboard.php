<?php

session_start();

require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
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

$equipmentCount = 0;
$industryCount = 0;
$waybillCount = 0;

$recentEquipment = [];
$recentIndustries = [];
$recentWaybills = [];

if ($railroad) {

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM equipment
        WHERE railroad_id = :railroad_id
    ");

    $stmt->execute([
        'railroad_id' => $railroad['id']
    ]);

    $equipmentCount = $stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM industries
        WHERE railroad_id = :railroad_id
    ");

    $stmt->execute([
        'railroad_id' => $railroad['id']
    ]);

    $industryCount = $stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM waybills
        WHERE railroad_id = :railroad_id
    ");

    $stmt->execute([
        'railroad_id' => $railroad['id']
    ]);

    $waybillCount = $stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT *
        FROM equipment
        WHERE railroad_id = :railroad_id
        ORDER BY id DESC
        LIMIT 5
    ");

    $stmt->execute([
        'railroad_id' => $railroad['id']
    ]);

    $recentEquipment = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("
        SELECT *
        FROM industries
        WHERE railroad_id = :railroad_id
        ORDER BY id DESC
        LIMIT 5
    ");

    $stmt->execute([
        'railroad_id' => $railroad['id']
    ]);

    $recentIndustries = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("
        SELECT
            w.*,
            e.reporting_marks,
            e.road_number,
            oi.industry_name AS origin_name,
            di.industry_name AS destination_name
        FROM waybills w
        JOIN equipment e
            ON w.equipment_id = e.id
        JOIN industries oi
            ON w.origin_industry_id = oi.id
        JOIN industries di
            ON w.destination_industry_id = di.id
        WHERE w.railroad_id = :railroad_id
        ORDER BY w.id DESC
        LIMIT 5
    ");

    $stmt->execute([
        'railroad_id' => $railroad['id']
    ]);

    $recentWaybills = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

?>

<?php include 'includes/header.php'; ?>

<title>TrainTote Ops Manager</title>

</head>

<body>

<?php include 'includes/navbar.php'; ?>

<div class="container mt-5">

<h1>TrainTote Ops Manager</h1>

<p class="text-muted">

Operations Dashboard

</p>

<div class="row mb-4">

<div class="col-md-4">

<div class="card h-100">

<div class="card-body">

<h5>Equipment</h5>

<h2><?php echo $equipmentCount; ?> Total Cars</h2>

<a
href="equipment/list.php"
class="btn btn-primary">

Open Equipment

</a>

</div>

</div>

</div>

<div class="col-md-4">

<div class="card h-100">

<div class="card-body">

<h5>Industries</h5>

<h2><?php echo $industryCount; ?> Locations</h2>

<a
href="industries/list.php"
class="btn btn-success">

Open Industries

</a>

</div>

</div>

</div>

<div class="col-md-4">

<div class="card h-100">

<div class="card-body">

<h5>Waybills</h5>

<h2><?php echo $waybillCount; ?> Active</h2>

<a
href="waybills/list.php"
class="btn btn-warning">

Open Waybills

</a>

</div>

</div>

</div>

</div>

<div class="row">

<div class="col-md-4">

<div class="card mb-4">

<div class="card-header">

Recent Equipment

</div>

<div class="card-body">

<?php if (count($recentEquipment) == 0): ?>

<p class="text-muted">

No equipment added yet.

</p>

<?php endif; ?>

<?php foreach ($recentEquipment as $item): ?>

<div class="d-flex align-items-center mb-3">

<?php if (!empty($item['photo_filename'])): ?>

<a href="equipment/view.php?id=<?php echo $item['id']; ?>">

<img
src="uploads/<?php echo htmlspecialchars($item['photo_filename']); ?>"
class="img-thumbnail me-3"
style="width:80px;height:50px;object-fit:contain;">

</a>

<?php endif; ?>

<div>

<a
href="equipment/view.php?id=<?php echo $item['id']; ?>"
style="text-decoration:none;">

<strong>

<?php
echo htmlspecialchars(
    $item['reporting_marks']
    . ' '
    . $item['road_number']
);
?>

</strong>

</a>

</div>

</div>

<?php endforeach; ?>

</div>

</div>

</div>

<div class="col-md-4">

<div class="card mb-4">

<div class="card-header">

Recent Industries

</div>

<div class="card-body">

<?php if (count($recentIndustries) == 0): ?>

<p class="text-muted">

No industries added yet.

</p>

<?php endif; ?>

<?php foreach ($recentIndustries as $industry): ?>

<div class="d-flex align-items-center mb-3">

<?php if (!empty($industry['photo_filename'])): ?>

<a href="industries/view.php?id=<?php echo $industry['id']; ?>">

<img
src="uploads/<?php echo htmlspecialchars($industry['photo_filename']); ?>"
class="img-thumbnail me-3"
style="width:80px;height:50px;object-fit:contain;">

</a>

<?php endif; ?>

<div>

<a
href="industries/view.php?id=<?php echo $industry['id']; ?>"
style="text-decoration:none;">

<strong>

<?php echo htmlspecialchars($industry['industry_name']); ?>

</strong>

</a>

</div>

</div>

<?php endforeach; ?>

</div>

</div>

</div>

<div class="col-md-4">

<div class="card mb-4">

<div class="card-header">

Recent Waybills

</div>

<div class="card-body">

<?php if (count($recentWaybills) == 0): ?>

<p class="text-muted">

No waybills created yet.

</p>

<?php endif; ?>

<?php foreach ($recentWaybills as $waybill): ?>

<div class="mb-3">

<a
href="waybills/view.php?id=<?php echo $waybill['id']; ?>"
style="text-decoration:none;">

<strong>

<?php
echo htmlspecialchars(
    $waybill['reporting_marks']
    . ' '
    . $waybill['road_number']
);
?>

</strong>

</a>

<br>

<small>

<?php echo htmlspecialchars($waybill['origin_name']); ?>

 →

<?php echo htmlspecialchars($waybill['destination_name']); ?>

</small>

</div>

<?php endforeach; ?>

</div>

</div>

</div>

</div>

<div class="card">

<div class="card-body">

<h4>Upcoming Modules</h4>

<ul>
<li>Switch Lists</li>
<li>Car Routing</li>
<li>Operating Sessions</li>
</ul>

</div>

</div>

</div>

<?php include 'includes/footer.php'; ?>