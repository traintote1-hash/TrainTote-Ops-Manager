<?php

session_start();

require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

if (!isset($_GET['id'])) {
    die('Equipment ID missing.');
}

$id = (int)$_GET['id'];

$stmt = $pdo->prepare("
    SELECT e.*
    FROM equipment e
    JOIN railroads r
        ON e.railroad_id = r.id
    WHERE e.id = :id
    AND r.user_id = :user_id
    LIMIT 1
");

$stmt->execute([
    'id' => $id,
    'user_id' => $_SESSION['user_id']
]);

$equipment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$equipment) {
    die('Equipment not found.');
}

?>

<?php include '../includes/header.php'; ?>

<title>Car Card</title>

<style>

body {
    background: #f5f5f5;
}

.car-card {

    width: 2.25in;
    height: 4in;

    background: #fff;

    border: 2px solid #000;

    margin: 20px auto;

    display: flex;
    flex-direction: column;

    box-shadow: 0 0 10px rgba(0,0,0,.15);

    overflow: hidden;
}

.card-photo {

    height: 38%;

    border-bottom: 1px solid #000;

    display: flex;
    align-items: center;
    justify-content: center;

    background: #fafafa;
}

.card-photo img {

    width: 100%;
    height: 100%;

    object-fit: contain;
}

.no-photo {

    font-size: 12px;
    color: #666;
    text-align: center;
}

.card-info {

    height: 22%;

    border-bottom: 1px solid #000;

    padding: 6px;

    font-size: 11px;

    line-height: 1.2;
}

.card-number {

    font-size: 14px;
    font-weight: bold;
}

.card-pocket {

    flex-grow: 1;

    display: flex;
    align-items: center;
    justify-content: center;

    font-size: 14px;
    font-weight: bold;
    color: #666;

    background:
    repeating-linear-gradient(
        45deg,
        #fafafa,
        #fafafa 10px,
        #f0f0f0 10px,
        #f0f0f0 20px
    );
}

@media print {

    .no-print {
        display:none;
    }

    body {
        background:#fff;
    }

    .car-card {
        margin:0;
        box-shadow:none;
    }
}

</style>

</head>

<body>

<?php include '../includes/navbar.php'; ?>

<div class="container mt-4">

<div class="no-print mb-3">

<h1>Car Card Preview</h1>

<a
href="view.php?id=<?php echo $equipment['id']; ?>"
class="btn btn-secondary me-2">

Back to Equipment

</a>

<button
onclick="window.print();"
class="btn btn-primary">

Print Card

</button>

</div>

<div class="car-card">

<div class="card-photo">

<?php if (!empty($equipment['photo_filename'])): ?>

<img
src="../uploads/<?php echo htmlspecialchars($equipment['photo_filename']); ?>?v=<?php echo time(); ?>"
alt="Equipment Photo">

<?php else: ?>

<div class="no-photo">

NO PHOTO AVAILABLE

</div>

<?php endif; ?>

</div>

<div class="card-info">

<div class="card-number">

<?php
echo htmlspecialchars(
    $equipment['reporting_marks']
    . ' '
    . $equipment['road_number']
);
?>

</div>

<div>

<?php
echo htmlspecialchars(
    $equipment['length_ft']
);
?>'

<?php
echo strtoupper(
    htmlspecialchars(
        $equipment['equipment_type']
    )
);
?>

</div>

<div>

<?php
echo htmlspecialchars(
    $equipment['color']
);
?>

</div>

<div>

<?php
echo htmlspecialchars(
    $equipment['load_status']
);
?>

</div>

</div>

<div class="card-pocket">

WAYBILL POCKET

</div>

</div>

</div>

<?php include '../includes/footer.php'; ?>