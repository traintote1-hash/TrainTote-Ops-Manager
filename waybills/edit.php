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
    SELECT id
    FROM railroads
    WHERE user_id = :user_id
    LIMIT 1
");

$stmt->execute([
    'user_id' => $_SESSION['user_id']
]);

$railroad = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$railroad) {
    die('No railroad found.');
}

$stmt = $pdo->prepare("
    SELECT *
    FROM waybills
    WHERE id = :id
    AND railroad_id = :railroad_id
    LIMIT 1
");

$stmt->execute([
    'id' => $id,
    'railroad_id' => $railroad['id']
]);

$waybill = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$waybill) {
    die('Waybill not found.');
}

$stmt = $pdo->prepare("
    SELECT *
    FROM equipment
    WHERE railroad_id = :railroad_id
    ORDER BY reporting_marks, road_number
");

$stmt->execute([
    'railroad_id' => $railroad['id']
]);

$equipment = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("
    SELECT *
    FROM industries
    WHERE railroad_id = :railroad_id
    ORDER BY industry_name
");

$stmt->execute([
    'railroad_id' => $railroad['id']
]);

$industries = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $stmt = $pdo->prepare("
        UPDATE waybills
        SET
            equipment_id = :equipment_id,
            origin_industry_id = :origin_industry_id,
            destination_industry_id = :destination_industry_id,
            commodity = :commodity,
            route = :route,
            status = :status,
            notes = :notes
        WHERE id = :id
        AND railroad_id = :railroad_id
    ");

    $stmt->execute([
        'equipment_id' => $_POST['equipment_id'],
        'origin_industry_id' => $_POST['origin_industry_id'],
        'destination_industry_id' => $_POST['destination_industry_id'],
        'commodity' => trim($_POST['commodity']),
        'route' => trim($_POST['route']),
        'status' => $_POST['status'],
        'notes' => trim($_POST['notes']),
        'id' => $id,
        'railroad_id' => $railroad['id']
    ]);

    header("Location: view.php?id=$id");
    exit;
}

?>

<?php include '../includes/header.php'; ?>

<title>Edit Waybill</title>

</head>

<body>

<?php include '../includes/navbar.php'; ?>

<div class="container mt-5">

<h1>Edit Waybill</h1>

<form method="post">

<div class="mb-3">

<label>Equipment</label>

<select
name="equipment_id"
class="form-select"
required>

<?php foreach ($equipment as $item): ?>

<option
value="<?php echo $item['id']; ?>"
<?php if ($item['id'] == $waybill['equipment_id']) echo 'selected'; ?>>

<?php
echo htmlspecialchars(
    $item['reporting_marks']
    . ' '
    . $item['road_number']
);
?>

</option>

<?php endforeach; ?>

</select>

</div>

<div class="mb-3">

<label>Origin</label>

<select
name="origin_industry_id"
class="form-select"
required>

<?php foreach ($industries as $industry): ?>

<option
value="<?php echo $industry['id']; ?>"
<?php if ($industry['id'] == $waybill['origin_industry_id']) echo 'selected'; ?>>

<?php echo htmlspecialchars($industry['industry_name']); ?>

</option>

<?php endforeach; ?>

</select>

</div>

<div class="mb-3">

<label>Destination</label>

<select
name="destination_industry_id"
class="form-select"
required>

<?php foreach ($industries as $industry): ?>

<option
value="<?php echo $industry['id']; ?>"
<?php if ($industry['id'] == $waybill['destination_industry_id']) echo 'selected'; ?>>

<?php echo htmlspecialchars($industry['industry_name']); ?>

</option>

<?php endforeach; ?>

</select>

</div>

<div class="mb-3">

<label>Commodity</label>

<input
type="text"
name="commodity"
class="form-control"
value="<?php echo htmlspecialchars($waybill['commodity']); ?>"
required>

</div>

<div class="mb-3">

<label>Route</label>

<input
type="text"
name="route"
class="form-control"
value="<?php echo htmlspecialchars($waybill['route'] ?? ''); ?>"
placeholder="Interchange → Yard → Industry">

</div>

<div class="mb-3">

<label>Status</label>

<select
name="status"
class="form-select">

<option
<?php if ($waybill['status'] == 'Loaded') echo 'selected'; ?>>
Loaded
</option>

<option
<?php if ($waybill['status'] == 'Empty') echo 'selected'; ?>>
Empty
</option>

</select>

</div>

<div class="mb-3">

<label>Notes</label>

<textarea
name="notes"
class="form-control"
rows="4"><?php echo htmlspecialchars($waybill['notes']); ?></textarea>

</div>

<button
type="submit"
class="btn btn-success">

Save Changes

</button>

<a
href="view.php?id=<?php echo $id; ?>"
class="btn btn-secondary">

Cancel

</a>

</form>

</div>

<?php include '../includes/footer.php'; ?>