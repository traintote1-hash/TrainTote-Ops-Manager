<?php

session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

include '../includes/header.php';

?>

<title>Operations</title>

</head>

<body>

<?php include '../includes/navbar.php'; ?>

<div class="container mt-5">

<h1 class="mb-4">Operations</h1>

<div class="row">

<div class="col-md-6 mb-4">

<div class="card h-100">

<div class="card-body">

<h4>Generate Operating Session</h4>

<p>

Generate an operating session using
waybills and railroad traffic.

</p>

<a
href="generate.php"
class="btn btn-primary">

Generate Operating Session

</a>

</div>

</div>

</div>

<div class="col-md-6 mb-4">

<div class="card h-100">

<div class="card-body">

<h4>Generate Switch List</h4>

<p>

Generate a switch list using jobs,
assigned industries, and assigned locomotives.

</p>

<a
href="select_job.php"
class="btn btn-success">

Generate Switch List

</a>

</div>

</div>

</div>

</div>

</div>

<?php include '../includes/footer.php'; ?>