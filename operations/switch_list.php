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

$pickupGroups = [];

foreach ($pickups as $car) {
    $location = $car['origin_name'] ?: 'Unknown';
    $pickupGroups[$location][] = $car;
}

$setoutGroups = [];

foreach ($setouts as $car) {
    $destination = $car['destination_name'] ?: 'Unknown';
    $setoutGroups[$destination][] = $car;
}

?>

<?php
$pageTitle = 'Switch List';
include '../assets/components/header.php';
include '../assets/components/sidebar.php';
?>
<link rel="stylesheet" href="../assets/css/dashboard.css">

<div class="tt-switch-list-page">
    <div class="tt-session-hero tt-switch-hero">
        <div>
            <span class="tt-session-kicker">Job Switch List</span>
            <h1><?php echo htmlspecialchars($job['job_name']); ?></h1>
            <p>
                <?php echo htmlspecialchars($job['job_type'] ?: 'Operating job'); ?>
                <?php if (!empty($job['home_location'])): ?>
                based at <?php echo htmlspecialchars($job['home_location']); ?>
                <?php endif; ?>
            </p>
        </div>

        <div class="tt-switch-hero-actions no-print">
            <button onclick="window.print();" class="btn btn-light">Print</button>
            <a href="select_job.php" class="btn btn-outline-light">Back to Jobs</a>
        </div>
    </div>

    <div class="tt-switch-summary-grid">
        <div>
            <span>Assigned Locomotives</span>
            <strong><?php echo count($locomotives); ?></strong>
        </div>
        <div>
            <span>Assigned Industries</span>
            <strong><?php echo count($industries); ?></strong>
        </div>
        <div>
            <span>Pick Up</span>
            <strong><?php echo count($pickups); ?></strong>
        </div>
        <div>
            <span>Set Out</span>
            <strong><?php echo count($setouts); ?></strong>
        </div>
    </div>

    <div class="tt-switch-two-column">
        <section class="tt-panel tt-switch-section">
            <div class="tt-panel-heading">
                <div>
                    <span class="tt-panel-kicker">Power</span>
                    <h2>Assigned Locomotives</h2>
                </div>
            </div>

            <?php if (count($locomotives) == 0): ?>
            <p class="tt-muted-text">No locomotives assigned.</p>
            <?php else: ?>
            <ul class="tt-switch-pill-list">
                <?php foreach ($locomotives as $loco): ?>
                <li>
                    <?php
                    echo htmlspecialchars(
                        trim(
                            ($loco['road_name'] ?? '')
                            . ' '
                            . ($loco['road_number'] ?? '')
                        )
                    );
                    ?>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </section>

        <section class="tt-panel tt-switch-section">
            <div class="tt-panel-heading">
                <div>
                    <span class="tt-panel-kicker">Route</span>
                    <h2>Assigned Industries</h2>
                </div>
            </div>

            <?php if (count($industries) == 0): ?>
            <p class="tt-muted-text">No industries assigned.</p>
            <?php else: ?>
            <ol class="tt-switch-route-list">
                <?php foreach ($industries as $industry): ?>
                <li><?php echo htmlspecialchars($industry['industry_name']); ?></li>
                <?php endforeach; ?>
            </ol>
            <?php endif; ?>
        </section>
    </div>

    <section class="tt-panel tt-switch-section">
        <div class="tt-panel-heading">
            <div>
                <span class="tt-panel-kicker">Work</span>
                <h2>Pick Up</h2>
            </div>
        </div>

        <?php if (count($pickupGroups) == 0): ?>
        <p class="tt-muted-text">No pickups.</p>
        <?php else: ?>
        <?php foreach ($pickupGroups as $location => $group): ?>
        <div class="tt-switch-work-group">
            <h3><?php echo htmlspecialchars($location); ?></h3>
            <div class="table-responsive">
                <table class="table table-sm align-middle tt-switch-table">
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
                            <td><strong><?php echo htmlspecialchars(trim($car['reporting_marks'] . ' ' . $car['road_number'])); ?></strong></td>
                            <td><?php echo htmlspecialchars($car['current_track'] ?: '-'); ?></td>
                            <td><?php echo htmlspecialchars($car['destination_name'] ?: '-'); ?></td>
                            <td><?php echo htmlspecialchars($car['commodity'] ?: ''); ?></td>
                            <td><?php echo htmlspecialchars($car['current_cycle']); ?> / <?php echo htmlspecialchars($car['cycle_count']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </section>

    <section class="tt-panel tt-switch-section">
        <div class="tt-panel-heading">
            <div>
                <span class="tt-panel-kicker">Work</span>
                <h2>Set Out</h2>
            </div>
        </div>

        <?php if (count($setoutGroups) == 0): ?>
        <p class="tt-muted-text">No set outs.</p>
        <?php else: ?>
        <?php foreach ($setoutGroups as $destination => $group): ?>
        <div class="tt-switch-work-group">
            <h3><?php echo htmlspecialchars($destination); ?></h3>
            <div class="table-responsive">
                <table class="table table-sm align-middle tt-switch-table">
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
                            <td><strong><?php echo htmlspecialchars(trim($car['reporting_marks'] . ' ' . $car['road_number'])); ?></strong></td>
                            <td><?php echo htmlspecialchars($car['origin_name'] ?: 'Unknown'); ?></td>
                            <td><?php echo htmlspecialchars($car['commodity'] ?: ''); ?></td>
                            <td><?php echo htmlspecialchars($car['waybill_status'] ?: ''); ?></td>
                            <td><?php echo htmlspecialchars($car['current_cycle']); ?> / <?php echo htmlspecialchars($car['cycle_count']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </section>

    <section class="tt-panel tt-switch-section">
        <div class="tt-panel-heading">
            <div>
                <span class="tt-panel-kicker">Notes</span>
                <h2>Switching Notes</h2>
            </div>
        </div>
        <p class="tt-muted-text">Future home for pull and spot instructions.</p>
    </section>

    <section class="tt-panel tt-switch-section">
        <div class="tt-panel-heading">
            <div>
                <span class="tt-panel-kicker">Train Makeup</span>
                <h2>Locomotive Consist</h2>
            </div>
        </div>
        <p class="tt-muted-text">Future home for MU consists and train makeup.</p>
    </section>

    <section class="tt-panel tt-switch-section tt-complete-job-panel no-print">
        <div>
            <span class="tt-panel-kicker">Finish</span>
            <h2>Complete Job</h2>
            <p class="tt-muted-text">Move cars and advance waybills.</p>
        </div>

        <a href="complete_job.php?job_id=<?php echo $jobId; ?>" class="btn btn-success">Complete Job</a>
    </section>

    <div class="tt-switch-footer-actions no-print">
        <button onclick="window.print();" class="btn btn-dark">Print Switch List</button>
        <a href="select_job.php" class="btn btn-secondary">Back to Jobs</a>
    </div>
</div>

<?php include '../assets/components/footer.php'; ?>
