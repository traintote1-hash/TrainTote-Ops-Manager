<?php

session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

?>

<?php include '../includes/header.php'; ?>

<title>Add Equipment</title>

</head>

<body>

<?php include '../includes/navbar.php'; ?>

<div class="container mt-5">

<div class="text-center mb-5">

<h1>Add Equipment</h1>

<p class="lead text-muted">

Choose how you'd like to add equipment.

</p>

</div>

<div class="row g-4">

<div class="col-md-6">

<div class="card h-100 shadow-sm border-primary">

<div class="card-body text-center p-5">

<h2 class="mb-3">

🤖 AI Scanner

</h2>

<p class="mb-4">

Upload a photo and let AI identify and fill in the equipment details automatically.

</p>

<span class="badge bg-success mb-3">

Recommended

</span>

<br>

<a
href="../ai/scan_equipment.php"
class="btn btn-primary btn-lg">

Start AI Scan

</a>

</div>

</div>

</div>

<div class="col-md-6">

<div class="card h-100 shadow-sm">

<div class="card-body text-center p-5">

<h2 class="mb-3">

✏️ Manual Entry

</h2>

<p class="mb-4">

Create an equipment record by entering the details yourself.

</p>

<a
href="add.php"
class="btn btn-success btn-lg">

Manual Entry

</a>

</div>

</div>

</div>

</div>

<div class="text-center mt-4">

<a
href="list.php"
class="btn btn-secondary">

Back to Equipment

</a>

</div>

</div>

<?php include '../includes/footer.php'; ?>