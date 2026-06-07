<?php

session_start();

require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name = trim($_POST['name']);
    $era = trim($_POST['era']);
    $region = trim($_POST['region']);
    $operating_style = trim($_POST['operating_style']);
    $description = trim($_POST['description']);

    if (!empty($name)) {

        $stmt = $pdo->prepare("
            INSERT INTO railroads
            (
                user_id,
                name,
                era,
                region,
                operating_style,
                description
            )
            VALUES
            (
                :user_id,
                :name,
                :era,
                :region,
                :operating_style,
                :description
            )
        ");

        $stmt->execute([
            'user_id' => $_SESSION['user_id'],
            'name' => $name,
            'era' => $era,
            'region' => $region,
            'operating_style' => $operating_style,
            'description' => $description
        ]);

        header('Location: ../dashboard.php');
        exit;
    }

    $message = 'Please enter a railroad name.';
}

?>

<!DOCTYPE html>
<html>
<head>

<title>Create Railroad</title>

<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">

</head>

<body>

<?php include '../includes/navbar.php'; ?>

<div class="container mt-5">

<h1>TrainTote Ops Manager</h1>

<h3>Create Your Railroad</h3>

<?php if($message): ?>

<div class="alert alert-danger">

<?php echo $message; ?>

</div>

<?php endif; ?>

<form method="post">

<div class="mb-3">
<label>Railroad Name</label>
<input
type="text"
name="name"
class="form-control"
required>
</div>

<div class="mb-3">
<label>Era</label>
<select
name="era"
class="form-select">
<option>Modern</option>
<option>Diesel</option>
<option>Transition</option>
<option>Steam</option>
</select>
</div>

<div class="mb-3">
<label>Region</label>
<input
type="text"
name="region"
class="form-control"
placeholder="Midwest">
</div>

<div class="mb-3">
<label>Operating Style</label>
<select
name="operating_style"
class="form-select">
<option>Hybrid</option>
<option>Car Cards & Waybills</option>
<option>Switch Lists</option>
<option>Mobile</option>
</select>
</div>

<div class="mb-3">
<label>Description</label>
<textarea
name="description"
class="form-control"
rows="4"></textarea>
</div>

<button
type="submit"
class="btn btn-primary">

Create Railroad

</button>

</form>

</div>

</body>
</html>