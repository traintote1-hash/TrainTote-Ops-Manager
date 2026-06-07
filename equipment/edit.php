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

/*
|--------------------------------------------------------------------------
| Load Industries
|--------------------------------------------------------------------------
*/

$railroadStmt = $pdo->prepare("
    SELECT id
    FROM railroads
    WHERE user_id = :user_id
    LIMIT 1
");

$railroadStmt->execute([
    'user_id' => $_SESSION['user_id']
]);

$railroad = $railroadStmt->fetch(PDO::FETCH_ASSOC);

$industryStmt = $pdo->prepare("
    SELECT id, industry_name
    FROM industries
    WHERE railroad_id = :railroad_id
    ORDER BY industry_name
");

$industryStmt->execute([
    'railroad_id' => $railroad['id']
]);

$industries = $industryStmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $reporting_marks = trim($_POST['reporting_marks']);
    $road_number = trim($_POST['road_number']);
    $road_name = trim($_POST['road_name']);
    $equipment_class = trim($_POST['equipment_class']);
    $equipment_type = trim($_POST['equipment_type']);
    $prototype = trim($_POST['prototype']);
    $service = trim($_POST['service']);
    $manufacturer = trim($_POST['manufacturer']);
    $color = trim($_POST['color']);
    $length_ft = trim($_POST['length_ft']);
    $scale = trim($_POST['scale']);
    $load_status = trim($_POST['load_status']);

    $current_industry_id =
        !empty($_POST['current_industry_id'])
        ? (int)$_POST['current_industry_id']
        : null;

    $current_track = trim($_POST['current_track']);

    $notes = trim($_POST['notes']);

    $stmt = $pdo->prepare("
        UPDATE equipment
        SET
            reporting_marks = :reporting_marks,
            road_number = :road_number,
            road_name = :road_name,
            equipment_class = :equipment_class,
            equipment_type = :equipment_type,
            prototype = :prototype,
            service = :service,
            manufacturer = :manufacturer,
            color = :color,
            length_ft = :length_ft,
            scale = :scale,
            load_status = :load_status,
            current_industry_id = :current_industry_id,
            current_track = :current_track,
            notes = :notes
        WHERE id = :id
    ");

    $stmt->execute([

        'reporting_marks' => $reporting_marks,
        'road_number' => $road_number,
        'road_name' => $road_name,
        'equipment_class' => $equipment_class,
        'equipment_type' => $equipment_type,
        'prototype' => $prototype,
        'service' => $service,
        'manufacturer' => $manufacturer,
        'color' => $color,
        'length_ft' => $length_ft,
        'scale' => $scale,
        'load_status' => $load_status,
        'current_industry_id' => $current_industry_id,
        'current_track' => $current_track,
        'notes' => $notes,
        'id' => $id

    ]);

    header("Location: view.php?id=$id");
    exit;
}

?>

<?php include '../includes/header.php'; ?>

<title>Edit Equipment</title>

</head>

<body>

<?php include '../includes/navbar.php'; ?>

<div class="container mt-5">

<h1>Edit Equipment</h1>

<form method="post">
<div class="mb-3">

<label>Reporting Marks</label>

<input
type="text"
name="reporting_marks"
class="form-control"
value="<?php echo htmlspecialchars($equipment['reporting_marks']); ?>"
required>

</div>

<div class="mb-3">

<label>Road Number</label>

<input
type="text"
name="road_number"
class="form-control"
value="<?php echo htmlspecialchars($equipment['road_number']); ?>"
required>

</div>

<div class="mb-3">

<label>Road Name</label>

<input
type="text"
name="road_name"
class="form-control"
value="<?php echo htmlspecialchars($equipment['road_name']); ?>">

</div>

<div class="mb-3">

<label>Equipment Class</label>

<select name="equipment_class" class="form-select">

<?php

$classes = [
    'Freight Car',
    'Locomotive',
    'Passenger Car',
    'Caboose',
    'MOW'
];

foreach ($classes as $class) {

    $selected =
        ($equipment['equipment_class'] === $class)
        ? 'selected'
        : '';

    echo "<option value=\"$class\" $selected>$class</option>";
}

?>

</select>

</div>

<div class="mb-3">

<label>Equipment Type</label>

<input
type="text"
name="equipment_type"
class="form-control"
value="<?php echo htmlspecialchars($equipment['equipment_type']); ?>">

</div>

<div class="mb-3">

<label>Prototype</label>

<input
type="text"
name="prototype"
class="form-control"
value="<?php echo htmlspecialchars($equipment['prototype']); ?>">

</div>

<div class="mb-3">

<label>Service</label>

<input
type="text"
name="service"
class="form-control"
value="<?php echo htmlspecialchars($equipment['service']); ?>">

</div>

<div class="mb-3">

<label>Manufacturer</label>

<input
type="text"
name="manufacturer"
class="form-control"
value="<?php echo htmlspecialchars($equipment['manufacturer']); ?>">

</div>

<div class="mb-3">

<label>Color</label>

<input
type="text"
name="color"
class="form-control"
value="<?php echo htmlspecialchars($equipment['color']); ?>">

</div>

<div class="mb-3">

<label>Length (Feet)</label>

<input
type="text"
name="length_ft"
class="form-control"
value="<?php echo htmlspecialchars($equipment['length_ft']); ?>">

</div>

<div class="mb-3">

<label>Scale</label>

<select name="scale" class="form-select">

<?php

$scales = ['HO', 'N', 'O', 'S'];

foreach ($scales as $scale) {

    $selected =
        ($equipment['scale'] === $scale)
        ? 'selected'
        : '';

    echo "<option value=\"$scale\" $selected>$scale</option>";
}

?>

</select>

</div>

<div class="mb-3">

<label>Load Status</label>

<select name="load_status" class="form-select">

<?php

$statuses = ['Empty', 'Loaded'];

foreach ($statuses as $status) {

    $selected =
        ($equipment['load_status'] === $status)
        ? 'selected'
        : '';

    echo "<option value=\"$status\" $selected>$status</option>";
}

?>

</select>

</div>
<div class="mb-3">

<label>Current Location</label>

<select
name="current_industry_id"
class="form-select">

<option value="">
Select Location
</option>

<?php foreach ($industries as $industry): ?>

<option
value="<?php echo $industry['id']; ?>"
<?php echo ($equipment['current_industry_id'] == $industry['id']) ? 'selected' : ''; ?>>

<?php echo htmlspecialchars($industry['industry_name']); ?>

</option>

<?php endforeach; ?>

</select>

</div>

<div class="mb-3">

<label>Current Track</label>

<input
type="text"
name="current_track"
class="form-control"
placeholder="Track, spot, yard, lead, etc."
value="<?php echo htmlspecialchars($equipment['current_track']); ?>">

</div>

<div class="mb-3">

<label>Notes</label>

<textarea
name="notes"
class="form-control"
rows="4"><?php echo htmlspecialchars($equipment['notes']); ?></textarea>

</div>

<button
type="submit"
class="btn btn-success">

Save Changes

</button>

<a
href="view.php?id=<?php echo $equipment['id']; ?>"
class="btn btn-secondary">

Cancel

</a>

</form>

</div>

<?php include '../includes/footer.php'; ?>