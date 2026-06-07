<?php

session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

if (!isset($_GET['id'])) {
    die('Industry ID missing.');
}

$id = (int)$_GET['id'];

?>

<?php include '../includes/header.php'; ?>

<title>Industry Saved</title>

</head>

<body>

<?php include '../includes/navbar.php'; ?>

<div class="container mt-5">

<div class="card">

<div class="card-body text-center">

<h2 class="text-success">

✓ Industry Saved Successfully

</h2>

<p class="text-muted">

What would you like to do next?

</p>

<div class="mt-4">

<a
href="view.php?id=<?php echo $id; ?>"
class="btn btn-primary me-2">

View Industry

</a>

<a
href="add.php"
class="btn btn-info me-2">

Add Another

</a>

<a
href="list.php"
class="btn btn-secondary">

Industry List

</a>

</div>

</div>

</div>

</div>

<?php include '../includes/footer.php'; ?>