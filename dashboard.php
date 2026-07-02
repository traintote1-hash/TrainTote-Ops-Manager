<?php

session_start();

require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
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

$equipmentCount = 0;
$industryCount = 0;
$waybillCount = 0;
$jobCount = 0;
$activeCarsCount = 0;
$readyForSessionCount = 0;
$activeLocomotiveCount = 0;
$missingOperationsServiceCount = 0;
$missingLocationCount = 0;

$recentEquipment = [];
$recentIndustries = [];
$recentWaybills = [];
$recentJobs = [];

if ($railroad) {

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM equipment
        WHERE railroad_id = :railroad_id
    ");

    $stmt->execute([
        'railroad_id' => $railroad['id']
    ]);

    $equipmentCount = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM industries
        WHERE railroad_id = :railroad_id
    ");

    $stmt->execute([
        'railroad_id' => $railroad['id']
    ]);

    $industryCount = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM waybills
        WHERE railroad_id = :railroad_id
    ");

    $stmt->execute([
        'railroad_id' => $railroad['id']
    ]);

    $waybillCount = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM jobs
        WHERE railroad_id = :railroad_id
    ");

    $stmt->execute([
        'railroad_id' => $railroad['id']
    ]);

    $jobCount = (int)$stmt->fetchColumn();

    $nonLocomotiveCondition = "
        (
            equipment_class IS NULL
            OR equipment_class = ''
            OR equipment_class <> 'Locomotive'
        )
    ";

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM equipment
        WHERE railroad_id = :railroad_id
            AND active = 1
            AND $nonLocomotiveCondition
    ");

    $stmt->execute([
        'railroad_id' => $railroad['id']
    ]);

    $activeCarsCount = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM equipment
        WHERE railroad_id = :railroad_id
            AND active = 1
            AND $nonLocomotiveCondition
            AND current_industry_id IS NOT NULL
            AND current_industry_id <> 0
            AND operations_service IS NOT NULL
            AND TRIM(operations_service) <> ''
    ");

    $stmt->execute([
        'railroad_id' => $railroad['id']
    ]);

    $readyForSessionCount = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM equipment
        WHERE railroad_id = :railroad_id
            AND active = 1
            AND equipment_class = 'Locomotive'
    ");

    $stmt->execute([
        'railroad_id' => $railroad['id']
    ]);

    $activeLocomotiveCount = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM equipment
        WHERE railroad_id = :railroad_id
            AND active = 1
            AND $nonLocomotiveCondition
            AND (
                operations_service IS NULL
                OR TRIM(operations_service) = ''
            )
    ");

    $stmt->execute([
        'railroad_id' => $railroad['id']
    ]);

    $missingOperationsServiceCount = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM equipment
        WHERE railroad_id = :railroad_id
            AND active = 1
            AND $nonLocomotiveCondition
            AND (
                current_industry_id IS NULL
                OR current_industry_id = 0
            )
    ");

    $stmt->execute([
        'railroad_id' => $railroad['id']
    ]);

    $missingLocationCount = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT *
        FROM equipment
        WHERE railroad_id = :railroad_id
        ORDER BY id DESC
        LIMIT 5
    ");

    $stmt->execute([
        'railroad_id' => $railroad['id']
    ]);

    $recentEquipment = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("
        SELECT *
        FROM industries
        WHERE railroad_id = :railroad_id
        ORDER BY id DESC
        LIMIT 5
    ");

    $stmt->execute([
        'railroad_id' => $railroad['id']
    ]);

    $recentIndustries = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("
        SELECT
            w.*,
            e.reporting_marks,
            e.road_number,
            oi.industry_name AS origin_name,
            di.industry_name AS destination_name
        FROM waybills w
        JOIN equipment e
            ON w.equipment_id = e.id
        JOIN industries oi
            ON w.origin_industry_id = oi.id
        JOIN industries di
            ON w.destination_industry_id = di.id
        WHERE w.railroad_id = :railroad_id
        ORDER BY w.id DESC
        LIMIT 5
    ");

    $stmt->execute([
        'railroad_id' => $railroad['id']
    ]);

    $recentWaybills = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("
        SELECT
            j.*,
            i.industry_name AS home_location
        FROM jobs j
        LEFT JOIN industries i
            ON j.home_industry_id = i.id
        WHERE j.railroad_id = :railroad_id
        ORDER BY j.id DESC
        LIMIT 5
    ");

    $stmt->execute([
        'railroad_id' => $railroad['id']
    ]);

    $recentJobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

?>

<?php include 'includes/header.php'; ?>

<title>TrainTote Ops Manager</title>
<link rel="stylesheet" href="assets/css/dashboard.css">

</head>

<body>

<?php include 'includes/navbar.php'; ?>

<div class="container mt-5 tt-dashboard-page tt-dashboard-home-page">

    <section class="tt-hero tt-dashboard-home-hero">
        <div class="tt-hero-main">
            <div>
                <span class="tt-hero-kicker">Dashboard</span>
                <h1>TrainTote Ops Manager</h1>
                <p>Manage your railroad, prepare your equipment, and launch operations.</p>
            </div>
        </div>
    </section>

    <section class="tt-dashboard-column-grid" aria-label="Railroad management dashboard">
        <div class="tt-dashboard-lane">
            <article class="tt-panel tt-dashboard-summary-card">
                <span class="tt-panel-kicker">Database Summary</span>
                <h2>Equipment</h2>
                <strong><?php echo $equipmentCount; ?></strong>
                <a href="equipment/list.php">Open Equipment</a>
            </article>

            <article class="tt-panel tt-recent-panel">
                <div class="tt-panel-heading">
                    <div>
                        <span class="tt-panel-kicker">Recent</span>
                        <h3>Recent Equipment</h3>
                    </div>
                </div>

                <?php if (count($recentEquipment) == 0): ?>
                <p class="tt-muted-text">No equipment added yet.</p>
                <?php endif; ?>

                <?php foreach ($recentEquipment as $item): ?>
                <div class="tt-recent-item">
                    <?php if (!empty($item['photo_filename'])): ?>
                    <a href="equipment/view.php?id=<?php echo (int)$item['id']; ?>">
                        <img
                        src="uploads/<?php echo htmlspecialchars($item['photo_filename']); ?>"
                        alt=""
                        class="tt-recent-thumb">
                    </a>
                    <?php endif; ?>

                    <a href="equipment/view.php?id=<?php echo (int)$item['id']; ?>">
                        <strong><?php echo htmlspecialchars(trim($item['reporting_marks'] . ' ' . $item['road_number'])); ?></strong>
                        <span><?php echo htmlspecialchars($item['equipment_type'] ?? ''); ?></span>
                    </a>
                </div>
                <?php endforeach; ?>
            </article>
        </div>

        <div class="tt-dashboard-lane">
            <article class="tt-panel tt-dashboard-summary-card">
                <span class="tt-panel-kicker">Database Summary</span>
                <h2>Industries</h2>
                <strong><?php echo $industryCount; ?></strong>
                <a href="industries/list.php">Open Industries</a>
            </article>

            <article class="tt-panel tt-recent-panel">
                <div class="tt-panel-heading">
                    <div>
                        <span class="tt-panel-kicker">Recent</span>
                        <h3>Recent Industries</h3>
                    </div>
                </div>

                <?php if (count($recentIndustries) == 0): ?>
                <p class="tt-muted-text">No industries added yet.</p>
                <?php endif; ?>

                <?php foreach ($recentIndustries as $industry): ?>
                <div class="tt-recent-item">
                    <?php if (!empty($industry['photo_filename'])): ?>
                    <a href="industries/view.php?id=<?php echo (int)$industry['id']; ?>">
                        <img
                        src="uploads/<?php echo htmlspecialchars($industry['photo_filename']); ?>"
                        alt=""
                        class="tt-recent-thumb">
                    </a>
                    <?php endif; ?>

                    <a href="industries/view.php?id=<?php echo (int)$industry['id']; ?>">
                        <strong><?php echo htmlspecialchars($industry['industry_name']); ?></strong>
                        <span><?php echo htmlspecialchars($industry['industry_type'] ?? ''); ?></span>
                    </a>
                </div>
                <?php endforeach; ?>
            </article>
        </div>

        <div class="tt-dashboard-lane">
            <article class="tt-panel tt-dashboard-summary-card">
                <span class="tt-panel-kicker">Database Summary</span>
                <h2>Waybills</h2>
                <strong><?php echo $waybillCount; ?></strong>
                <a href="waybills/list.php">Open Waybills</a>
            </article>

            <article class="tt-panel tt-recent-panel">
                <div class="tt-panel-heading">
                    <div>
                        <span class="tt-panel-kicker">Recent</span>
                        <h3>Recent Waybills</h3>
                    </div>
                </div>

                <?php if (count($recentWaybills) == 0): ?>
                <p class="tt-muted-text">No waybills created yet.</p>
                <?php endif; ?>

                <?php foreach ($recentWaybills as $waybill): ?>
                <div class="tt-recent-waybill">
                    <a href="waybills/view.php?id=<?php echo (int)$waybill['id']; ?>">
                        <strong><?php echo htmlspecialchars(trim($waybill['reporting_marks'] . ' ' . $waybill['road_number'])); ?></strong>
                    </a>
                    <span><?php echo htmlspecialchars($waybill['origin_name']); ?> to <?php echo htmlspecialchars($waybill['destination_name']); ?></span>
                </div>
                <?php endforeach; ?>
            </article>
        </div>

        <div class="tt-dashboard-lane">
            <article class="tt-panel tt-dashboard-summary-card">
                <span class="tt-panel-kicker">Database Summary</span>
                <h2>Jobs</h2>
                <strong><?php echo $jobCount; ?></strong>
                <a href="jobs/list.php">Open Jobs</a>
            </article>

            <article class="tt-panel tt-recent-panel">
                <div class="tt-panel-heading">
                    <div>
                        <span class="tt-panel-kicker">Recent</span>
                        <h3>Recent Jobs</h3>
                    </div>
                </div>

                <?php if (count($recentJobs) == 0): ?>
                <p class="tt-muted-text">No jobs created yet.</p>
                <?php endif; ?>

                <?php foreach ($recentJobs as $job): ?>
                <?php
                    $jobName = trim($job['job_name'] ?? '');
                    if ($jobName === '') {
                        $jobName = 'Job #' . (int)$job['id'];
                    }

                    $jobDetails = array_filter([
                        trim($job['job_type'] ?? ''),
                        trim($job['home_location'] ?? '')
                    ]);
                ?>
                <div class="tt-recent-waybill">
                    <a href="jobs/view.php?id=<?php echo (int)$job['id']; ?>">
                        <strong><?php echo htmlspecialchars($jobName); ?></strong>
                    </a>
                    <?php if (!empty($jobDetails)): ?>
                    <span><?php echo htmlspecialchars(implode(' at ', $jobDetails)); ?></span>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </article>
        </div>
    </section>

    <section class="tt-operations-button-strip" aria-label="Operations shortcuts">
        <a href="operations/generate.php" class="btn btn-success">Start Operating Session</a>
        <a href="equipment/status.php" class="btn btn-outline-secondary">Car Status Board</a>
    </section>

    <section class="tt-readiness-section">
        <div class="tt-section-header">
            <div>
                <span class="tt-panel-kicker">Railroad Readiness</span>
                <h2>Ready to Operate</h2>
            </div>
        </div>

        <div class="tt-readiness-grid">
            <a href="equipment/status.php?filter=active" class="tt-readiness-card">
                <span>Active Cars / On Layout</span>
                <strong><?php echo $activeCarsCount; ?></strong>
                <small>Cars available for sessions</small>
            </a>

            <a href="equipment/status.php?filter=ready" class="tt-readiness-card tt-readiness-good">
                <span>Ready for Session</span>
                <strong><?php echo $readyForSessionCount; ?></strong>
                <small>Active cars with location and service</small>
            </a>

            <a href="equipment/status.php" class="tt-readiness-card">
                <span>Active Locomotives</span>
                <strong><?php echo $activeLocomotiveCount; ?></strong>
                <small>Power available for assignments</small>
            </a>

            <a href="industries/list.php" class="tt-readiness-card">
                <span>Industries / Locations</span>
                <strong><?php echo $industryCount; ?></strong>
                <small>Places to work on the railroad</small>
            </a>

            <a href="equipment/status.php?filter=missing_service" class="tt-readiness-card <?php if ($missingOperationsServiceCount > 0) echo 'tt-readiness-warning'; ?>">
                <span>Cars Missing Operations Service</span>
                <strong><?php echo $missingOperationsServiceCount; ?></strong>
                <small>Needed for service matching</small>
            </a>

            <a href="equipment/status.php?filter=missing_location" class="tt-readiness-card <?php if ($missingLocationCount > 0) echo 'tt-readiness-warning'; ?>">
                <span>Cars Missing Location</span>
                <strong><?php echo $missingLocationCount; ?></strong>
                <small>Needed before cars can be switched</small>
            </a>
        </div>
    </section>
</div>

<?php include 'includes/footer.php'; ?>
