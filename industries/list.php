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

$industries = [];

$search = trim($_GET['search'] ?? '');

if ($railroad) {

    if ($search !== '') {

        $stmt = $pdo->prepare("
            SELECT *
            FROM industries
            WHERE railroad_id = :railroad_id
            AND (
                industry_name LIKE :search
                OR industry_type LIKE :search
                OR location LIKE :search
            )
            ORDER BY industry_name
        ");

        $stmt->execute([
            'railroad_id' => $railroad['id'],
            'search' => '%' . $search . '%'
        ]);

    } else {

        $stmt = $pdo->prepare("
            SELECT *
            FROM industries
            WHERE railroad_id = :railroad_id
            ORDER BY industry_name
        ");

        $stmt->execute([
            'railroad_id' => $railroad['id']
        ]);
    }

    $industries = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

?>

<?php include '../includes/header.php'; ?>

<title>Industries</title>

</head>

<body>

<?php include '../includes/navbar.php'; ?>

<div class="container mt-5">

<h1>Industries</h1>

<p>

<a
href="add.php"
class="btn btn-primary">

Add Industry

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
placeholder="Search industry name, type, location..."
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

<?php if (count($industries) == 0): ?>

<div class="alert alert-warning">

No matching industries found.

</div>

<?php else: ?>

<!-- DESKTOP TABLE -->

<div class="d-none d-md-block">

<div class="table-responsive">

<table class="table table-striped align-middle">

<thead>

<tr>

<th>Photo</th>

<th>Industry</th>

<th>Type</th>

<th>Location</th>

<th>Capacity</th>

<th></th>

</tr>

</thead>

<tbody>
<?php foreach($industries as $industry): ?>

<tr
    class="clickable-row"
    data-href="view.php?id=<?php echo $industry['id']; ?>">

<td width="120">

<?php if (!empty($industry['photo_filename'])): ?>

<a
href="view.php?id=<?php echo $industry['id']; ?>">

<img
src="../uploads/<?php echo htmlspecialchars($industry['photo_filename']); ?>?v=<?php echo filemtime(dirname(__DIR__) . '/uploads/' . $industry['photo_filename']); ?>"
class="img-thumbnail"
style="width:100px;height:60px;object-fit:contain;">

</a>

<?php else: ?>

<div
class="border rounded text-center bg-light"
style="width:100px;height:60px;line-height:60px;">

📷

</div>

<?php endif; ?>

</td>

<td>

<strong>

<a
href="view.php?id=<?php echo $industry['id']; ?>"
style="text-decoration:none;">

<?php echo htmlspecialchars($industry['industry_name']); ?>

</a>

</strong>

<br>

<small class="text-muted">

<?php echo htmlspecialchars($industry['industry_type']); ?>

</small>

</td>

<td>

<?php echo htmlspecialchars($industry['industry_type']); ?>

</td>

<td>

<?php echo htmlspecialchars($industry['location']); ?>

</td>

<td>

<?php echo htmlspecialchars($industry['track_capacity']); ?>

</td>

<td>

<a
href="edit.php?id=<?php echo $industry['id']; ?>"
class="btn btn-sm btn-outline-primary">

Edit

</a>

</td>

</tr>

<?php endforeach; ?>
</tbody>

</table>

</div>

</div>

<!-- MOBILE CARD VIEW -->

<div class="d-md-none">

<?php foreach($industries as $industry): ?>

<div class="card mb-3 shadow-sm">

<div class="card-body">

<div class="text-center mb-3">

<?php if (!empty($industry['photo_filename'])): ?>

<a href="view.php?id=<?php echo $industry['id']; ?>">

<img
src="../uploads/<?php echo htmlspecialchars($industry['photo_filename']); ?>"
class="img-fluid rounded border"
style="max-height:150px;">

</a>

<?php else: ?>

<div class="border rounded p-4 bg-light">

📷 No Photo

</div>

<?php endif; ?>

</div>

<h5>

<a
href="view.php?id=<?php echo $industry['id']; ?>"
style="text-decoration:none;">

<?php echo htmlspecialchars($industry['industry_name']); ?>

</a>

</h5>

<p class="text-muted">

<?php echo htmlspecialchars($industry['industry_type']); ?>

</p>

<p>

<strong>Location:</strong>

<?php echo htmlspecialchars($industry['location']); ?>

</p>

<p>

<strong>Track Capacity:</strong>

<?php echo htmlspecialchars($industry['track_capacity']); ?>

cars

</p>

<a
href="edit.php?id=<?php echo $industry['id']; ?>"
class="btn btn-primary w-100">

Edit Industry

</a>

</div>

</div>

<?php endforeach; ?>

</div>

<?php endif; ?>

</div>



<?php include '../includes/footer.php'; ?>