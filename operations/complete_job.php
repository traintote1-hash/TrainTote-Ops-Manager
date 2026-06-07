<?php

session_start();

require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {

    header('Location: ../login.php');

    exit;

}

if (!isset($_GET['job_id'])) {

    die('Job ID missing.');

}

$jobId = (int)$_GET['job_id'];

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

if (!$railroad) {

    die('Railroad not found.');

}

/*
|--------------------------------------------------------------------------
| Job
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT *
    FROM jobs
    WHERE id = :id
    AND railroad_id = :railroad_id
    LIMIT 1
");

$stmt->execute([

    'id' => $jobId,

    'railroad_id' => $railroad['id']

]);

$job = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$job) {

    die('Job not found.');

}

/*
|--------------------------------------------------------------------------
| Assigned Industries
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT

        i.*

    FROM job_industries ji

    JOIN industries i
        ON ji.industry_id = i.id

    WHERE ji.job_id = :job_id

    ORDER BY ji.sequence_number
");

$stmt->execute([

    'job_id' => $jobId

]);

$industries = $stmt->fetchAll(PDO::FETCH_ASSOC);

$industryIds = [];

foreach ($industries as $industry) {

    $industryIds[] = $industry['id'];

}

/*
|--------------------------------------------------------------------------
| Cars To Move
|--------------------------------------------------------------------------
*/

$cars = [];

if (!empty($industryIds)) {

    $placeholders = implode(

        ',',

        array_fill(

            0,

            count($industryIds),

            '?'

        )

    );

    $stmt = $pdo->prepare("

        SELECT

            e.id AS equipment_id,

            e.reporting_marks,

            e.road_number,

            w.id AS waybill_id,

            w.commodity,

            currentloc.industry_name AS current_location,

            destination.industry_name AS destination_name

        FROM waybills w

        JOIN equipment e
            ON w.equipment_id = e.id

        LEFT JOIN industries currentloc
            ON e.current_industry_id = currentloc.id

        LEFT JOIN industries destination
            ON w.destination_industry_id = destination.id

        WHERE

            w.active = 1

            AND (

                w.origin_industry_id IN ($placeholders)

                OR

                w.destination_industry_id IN ($placeholders)

            )

        ORDER BY

            currentloc.industry_name,

            e.reporting_marks,

            e.road_number

    ");

    $params = array_merge(

        $industryIds,

        $industryIds

    );

    $stmt->execute($params);

    $cars = $stmt->fetchAll(PDO::FETCH_ASSOC);

}

?>

<?php include '../includes/header.php'; ?>

<title>Complete Job</title>

</head>

<body>

<?php include '../includes/navbar.php'; ?>

<div class="container mt-4">

<h1>

Complete Job

</h1>

<h4>

<?php echo htmlspecialchars($job['job_name']); ?>

</h4>

<div class="card mt-4">

<div class="card-body">

<h3>

Cars To Move

</h3>
<?php if (count($cars) == 0): ?>

<p class="text-muted">

No active waybills found.

</p>

<?php else: ?>

<table class="table table-striped align-middle">

<thead>

<tr>

<th>Car</th>

<th>Current Location</th>

<th>Destination</th>

<th>Commodity</th>

</tr>

</thead>

<tbody>

<?php foreach ($cars as $car): ?>

<tr>

<td>

<strong>

<?php

echo htmlspecialchars(

    trim(

        $car['reporting_marks']

        . ' '

        . $car['road_number']

    )

);

?>

</strong>

</td>

<td>

<?php

echo htmlspecialchars(

    $car['current_location']

    ?: 'Unknown'

);

?>

</td>

<td>

<?php

echo htmlspecialchars(

    $car['destination_name']

    ?: '-'

);

?>

</td>

<td>

<?php

echo htmlspecialchars(

    $car['commodity']

    ?: ''

);

?>

</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

<?php endif; ?>

</div>

</div>
<form
method="post"
action="process_job.php">

<input
type="hidden"
name="job_id"
value="<?php echo $jobId; ?>">

<div class="mt-4">

<button
type="submit"
class="btn btn-success me-2">

Confirm Complete Job

</button>

<a
href="switch_list.php?job_id=<?php echo $jobId; ?>"
class="btn btn-secondary">

Cancel

</a>

</div>

</form>
<?php include '../includes/footer.php'; ?>