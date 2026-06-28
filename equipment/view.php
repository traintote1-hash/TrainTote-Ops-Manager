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
    SELECT

        e.*,

        i.industry_name AS current_location

    FROM equipment e

    LEFT JOIN industries i
        ON e.current_industry_id = i.id

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

<title>Equipment Details</title>

</head>

<body>

<?php include '../includes/navbar.php'; ?>

<div class="container mt-5">

<h1>

<?php

echo htmlspecialchars(
    $equipment['reporting_marks']
    . ' '
    . $equipment['road_number']
);

?>

</h1>

<div class="card">

<div class="card-body">

<?php if (!empty($equipment['photo_filename'])): ?>

<div class="mb-4 text-center">

<a
href="../uploads/<?php echo htmlspecialchars($equipment['photo_filename']); ?>?v=<?php echo filemtime(dirname(__DIR__) . '/uploads/' . $equipment['photo_filename']); ?>"
target="_blank">

<img
src="../uploads/<?php echo htmlspecialchars($equipment['photo_filename']); ?>?v=<?php echo filemtime(dirname(__DIR__) . '/uploads/' . $equipment['photo_filename']); ?>"
class="img-fluid rounded border"
style="max-height:300px;"
title="Click for full size">

</a>

</div>

<?php else: ?>

<div class="alert alert-secondary text-center">

No Photo Uploaded

</div>

<?php endif; ?>

<hr>
<p>

<strong>Road Name:</strong>

<?php echo htmlspecialchars($equipment['road_name']); ?>

</p>

<p>

<strong>Equipment Class:</strong>

<?php echo htmlspecialchars($equipment['equipment_class']); ?>

</p>

<p>

<strong>Equipment Type:</strong>

<?php echo htmlspecialchars($equipment['equipment_type']); ?>

</p>

<p>

<strong>Prototype:</strong>

<?php echo htmlspecialchars($equipment['prototype']); ?>

</p>

<p>

<strong>Service:</strong>

<?php echo htmlspecialchars($equipment['service']); ?>

</p>

<p>

<strong>Operations Service:</strong>

<?php echo htmlspecialchars($equipment['operations_service'] ?? ''); ?>

</p>

<p>

<strong>Manufacturer:</strong>

<?php echo htmlspecialchars($equipment['manufacturer']); ?>

</p>

<p>

<strong>Color:</strong>

<?php echo htmlspecialchars($equipment['color']); ?>

</p>

<p>

<strong>Length:</strong>

<?php echo htmlspecialchars($equipment['length_ft']); ?>'

</p>

<p>

<strong>Scale:</strong>

<?php echo htmlspecialchars($equipment['scale']); ?>

</p>

<p>

<strong>Load Status:</strong>

<?php echo htmlspecialchars($equipment['load_status']); ?>

</p>

<hr>

<div class="card border-primary bg-light mb-3">

<div class="card-header">

📍 <strong>Current Location</strong>

</div>

<div class="card-body">

<div class="row">

<div class="col-md-6">

<strong>Industry</strong>

<br>

<?php

echo htmlspecialchars(
    $equipment['current_location']
    ?: 'Not Assigned'
);

?>

</div>

<div class="col-md-6">

<strong>Track</strong>

<br>

<?php

echo !empty($equipment['current_track'])
    ? htmlspecialchars($equipment['current_track'])
    : 'Not Assigned';

?>

</div>

</div>

</div>

</div>

<p>

<strong>Notes:</strong>

<br>

<?php echo nl2br(htmlspecialchars($equipment['notes'])); ?>

</p>
</div>

</div>

<div class="mt-3 mb-5">

<a
href="edit.php?id=<?php echo $equipment['id']; ?>"
class="btn btn-primary me-2">

Edit Equipment

</a>

<a
href="upload_photo.php?id=<?php echo $equipment['id']; ?>"
class="btn btn-success me-2">

Upload Photo

</a>

<?php if (!empty($equipment['photo_filename'])): ?>

<a
href="../photo_editor/edit.php?type=equipment&id=<?php echo $equipment['id']; ?>"
class="btn btn-warning me-2">

Edit Photo

</a>

<a
href="car_card_v3.php?id=<?php echo $equipment['id']; ?>"
class="btn btn-dark me-2">

Print Car Card

</a>

<?php endif; ?>

<a
href="add_select.php"
class="btn btn-info me-2">

Add Another

</a>

<a
href="delete.php?id=<?php echo $equipment['id']; ?>"
class="btn btn-danger me-2">

Delete Equipment

</a>

<a
href="list.php"
class="btn btn-secondary">

Back to List

</a>

</div>

</div>

<?php include '../includes/footer.php'; ?>