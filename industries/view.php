<?php

session_start();

require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

if (!isset($_GET['id'])) {
    die('Industry ID missing.');
}

$id = (int)$_GET['id'];

$stmt = $pdo->prepare("
    SELECT i.*
    FROM industries i
    JOIN railroads r
        ON i.railroad_id = r.id
    WHERE i.id = :id
    AND r.user_id = :user_id
    LIMIT 1
");

$stmt->execute([
    'id' => $id,
    'user_id' => $_SESSION['user_id']
]);

$industry = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$industry) {
    die('Industry not found.');
}

$previousIndustryId = null;
$nextIndustryId = null;

$stmt = $pdo->prepare("
    SELECT id
    FROM industries
    WHERE railroad_id = :railroad_id
    ORDER BY industry_name ASC, id ASC
");

$stmt->execute([
    'railroad_id' => $industry['railroad_id']
]);

$industryIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
$currentIndustryIndex = array_search($industry['id'], $industryIds);

if ($currentIndustryIndex !== false) {
    if ($currentIndustryIndex > 0) {
        $previousIndustryId = $industryIds[$currentIndustryIndex - 1];
    }

    if ($currentIndustryIndex < count($industryIds) - 1) {
        $nextIndustryId = $industryIds[$currentIndustryIndex + 1];
    }
}
?>

<?php include '../includes/header.php'; ?>

<title>Industry Details</title>

</head>

<body>

<?php include '../includes/navbar.php'; ?>

<div class="container mt-5">

<h1>Industry Details</h1>

<div class="card">

<div class="card-body">

<?php if (!empty($industry['photo_filename'])): ?>

<div class="mb-4">

<img
src="../uploads/<?php echo htmlspecialchars($industry['photo_filename']); ?>?v=<?php echo filemtime(dirname(__DIR__) . '/uploads/' . $industry['photo_filename']); ?>"
class="img-fluid rounded border"
style="max-height:250px; max-width:400px;">

</div>

<?php else: ?>

<div class="alert alert-secondary text-center">

No Photo Uploaded

</div>

<?php endif; ?>

<h3>

<?php echo htmlspecialchars($industry['industry_name']); ?>

</h3>

<hr>

<p>
<strong>Industry Type:</strong>
<?php echo htmlspecialchars($industry['industry_type']); ?>
</p>

<p>
<strong>Location:</strong>
<?php echo htmlspecialchars($industry['location']); ?>
</p>

<p>
<strong>Track Capacity:</strong>
<?php echo htmlspecialchars($industry['track_capacity']); ?> Cars
</p>

<p>
<strong>Receives Services:</strong><br>
<?php echo nl2br(htmlspecialchars($industry['receives_services'] ?? '')); ?>
</p>

<p>
<strong>Ships Services:</strong><br>
<?php echo nl2br(htmlspecialchars($industry['ships_services'] ?? '')); ?>
</p>

<p>
<strong>Notes:</strong><br>
<?php echo nl2br(htmlspecialchars($industry['notes'])); ?>
</p>

</div>

</div>

<div class="mt-3">

<?php if ($previousIndustryId): ?>

<a
href="view.php?id=<?php echo (int)$previousIndustryId; ?>"
class="btn btn-outline-secondary me-2">

Previous Industry

</a>

<?php else: ?>

<span class="btn btn-outline-secondary me-2 disabled">

Previous Industry

</span>

<?php endif; ?>

<?php if ($nextIndustryId): ?>

<a
href="view.php?id=<?php echo (int)$nextIndustryId; ?>"
class="btn btn-outline-secondary me-2">

Next Industry

</a>

<?php else: ?>

<span class="btn btn-outline-secondary me-2 disabled">

Next Industry

</span>

<?php endif; ?>


<a
href="edit.php?id=<?php echo $industry['id']; ?>"
class="btn btn-primary me-2">

Edit Industry

</a>

<a
href="upload_photo.php?id=<?php echo $industry['id']; ?>"
class="btn btn-success me-2">

Upload Photo

</a>

<?php if (!empty($industry['photo_filename'])): ?>

<a
href="../photo_editor/edit.php?type=industry&id=<?php echo $industry['id']; ?>"
class="btn btn-warning me-2">

Edit Photo

</a>

<?php endif; ?>

<a
href="add.php"
class="btn btn-info me-2">

Add Another

</a>

<a
href="delete.php?id=<?php echo $industry['id']; ?>"
class="btn btn-danger me-2">

Delete Industry

</a>

<a
href="list.php"
class="btn btn-secondary">

Back to List

</a>

</div>

</div>

<?php include '../includes/footer.php'; ?>