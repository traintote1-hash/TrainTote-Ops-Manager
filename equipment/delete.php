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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!empty($equipment['photo_filename'])) {

        $photoPath =
            dirname(__DIR__) .
            '/uploads/' .
            $equipment['photo_filename'];

        if (file_exists($photoPath)) {
            unlink($photoPath);
        }
    }

    $stmt = $pdo->prepare("
        DELETE FROM equipment
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

<title>Delete Equipment</title>

</head>

<body>

<?php include '../includes/navbar.php'; ?>

<div class="container mt-5">

<div class="alert alert-danger">

<h3>Delete Equipment</h3>

<p>
Are you sure you want to delete:
</p>

<h4>

<?php
echo htmlspecialchars(
    $equipment['reporting_marks'] .
    ' ' .
    $equipment['road_number']
);
?>

</h4>

<p>
This action cannot be undone.
</p>

<form method="post">

<button
type="submit"
class="btn btn-danger me-2">

Delete Equipment

</button>

<a
href="view.php?id=<?php echo $equipment['id']; ?>"
class="btn btn-secondary">

Cancel

</a>

</form>

</div>

</div>

<?php include '../includes/footer.php'; ?>