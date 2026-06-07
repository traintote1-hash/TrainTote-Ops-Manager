<?php

session_start();

require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$stmt = $pdo->prepare("
    SELECT id
    FROM railroads
    WHERE user_id = :user_id
    LIMIT 1
");

$stmt->execute([
    'user_id' => $_SESSION['user_id']
]);

$railroad = $stmt->fetch(PDO::FETCH_ASSOC);

$jobs = [];

if ($railroad) {

    $stmt = $pdo->prepare("
        SELECT *
        FROM jobs
        WHERE railroad_id = :railroad_id
        AND active = 1
        ORDER BY job_name
    ");

    $stmt->execute([
        'railroad_id' => $railroad['id']
    ]);

    $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

?>

<?php include '../includes/header.php'; ?>

<title>Generate Switch List</title>

</head>

<body>

<?php include '../includes/navbar.php'; ?>

<div class="container mt-5">

<h1>Generate Switch List</h1>
<div class="card mt-4">

<div class="card-body">

<form
method="get"
action="switch_list.php">

<div class="mb-3">

<label class="form-label">

Job

</label>

<select
name="job_id"
class="form-select"
required>

<option value="">

Select Job

</option>

<?php foreach ($jobs as $job): ?>

<option value="<?php echo $job['id']; ?>">

<?php echo htmlspecialchars($job['job_name']); ?>

</option>

<?php endforeach; ?>

</select>

</div>

<button
type="submit"
class="btn btn-primary">

Generate Switch List

</button>

<a
href="/dashboard.php"
class="btn btn-secondary ms-2">

Cancel

</a>

</form>

</div>

</div>
<?php if (count($jobs) == 0): ?>

<div class="alert alert-warning mt-4">

No active jobs found.

<br><br>

<a
href="/jobs/add.php"
class="btn btn-warning btn-sm">

Create Your First Job

</a>

</div>

<?php endif; ?>

</div>

<?php include '../includes/footer.php'; ?>