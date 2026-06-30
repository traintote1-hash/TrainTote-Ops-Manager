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

        <div class="tt-home-hero-actions">
            <a href="operations/index.php" class="btn btn-outline-secondary">Open Operations</a>
        </div>
    </section>

    <section class="tt-primary-module-grid" aria-label="Railroad management modules">
        <article class="tt-panel tt-module-card">
            <span class="tt-panel-kicker">Railroad</span>
            <h2>Equipment</h2>
            <strong><?php echo $equipmentCount; ?></strong>
            <p>Cars, locomotives, and rolling stock.</p>
            <a href="equipment/list.php" class="tt-module-action">Open Equipment</a>
        </article>

        <article class="tt-panel tt-module-card">
            <span class="tt-panel-kicker">Railroad</span>
            <h2>Industries</h2>
            <strong><?php echo $industryCount; ?></strong>
            <p>Customers, yards, interchanges, and locations.</p>
            <a href="industries/list.php" class="tt-module-action">Open Industries</a>
        </article>

        <article class="tt-panel tt-module-card">
            <span class="tt-panel-kicker">Traffic</span>
            <h2>Waybills</h2>
            <strong><?php echo $waybillCount; ?></strong>
            <p>Car movement paperwork and traffic records.</p>
            <a href="waybills/list.php" class="tt-module-action">Open Waybills</a>
        </article>

        <article class="tt-panel tt-module-card">
            <span class="tt-panel-kicker">Operations</span>
            <h2>Jobs</h2>
            <strong><?php echo $jobCount; ?></strong>
            <p>Assigned work, routes, and operating jobs.</p>
            <a href="jobs/list.php" class="tt-module-action">Open Jobs</a>
        </article>
    </section>

    <section class="tt-dashboard-section tt-recent-section">
        <div class="tt-section-header">
            <div>
                <span class="tt-panel-kicker">Recent Activity</span>
                <h2>Latest Railroad Updates</h2>
            </div>
        </div>

        <div class="tt-recent-grid">
            <div class="tt-panel tt-recent-panel">
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
            </div>

            <div class="tt-panel tt-recent-panel">
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
            </div>

            <div class="tt-panel tt-recent-panel">
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
            </div>

            <div class="tt-panel tt-recent-panel">
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
            </div>
        </div>
    </section>

    <section class="tt-panel tt-operations-minimal-panel">
        <div>
            <span class="tt-panel-kicker">Operations</span>
            <h2>Operations</h2>
            <p>Run sessions, generate switch lists, and manage operating activity.</p>
        </div>

        <div class="tt-operations-minimal-actions">
            <a href="operations/index.php" class="btn btn-primary">Open Operations</a>
            <a href="equipment/status.php" class="btn btn-outline-secondary">Car Status Board</a>
        </div>
    </section>
</div>

<?php include 'includes/footer.php'; ?>
