<?php

session_start();

require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$stmt = $pdo->prepare("
    SELECT *
    FROM railroads
    WHERE user_id = :user_id
    LIMIT 1
");

$stmt->execute([
    'user_id' => $_SESSION['user_id']
]);

$railroad = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$railroad) {
    die('No railroad found.');
}

/*
|--------------------------------------------------------------------------
| Industries
|--------------------------------------------------------------------------
*/

$industryStmt = $pdo->prepare("
    SELECT
        id,
        industry_name
    FROM industries
    WHERE railroad_id = :railroad_id
    ORDER BY industry_name
");

$industryStmt->execute([
    'railroad_id' => $railroad['id']
]);

$industries = $industryStmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Locomotives
|--------------------------------------------------------------------------
*/

$locoStmt = $pdo->prepare("
    SELECT
        id,
        reporting_marks,
        road_number,
        road_name
    FROM equipment
    WHERE railroad_id = :railroad_id
    AND equipment_class = 'Locomotive'
    ORDER BY road_name, road_number
");

$locoStmt->execute([
    'railroad_id' => $railroad['id']
]);

$locomotives = $locoStmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $job_name = trim($_POST['job_name']);

    $job_type = trim($_POST['job_type']);

    $custom_job_type =
        trim($_POST['custom_job_type'] ?? '');

    $description =
        trim($_POST['description'] ?? '');

    $home_industry_id =
        !empty($_POST['home_industry_id'])
        ? (int)$_POST['home_industry_id']
        : null;

    $active =
        ($_POST['active'] ?? '1') == '1'
        ? 1
        : 0;

    $stmt = $pdo->prepare("
        INSERT INTO jobs
        (
            railroad_id,
            job_name,
            job_type,
            custom_job_type,
            home_industry_id,
            description,
            active
        )
        VALUES
        (
            :railroad_id,
            :job_name,
            :job_type,
            :custom_job_type,
            :home_industry_id,
            :description,
            :active
        )
    ");

    $stmt->execute([

        'railroad_id' => $railroad['id'],
        'job_name' => $job_name,
        'job_type' => $job_type,
        'custom_job_type' => $custom_job_type,
        'home_industry_id' => $home_industry_id,
        'description' => $description,
        'active' => $active

    ]);

    $jobId = $pdo->lastInsertId();
/*
|--------------------------------------------------------------------------
| Assigned Locomotives
|--------------------------------------------------------------------------
*/

if (!empty($_POST['locomotives'])) {

    $position = 1;

    foreach ($_POST['locomotives'] as $equipmentId) {

        $equipmentId = (int)$equipmentId;

        if ($equipmentId <= 0) {
            continue;
        }

        $stmt = $pdo->prepare("
            INSERT INTO job_locomotives
            (
                job_id,
                equipment_id,
                position
            )
            VALUES
            (
                :job_id,
                :equipment_id,
                :position
            )
        ");

        $stmt->execute([

            'job_id' => $jobId,
            'equipment_id' => $equipmentId,
            'position' => $position

        ]);

        $position++;
    }
}

header("Location: list.php");

exit;

}

?>

<?php include '../includes/header.php'; ?>

<title>Add Job</title>

</head>

<body>

<?php include '../includes/navbar.php'; ?>

<div class="container mt-5">

<h1>Add Job</h1>

<form method="post">
<div class="mb-3">

<label>Job Name</label>

<input
type="text"
name="job_name"
class="form-control"
required>

</div>

<div class="mb-3">

<label>Job Type</label>

<select
name="job_type"
id="job_type"
class="form-select">

<option value="system">System</option>

<option value="yard">Yard</option>

<option value="local">Local</option>

<option value="manifest">Manifest</option>

<option value="passenger">Passenger</option>

<option value="route">Route</option>

<option value="custom">Custom</option>

</select>

</div>

<div
class="mb-3"
id="customJobTypeRow"
style="display:none;">

<label>Custom Job Type</label>

<input
type="text"
name="custom_job_type"
class="form-control"
placeholder="Mine Run, Coal Extra, Transfer Run, etc.">

</div>

<div class="mb-3">

<label>Home Location</label>

<select
name="home_industry_id"
class="form-select">

<option value="">
Select Home Location
</option>

<?php foreach ($industries as $industry): ?>

<option value="<?php echo $industry['id']; ?>">

<?php echo htmlspecialchars($industry['industry_name']); ?>

</option>

<?php endforeach; ?>

</select>

</div>

<div class="mb-3">

<label>Status</label>

<select
name="active"
class="form-select">

<option value="1" selected>
Active
</option>

<option value="0">
Inactive
</option>

</select>

</div>

<div class="mb-3">

<label>Assigned Locomotives</label>

<div id="locomotiveContainer">

<select
name="locomotives[]"
class="form-select mb-2">

<option value="">
Select Locomotive
</option>

<?php foreach ($locomotives as $loco): ?>

<option value="<?php echo $loco['id']; ?>">

<?php

echo htmlspecialchars(
    trim(
        $loco['road_name'] .
        ' ' .
        $loco['road_number']
    )
);

?>

</option>

<?php endforeach; ?>

</select>

</div>

<button
type="button"
class="btn btn-outline-secondary btn-sm"
onclick="addLocomotive();">

Add Another Locomotive

</button>

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
class="btn btn-success me-2">

Save Job

</button>

<a
href="list.php"
class="btn btn-secondary">

Cancel

</a>

</form>

</div>

<script>

document
.getElementById('job_type')
.addEventListener('change', function() {

    const customRow =
        document.getElementById('customJobTypeRow');

    if (this.value === 'custom') {

        customRow.style.display = 'block';

    } else {

        customRow.style.display = 'none';

    }

});

function addLocomotive() {

    const container =
        document.getElementById(
            'locomotiveContainer'
        );

    const firstSelect =
        container.querySelector('select');

    const newSelect =
        firstSelect.cloneNode(true);

    newSelect.selectedIndex = 0;

    container.appendChild(newSelect);
}

</script>

<?php include '../includes/footer.php'; ?>