<?php

session_start();

require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

if (!isset($_GET['id'])) {
    die('Job ID missing.');
}

$id = (int)$_GET['id'];

$stmt = $pdo->prepare("
    SELECT
        j.*,
        i.industry_name AS home_location
    FROM jobs j
    LEFT JOIN industries i
        ON j.home_industry_id = i.id
    JOIN railroads r
        ON j.railroad_id = r.id
    WHERE j.id = :id
    AND r.user_id = :user_id
    LIMIT 1
");

$stmt->execute([
    'id' => $id,
    'user_id' => $_SESSION['user_id']
]);

$job = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$job) {
    die('Job not found.');
}

/*
|--------------------------------------------------------------------------
| Assigned Locomotives
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        e.road_name,
        e.road_number
    FROM job_locomotives jl
    JOIN equipment e
        ON jl.equipment_id = e.id
    WHERE jl.job_id = :job_id
    ORDER BY jl.position
");

$stmt->execute([
    'job_id' => $id
]);

$locomotives = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Assigned Industries
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        i.industry_name
    FROM job_industries ji
    JOIN industries i
        ON ji.industry_id = i.id
    WHERE ji.job_id = :job_id
    ORDER BY ji.sequence_number
");

$stmt->execute([
    'job_id' => $id
]);

$assignedIndustries = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<?php include '../includes/header.php'; ?>

<title>View Job</title>

</head>

<body>

<?php include '../includes/navbar.php'; ?>

<div class="container mt-5">

<h1>

<?php echo htmlspecialchars($job['job_name']); ?>

</h1>

<div class="card">

<div class="card-body">

<div class="row mb-3">

<div class="col-md-3">

<strong>Job Type</strong>

</div>

<div class="col-md-9">

<?php

echo htmlspecialchars(

    $job['job_type'] === 'custom'

        ? ($job['custom_job_type'] ?: 'Custom')

        : ucfirst($job['job_type'])

);

?>

</div>

</div>

<div class="row mb-3">

<div class="col-md-3">

<strong>Home Location</strong>

</div>

<div class="col-md-9">

<?php echo htmlspecialchars($job['home_location'] ?? '-'); ?>

</div>

</div>

<div class="row mb-4">

<div class="col-md-3">

<strong>Status</strong>

</div>

<div class="col-md-9">
<?php if ($job['active']): ?>

<span class="badge bg-success">

Active

</span>

<?php else: ?>

<span class="badge bg-secondary">

Inactive

</span>

<?php endif; ?>

</div>

</div>

<hr>

<h4>

Assigned Locomotives

</h4>

<?php if (count($locomotives)): ?>

<ul>

<?php foreach ($locomotives as $loco): ?>

<li>

<?php

echo htmlspecialchars(

    trim(

        $loco['road_name']

        . ' '

        . $loco['road_number']

    )

);

?>

</li>

<?php endforeach; ?>

</ul>

<?php else: ?>

<p class="text-muted">

No locomotives assigned.

</p>

<?php endif; ?>

<hr>

<h4>

Assigned Industries

</h4>

<?php if (count($assignedIndustries)): ?>

<ol>

<?php foreach ($assignedIndustries as $industry): ?>

<li>

<?php echo htmlspecialchars($industry['industry_name']); ?>

</li>

<?php endforeach; ?>

</ol>

<?php else: ?>

<p class="text-muted">

No industries assigned.

</p>

<?php endif; ?>

<hr>

<h4>

Description

</h4>

<p>

<?php

echo nl2br(

    htmlspecialchars(

        $job['description']

    )

);

?>

</p>

<hr>

<div class="d-flex flex-wrap gap-2">

<a
href="../operations/switch_list.php?job_id=<?php echo $job['id']; ?>"
class="btn btn-dark">

Generate Switch List

</a>

<a
href="edit.php?id=<?php echo $job['id']; ?>"
class="btn btn-primary">

Edit Job

</a>

<a
href="delete.php?id=<?php echo $job['id']; ?>"
class="btn btn-danger">

Delete Job

</a>

<a
href="list.php"
class="btn btn-secondary">

Back to Jobs

</a>

</div>

</div>

</div>
</div>

</div>

<?php include '../includes/footer.php'; ?>