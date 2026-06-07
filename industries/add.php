<?php

session_start();

require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$stmt = $pdo->prepare("
    SELECT *
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $industry_name = trim($_POST['industry_name']);
    $industry_type = trim($_POST['industry_type']);
    $location = trim($_POST['location']);
    $track_capacity = (int)$_POST['track_capacity'];
    $notes = trim($_POST['notes']);

    $stmt = $pdo->prepare("
        INSERT INTO industries
        (
            railroad_id,
            industry_name,
            industry_type,
            location,
            track_capacity,
            notes
        )
        VALUES
        (
            :railroad_id,
            :industry_name,
            :industry_type,
            :location,
            :track_capacity,
            :notes
        )
    ");

    $stmt->execute([
        'railroad_id' => $railroad['id'],
        'industry_name' => $industry_name,
        'industry_type' => $industry_type,
        'location' => $location,
        'track_capacity' => $track_capacity,
        'notes' => $notes
    ]);

    $industryId = $pdo->lastInsertId();

    header("Location: saved.php?id=$industryId");
    exit;
}

?>

<?php include '../includes/header.php'; ?>

<title>Add Industry</title>

</head>

<body>

<?php include '../includes/navbar.php'; ?>

<div class="container mt-5">

<h1>Add Industry</h1>

<form method="post">

<div class="mb-3">
<label>Industry Name</label>
<input
type="text"
name="industry_name"
class="form-control"
required>
</div>

<div class="mb-3">
<label>Industry Type</label>
<input
type="text"
name="industry_type"
class="form-control"
placeholder="Lumber, Team Track, Fuel Dealer, Intermodal, etc.">
</div>

<div class="mb-3">
<label>Location</label>
<input
type="text"
name="location"
class="form-control">
</div>

<div class="mb-3">
<label>Track Capacity (Cars)</label>
<input
type="number"
name="track_capacity"
class="form-control"
value="0">
</div>

<div class="mb-3">
<label>Notes</label>
<textarea
name="notes"
class="form-control"
rows="4"></textarea>
</div>

<button
type="submit"
class="btn btn-success me-2">

Save Industry

</button>

<a
href="list.php"
class="btn btn-secondary">

Cancel

</a>

</form>

</div>

<?php include '../includes/footer.php'; ?>