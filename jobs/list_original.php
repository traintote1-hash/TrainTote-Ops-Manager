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

$search = trim($_GET['search'] ?? '');

if ($railroad) {

    if ($search !== '') {

        $stmt = $pdo->prepare("
            SELECT
                j.*,
                i.industry_name AS home_location
            FROM jobs j
            LEFT JOIN industries i
                ON j.home_industry_id = i.id
            WHERE j.railroad_id = :railroad_id
            AND (
                j.job_name LIKE :search
                OR j.job_type LIKE :search
            )
            ORDER BY j.job_name
        ");

        $stmt->execute([
            'railroad_id' => $railroad['id'],
            'search' => '%' . $search . '%'
        ]);

    } else {

        $stmt = $pdo->prepare("
            SELECT
                j.*,
                i.industry_name AS home_location
            FROM jobs j
            LEFT JOIN industries i
                ON j.home_industry_id = i.id
            WHERE j.railroad_id = :railroad_id
            ORDER BY j.job_name
        ");

        $stmt->execute([
            'railroad_id' => $railroad['id']
        ]);
    }

    $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

?>

<?php include '../includes/header.php'; ?>

<title>Jobs</title>

</head>

<body>

<?php include '../includes/navbar.php'; ?>

<div class="container mt-5">

<h1>Jobs</h1>

<p>

<a
href="add.php"
class="btn btn-primary">

Add Job

</a>

</p>

<div class="card mb-4">

<div class="card-body">

<form method="get">

<div class="row g-2">

<div class="col-md-8">

<input
type="text"
name="search"
class="form-control"
placeholder="Search job name or type..."
value="<?php echo htmlspecialchars($search); ?>">

</div>

<div class="col-md-4">

<button
type="submit"
class="btn btn-primary me-2">

Search

</button>

<a
href="list.php"
class="btn btn-secondary">

Clear

</a>

</div>

</div>

</form>

</div>

</div>

<?php if ($search !== ''): ?>

<div class="alert alert-info">

Showing results for:

<strong>

<?php echo htmlspecialchars($search); ?>

</strong>

</div>

<?php endif; ?>

<?php if (count($jobs) == 0): ?>

<div class="alert alert-warning">

No jobs found.

</div>

<?php else: ?>

<div class="table-responsive">

<table class="table table-striped align-middle">

<thead>

<tr>

<th>Job Name</th>

<th>Type</th>

<th>Home Location</th>

<th>Status</th>

<th></th>

</tr>

</thead>

<tbody>
<?php foreach($jobs as $job): ?>

<tr
class="clickable-row"
data-href="view.php?id=<?php echo $job['id']; ?>">

<td>

<strong>

<a
href="view.php?id=<?php echo $job['id']; ?>"
style="text-decoration:none;">

<?php echo htmlspecialchars($job['job_name']); ?>

</a>

</strong>

</td>

<td>

<?php

echo htmlspecialchars(

    $job['job_type'] === 'custom'

        ? ($job['custom_job_type'] ?: 'Custom')

        : ucfirst($job['job_type'])

);

?>

</td>

<td>

<?php

echo htmlspecialchars(

    $job['home_location'] ?? '-'

);

?>

</td>

<td>

<?php if ($job['active']): ?>

<span class="badge bg-success">

Active

</span>

<?php else: ?>

<span class="badge bg-secondary">

Inactive

</span>

<?php endif; ?>

</td>

<td>

<a
href="edit.php?id=<?php echo $job['id']; ?>"
class="btn btn-sm btn-outline-primary me-1">

Edit

</a>

<a
href="delete.php?id=<?php echo $job['id']; ?>"
class="btn btn-sm btn-outline-danger">

Delete

</a>

</td>

</tr>

<?php endforeach; ?>
</tbody>

</table>

</div>

<?php endif; ?>

</div>



<?php include '../includes/footer.php'; ?>