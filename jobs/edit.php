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

$stmt = $pdo->prepare("
    SELECT *
    FROM jobs
    WHERE id = :id
    AND railroad_id = :railroad_id
    LIMIT 1
");

$stmt->execute([
    'id' => $id,
    'railroad_id' => $railroad['id']
]);

$job = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$job) {
    die('Job not found.');
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

/*
|--------------------------------------------------------------------------
| Assigned Locomotives
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT equipment_id
    FROM job_locomotives
    WHERE job_id = :job_id
    ORDER BY position
");

$stmt->execute([
    'job_id' => $id
]);

$assignedLocomotives =
    $stmt->fetchAll(PDO::FETCH_COLUMN);

if (count($assignedLocomotives) == 0) {
    $assignedLocomotives = [''];
}

/*
|--------------------------------------------------------------------------
| Assigned Industries
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT industry_id
    FROM job_industries
    WHERE job_id = :job_id
    ORDER BY sequence_number
");

$stmt->execute([
    'job_id' => $id
]);

$assignedIndustries =
    $stmt->fetchAll(PDO::FETCH_COLUMN);

if (count($assignedIndustries) == 0) {
    $assignedIndustries = [''];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $job_name =
        trim($_POST['job_name']);

    $job_type =
        trim($_POST['job_type']);

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
        UPDATE jobs
        SET
            job_name = :job_name,
            job_type = :job_type,
            custom_job_type = :custom_job_type,
            home_industry_id = :home_industry_id,
            description = :description,
            active = :active
        WHERE id = :id
    ");

    $stmt->execute([

        'job_name' => $job_name,

        'job_type' => $job_type,

        'custom_job_type' => $custom_job_type,

        'home_industry_id' => $home_industry_id,

        'description' => $description,

        'active' => $active,

        'id' => $id

    ]);
	    /*
    |--------------------------------------------------------------------------
    | Replace Locomotive Assignments
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        DELETE FROM job_locomotives
        WHERE job_id = :job_id
    ");

    $stmt->execute([
        'job_id' => $id
    ]);

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

                'job_id' => $id,

                'equipment_id' => $equipmentId,

                'position' => $position

            ]);

            $position++;

        }
    }

    /*
    |--------------------------------------------------------------------------
    | Replace Industry Assignments
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        DELETE FROM job_industries
        WHERE job_id = :job_id
    ");

    $stmt->execute([
        'job_id' => $id
    ]);

    if (!empty($_POST['industries'])) {

        $sequence = 1;

        foreach ($_POST['industries'] as $industryId) {

            $industryId = (int)$industryId;

            if ($industryId <= 0) {
                continue;
            }

            $stmt = $pdo->prepare("
                INSERT INTO job_industries
                (
                    job_id,
                    industry_id,
                    sequence_number
                )
                VALUES
                (
                    :job_id,
                    :industry_id,
                    :sequence_number
                )
            ");

            $stmt->execute([

                'job_id' => $id,

                'industry_id' => $industryId,

                'sequence_number' => $sequence

            ]);

            $sequence++;

        }

    }

    header('Location: list.php');

    exit;

}

?>

<?php include '../includes/header.php'; ?>

<title>Edit Job</title>

</head>

<body>

<?php include '../includes/navbar.php'; ?>

<div class="container mt-5">

<h1>Edit Job</h1>

<form method="post">

<div class="mb-3">

<label>Job Name</label>

<input
type="text"
name="job_name"
class="form-control"
value="<?php echo htmlspecialchars($job['job_name']); ?>"
required>

</div>

<div class="mb-3">

<label>Job Type</label>

<select
name="job_type"
id="job_type"
class="form-select">

<option value="system" <?php if($job['job_type']=='system') echo 'selected'; ?>>System</option>

<option value="yard" <?php if($job['job_type']=='yard') echo 'selected'; ?>>Yard</option>

<option value="local" <?php if($job['job_type']=='local') echo 'selected'; ?>>Local</option>

<option value="manifest" <?php if($job['job_type']=='manifest') echo 'selected'; ?>>Manifest</option>

<option value="passenger" <?php if($job['job_type']=='passenger') echo 'selected'; ?>>Passenger</option>

<option value="route" <?php if($job['job_type']=='route') echo 'selected'; ?>>Route</option>

<option value="custom" <?php if($job['job_type']=='custom') echo 'selected'; ?>>Custom</option>

</select>

</div>

<div
class="mb-3"
id="customJobTypeRow"
style="<?php echo ($job['job_type']=='custom') ? '' : 'display:none;'; ?>">

<label>Custom Job Type</label>

<input
type="text"
name="custom_job_type"
class="form-control"
value="<?php echo htmlspecialchars($job['custom_job_type']); ?>">

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

<option
value="<?php echo $industry['id']; ?>"
<?php if ($job['home_industry_id'] == $industry['id']) echo 'selected'; ?>>

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

<option
value="1"
<?php if ($job['active']) echo 'selected'; ?>>

Active

</option>

<option
value="0"
<?php if (!$job['active']) echo 'selected'; ?>>

Inactive

</option>

</select>

</div>

<div class="mb-3">

<label>Assigned Locomotives</label>

<div id="locomotiveContainer">

<?php foreach ($assignedLocomotives as $assignedId): ?>

<select
name="locomotives[]"
class="form-select mb-2">

<option value="">

Select Locomotive

</option>

<?php foreach ($locomotives as $loco): ?>

<option
value="<?php echo $loco['id']; ?>"
<?php if ($assignedId == $loco['id']) echo 'selected'; ?>>

<?php

echo htmlspecialchars(

    trim(

        $loco['road_name']

        . ' '

        . $loco['road_number']

    )

);

?>

</option>

<?php endforeach; ?>

</select>

<?php endforeach; ?>

</div>

<button
type="button"
class="btn btn-outline-secondary btn-sm"
onclick="addLocomotive();">

Add Another Locomotive

</button>

</div>

<hr>

<div class="mb-3">

<label>Assigned Industries</label>

<div id="industryContainer">

<?php foreach ($assignedIndustries as $assignedIndustry): ?>

<select
name="industries[]"
class="form-select mb-2">

<option value="">

Select Industry

</option>

<?php foreach ($industries as $industry): ?>

<option
value="<?php echo $industry['id']; ?>"
<?php if ($assignedIndustry == $industry['id']) echo 'selected'; ?>>

<?php echo htmlspecialchars($industry['industry_name']); ?>

</option>

<?php endforeach; ?>

</select>

<?php endforeach; ?>

</div>

<button
type="button"
class="btn btn-outline-secondary btn-sm"
onclick="addIndustry();">

Add Another Industry

</button>

</div>

<hr>

<div class="mb-3">

<label>Description</label>

<textarea
name="description"
class="form-control"
rows="4"><?php echo htmlspecialchars($job['description']); ?></textarea>

</div>

<button
type="submit"
class="btn btn-success me-2">

Save Changes

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
        document.getElementById(
            'customJobTypeRow'
        );

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

function addIndustry() {

    const container =
        document.getElementById(
            'industryContainer'
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