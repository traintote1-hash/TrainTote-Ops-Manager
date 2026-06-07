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

    JOIN railroads r
        ON w.railroad_id = r.id

    WHERE w.id = :id
    AND r.user_id = :user_id

    LIMIT 1
");

$stmt->execute([
    'id' => $id,
    'user_id' => $_SESSION['user_id']
]);

$waybill = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$waybill) {
    die('Waybill not found.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $stmt = $pdo->prepare("
        DELETE FROM waybills
        WHERE id = :id
    ");

    $stmt->execute([
        'id' => $id
    ]);

    header('Location: list.php');
    exit;
}

?>

<?php include '../includes/header.php'; ?>

<title>Delete Waybill</title>

</head>

<body>

<?php include '../includes/navbar.php'; ?>

<div class="container mt-5">

<div class="alert alert-danger">

<h3>Delete Waybill</h3>

<p>
Are you sure you want to delete this waybill?
</p>

<h4>

<?php
echo htmlspecialchars(
    $waybill['reporting_marks']
    . ' '
    . $waybill['road_number']
);
?>

</h4>

<p>

<strong>Origin:</strong>
<?php echo htmlspecialchars($waybill['origin_name']); ?>

<br>

<strong>Destination:</strong>
<?php echo htmlspecialchars($waybill['destination_name']); ?>

<br>

<strong>Commodity:</strong>
<?php echo htmlspecialchars($waybill['commodity']); ?>

</p>

<p>
This action cannot be undone.
</p>

<form method="post">

<button
type="submit"
class="btn btn-danger me-2">

Delete Waybill

</button>

<a
href="view.php?id=<?php echo $waybill['id']; ?>"
class="btn btn-secondary">

Cancel

</a>

</form>

</div>

</div>

<?php include '../includes/footer.php'; ?>