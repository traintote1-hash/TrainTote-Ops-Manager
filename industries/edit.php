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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $industry_name = trim($_POST['industry_name']);
    $industry_type = trim($_POST['industry_type']);
    $location = trim($_POST['location']);
    $track_capacity = (int)$_POST['track_capacity'];
    $notes = trim($_POST['notes']);

    $stmt = $pdo->prepare("
        UPDATE industries
        SET
            industry_name = :industry_name,
            industry_type = :industry_type,
            location = :location,
            track_capacity = :track_capacity,
            notes = :notes
        WHERE id = :id
    ");

    $stmt->execute([
        'industry_name' => $industry_name,
        'industry_type' => $industry_type,
        'location' => $location,
        'track_capacity' => $track_capacity,
        'notes' => $notes,
        'id' => $id
    ]);

    header("Location: view.php?id=$id");
    exit;
}

?>

<?php include '../includes/header.php'; ?>

<title>Edit Industry</title>

</head>

<body>

<?php include '../includes/navbar.php'; ?>

<div class="container mt-5">

<h1>Edit Industry</h1>

<form method="post">

<div class="mb-3">
<label>Industry Name</label>
<input
type="text"
name="industry_name"
class="form-control"
value="<?php echo htmlspecialchars($industry['industry_name']); ?>"
required>
</div>

<div class="mb-3">
<label>Industry Type</label>
<input
type="text"
name="industry_type"
class="form-control"
value="<?php echo htmlspecialchars($industry['industry_type']); ?>">
</div>

<div class="mb-3">
<label>Location</label>
<input
type="text"
name="location"
class="form-control"
value="<?php echo htmlspecialchars($industry['location']); ?>">
</div>

<div class="mb-3">
<label>Track Capacity</label>
<input
type="number"
name="track_capacity"
class="form-control"
value="<?php echo htmlspecialchars($industry['track_capacity']); ?>">
</div>

<div class="mb-3">
<label>Notes</label>
<textarea
name="notes"
class="form-control"
rows="4"><?php echo htmlspecialchars($industry['notes']); ?></textarea>
</div>

<button
type="submit"
class="btn btn-success me-2">

Save Changes

</button>

<a
href="view.php?id=<?php echo $industry['id']; ?>"
class="btn btn-secondary">

Cancel

</a>

</form>

</div>

<?php include '../includes/footer.php'; ?>