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

    if (!empty($industry['photo_filename'])) {

        $photoPath =
            dirname(__DIR__) .
            '/uploads/' .
            $industry['photo_filename'];

        if (file_exists($photoPath)) {
            unlink($photoPath);
        }
    }

    $stmt = $pdo->prepare("
        DELETE FROM industries
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

<title>Delete Industry</title>

</head>

<body>

<?php include '../includes/navbar.php'; ?>

<div class="container mt-5">

<div class="alert alert-danger">

<h3>Delete Industry</h3>

<p>

Are you sure you want to delete:

</p>

<h4>

<?php echo htmlspecialchars($industry['industry_name']); ?>

</h4>

<p>

This action cannot be undone.

</p>

<form method="post">

<button
type="submit"
class="btn btn-danger me-2">

Delete Industry

</button>

<a
href="view.php?id=<?php echo $industry['id']; ?>"
class="btn btn-secondary">

Cancel

</a>

</form>

</div>

</div>

<?php include '../includes/footer.php'; ?>