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

/*
|--------------------------------------------------------------------------
| Railroad
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT id
    FROM railroads
    WHERE user_id = ?
    LIMIT 1
");

$stmt->execute([
    $_SESSION['user_id']
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
    SELECT

        j.*,

        i.industry_name AS home_location

    FROM jobs j

    LEFT JOIN industries i
        ON j.home_industry_id = i.id

    WHERE

        j.id = ?

        AND

        j.railroad_id = ?

    LIMIT 1
");

$stmt->execute([
    $jobId,
    $railroad['id']
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

        e.*

    FROM job_locomotives jl

    JOIN equipment e
        ON jl.equipment_id = e.id

    WHERE

        jl.job_id = ?

    ORDER BY

        jl.position
");

$stmt->execute([
    $jobId
]);

$locomotives = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

    WHERE

        ji.job_id = ?

    ORDER BY

        ji.sequence_number
");

$stmt->execute([
    $jobId
]);

$industries = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Cars From job_cars
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("

    SELECT

        jc.*,

        e.reporting_marks,
        e.road_number,
        e.current_track,

        w.commodity,
        w.status AS waybill_status,
        w.current_cycle,
        w.cycle_count,

        origin.industry_name AS origin_name,
        destination.industry_name AS destination_name,

        currentloc.industry_name AS current_location

    FROM job_cars jc

    JOIN equipment e
        ON jc.equipment_id = e.id

    JOIN waybills w
        ON jc.waybill_id = w.id

    LEFT JOIN industries origin
        ON jc.from_industry_id = origin.id

    LEFT JOIN industries destination
        ON jc.to_industry_id = destination.id

    LEFT JOIN industries currentloc
        ON e.current_industry_id = currentloc.id

    WHERE

        jc.job_id = ?

    ORDER BY

        jc.sequence_num

");

$stmt->execute([
    $jobId
]);

$cars = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Pickup Cars
|--------------------------------------------------------------------------
*/

$pickups = array_filter(

    $cars,

    function ($car) {

        return $car['move_type'] === 'pickup';

    }

);

/*
|--------------------------------------------------------------------------
| Setout Cars
|--------------------------------------------------------------------------
*/

$setouts = array_filter(

    $cars,

    function ($car) {

        return $car['move_type'] === 'setout';

    }

);
?>

<?php include '../includes/header.php'; ?>

<title>Switch List</title>

</head>

<body>

<?php include '../includes/navbar.php'; ?>

<div class="container mt-4">

<div class="d-flex justify-content-between align-items-center mb-4">

<h1>

<?php echo htmlspecialchars($job['job_name']); ?>

Switch List

</h1>

<div>

<button
onclick="window.print();"
class="btn btn-dark me-2">

Print

</button>

<a
href="select_job.php"
class="btn btn-secondary">

Back

</a>

</div>

</div>

<div class="card mb-4">

<div class="card-body">

<h4>

Assigned Locomotives

</h4>

<?php if (count($locomotives) == 0): ?>

<p class="text-muted">

No locomotives assigned.

</p>

<?php else: ?>

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

<?php endif; ?>

</div>

</div>

<div class="card mb-4">

<div class="card-body">

<h4>

Assigned Industries

</h4>

<?php if (count($industries) == 0): ?>

<p class="text-muted">

No industries assigned.

</p>

<?php else: ?>

<ol>

<?php foreach ($industries as $industry): ?>

<li>

<?php echo htmlspecialchars($industry['industry_name']); ?>

</li>

<?php endforeach; ?>

</ol>

<?php endif; ?>

</div>

</div>

<!-- PICK UP -->

<div class="card mb-4">

<div class="card-body">

<h3>

Pick Up

</h3>

<?php

$pickupGroups = [];

foreach ($pickups as $car) {

    $location = $car['origin_name'] ?: 'Unknown';

    $pickupGroups[$location][] = $car;

}

?>

<?php if (count($pickupGroups) == 0): ?>

<p class="text-muted">

No pickups.

</p>

<?php else: ?>

<?php foreach ($pickupGroups as $location => $group): ?>

<h5 class="mt-4">

<?php echo htmlspecialchars($location); ?>

</h5>

<table class="table table-striped align-middle">

<thead>

<tr>

<th>Car</th>
<th>Track</th>
<th>Destination</th>
<th>Commodity</th>
<th>Cycle</th>

</tr>

</thead>

<tbody>

<?php foreach ($group as $car): ?>

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

<?php echo htmlspecialchars($car['current_track'] ?: '-'); ?>

</td>

<td>

<?php echo htmlspecialchars($car['destination_name'] ?: '-'); ?>

</td>

<td>

<?php echo htmlspecialchars($car['commodity'] ?: ''); ?>

</td>

<td>

<?php echo htmlspecialchars($car['current_cycle']); ?>

/

<?php echo htmlspecialchars($car['cycle_count']); ?>

</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

<?php endforeach; ?>

<?php endif; ?>

</div>

</div>

<!-- SET OUT -->

<div class="card mb-4">

<div class="card-body">

<h3>

Set Out

</h3>

<?php

$setoutGroups = [];

foreach ($setouts as $car) {

    $destination = $car['destination_name'] ?: 'Unknown';

    $setoutGroups[$destination][] = $car;

}

?>

<?php if (count($setoutGroups) == 0): ?>

<p class="text-muted">

No set outs.

</p>

<?php else: ?>

<?php foreach ($setoutGroups as $destination => $group): ?>

<h5 class="mt-4">

<?php echo htmlspecialchars($destination); ?>

</h5>

<table class="table table-striped align-middle">

<thead>

<tr>

<th>Car</th>
<th>Origin</th>
<th>Commodity</th>
<th>Status</th>
<th>Cycle</th>

</tr>

</thead>

<tbody>

<?php foreach ($group as $car): ?>

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

<?php echo htmlspecialchars($car['origin_name'] ?: 'Unknown'); ?>

</td>

<td>

<?php echo htmlspecialchars($car['commodity'] ?: ''); ?>

</td>

<td>

<?php echo htmlspecialchars($car['waybill_status'] ?: ''); ?>

</td>

<td>

<?php echo htmlspecialchars($car['current_cycle']); ?>

/

<?php echo htmlspecialchars($car['cycle_count']); ?>

</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

<?php endforeach; ?>

<?php endif; ?>

</div>

</div>

<!-- SWITCHING NOTES -->

<div class="card mb-4">

<div class="card-body">

<h3>

Switching Notes

</h3>

<p class="text-muted">

Future home for pull and spot instructions.

</p>

</div>

</div>

<!-- LOCOMOTIVE CONSIST -->

<div class="card mb-4">

<div class="card-body">

<h3>

Locomotive Consist

</h3>

<p class="text-muted">

Future home for MU consists and train makeup.

</p>

</div>

</div>

<!-- COMPLETE JOB -->

<div class="card mb-4">

<div class="card-body">

<h3>

Complete Job

</h3>

<p class="text-muted">

Move cars and advance waybills.

</p>

<a

href="complete_job.php?job_id=<?php echo $jobId; ?>"

class="btn btn-success">

Complete Job

</a>

</div>

</div>

<div class="mb-5">

<button

onclick="window.print();"

class="btn btn-dark me-2">

Print Switch List

</button>

<a

href="select_job.php"

class="btn btn-secondary">

Back to Jobs

</a>

</div>

</div>

<?php include '../includes/footer.php'; ?>