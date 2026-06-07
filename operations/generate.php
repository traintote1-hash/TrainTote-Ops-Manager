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

$sessionWaybills = [];

$difficulty = $_POST['difficulty'] ?? 'medium';
$carCount = (int)($_POST['car_count'] ?? 5);

if ($carCount < 1) {
    $carCount = 1;
}

if ($carCount > 50) {
    $carCount = 50;
}

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && $railroad
) {

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
    ");

    $stmt->execute([
        'railroad_id' => $railroad['id']
    ]);

    $allWaybills = $stmt->fetchAll(PDO::FETCH_ASSOC);

    shuffle($allWaybills);

    $sessionWaybills = array_slice(
        $allWaybills,
        0,
        min($carCount, count($allWaybills))
    );

    $_SESSION['generated_session'] = $sessionWaybills;
    $_SESSION['generated_difficulty'] = $difficulty;
    $_SESSION['generated_car_count'] = $carCount;
}

?>

<?php include '../includes/header.php'; ?>

<title>Generate Session</title>

</head>

<body>

<?php include '../includes/navbar.php'; ?>

<div class="container mt-5">

<h1>Generate Operating Session</h1>

<p class="text-muted">

Create a random switch list from existing waybills.

</p>

<div class="card mb-4">

<div class="card-body">

<form method="post">

<div class="mb-3">

<label class="form-label">

Difficulty

</label>

<div>

<div class="form-check">

<input
class="form-check-input"
type="radio"
name="difficulty"
value="easy"
<?php if ($difficulty === 'easy') echo 'checked'; ?>>

<label class="form-check-label">

Easy

</label>

</div>

<div class="form-check">

<input
class="form-check-input"
type="radio"
name="difficulty"
value="medium"
<?php if ($difficulty === 'medium') echo 'checked'; ?>>

<label class="form-check-label">

Medium

</label>

</div>

<div class="form-check">

<input
class="form-check-input"
type="radio"
name="difficulty"
value="hard"
<?php if ($difficulty === 'hard') echo 'checked'; ?>>

<label class="form-check-label">

Hard

</label>

</div>

</div>

</div>

<div class="mb-3">

<label class="form-label">

Cars To Switch

</label>

<input
type="number"
name="car_count"
class="form-control"
value="<?php echo $carCount; ?>"
min="1"
max="50">

</div>

<button
type="submit"
class="btn btn-primary">

Generate Session

</button>

</form>

</div>

</div>

<?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>

<?php if (count($sessionWaybills) == 0): ?>

<div class="alert alert-warning">

No waybills available.

Create some waybills first.

</div>

<?php else: ?>

<div class="card">

<div class="card-header">

Generated Session

</div>

<div class="card-body">

<p>

<strong>Difficulty:</strong>

<?php echo ucfirst($difficulty); ?>

<br>

<strong>Cars Requested:</strong>

<?php echo $carCount; ?>

</p>

<hr>

<?php foreach ($sessionWaybills as $index => $waybill): ?>

<div class="mb-4">

<h5>

Move <?php echo $index + 1; ?>

</h5>

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

<form method="post">

<input
type="hidden"
name="difficulty"
value="<?php echo htmlspecialchars($difficulty); ?>">

<input
type="hidden"
name="car_count"
value="<?php echo $carCount; ?>">

<div class="mt-3">

<button
type="submit"
class="btn btn-success me-2">

Generate Again

</button>

<a
href="print.php"
target="_blank"
class="btn btn-primary">

Print Switch List

</a>

</div>

</form>

</div>

</div>

<?php endif; ?>

<?php endif; ?>

</div>

<?php include '../includes/footer.php'; ?>