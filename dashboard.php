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
$activeCarsCount = 0;
$readyForSessionCount = 0;
$activeLocomotiveCount = 0;
$missingOperationsServiceCount = 0;
$missingLocationCount = 0;

$recentEquipment = [];
$recentIndustries = [];
$recentWaybills = [];

$hasGeneratedSession = !empty($_SESSION['generated_session'] ?? []);
$printSwitchListHref = $hasGeneratedSession
    ? 'operations/print.php'
    : 'operations/generate.php';

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
}

?>

<?php include 'includes/header.php'; ?>

<title>TrainTote Ops Manager</title>
<link rel="stylesheet" href="assets/css/dashboard.css">

</head>

<body>

<?php include 'includes/navbar.php'; ?>

<div class="container mt-5 tt-dashboard-page tt-command-center-page">

    <section class="tt-hero tt-command-hero">
        <div class="tt-hero-main">
            <div>
                <span class="tt-hero-kicker">Operations Command Center</span>
                <h1>TrainTote Ops Manager</h1>
                <p>Check railroad readiness, clean up setup issues, and launch your next operating session.</p>
            </div>
        </div>

        <div class="tt-hero-actions">
            <a href="operations/generate.php" class="btn btn-success">Start Operating Session</a>
            <a href="equipment/status.php" class="btn btn-light">Car Status Board</a>
        </div>
    </section>

    <section class="tt-command-section">
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

    <?php if ($missingOperationsServiceCount > 0 || $missingLocationCount > 0 || $activeLocomotiveCount === 0): ?>
    <section class="tt-panel tt-setup-warnings">
        <div class="tt-panel-heading">
            <div>
                <span class="tt-panel-kicker">Setup Warnings</span>
                <h3>Before You Start</h3>
            </div>
            <span class="tt-status-pill tt-status-ready">Action Needed</span>
        </div>

        <div class="tt-warning-list">
            <?php if ($missingOperationsServiceCount > 0): ?>
            <a href="equipment/status.php?filter=missing_service">
                <strong><?php echo $missingOperationsServiceCount; ?> active cars need Operations Service</strong>
                <span>Open Car Status Board</span>
            </a>
            <?php endif; ?>

            <?php if ($missingLocationCount > 0): ?>
            <a href="equipment/status.php?filter=missing_location">
                <strong><?php echo $missingLocationCount; ?> active cars need a current location</strong>
                <span>Open Car Status Board</span>
            </a>
            <?php endif; ?>

            <?php if ($activeLocomotiveCount === 0): ?>
            <a href="equipment/status.php">
                <strong>No active locomotive assigned</strong>
                <span>Open Car Status Board</span>
            </a>
            <?php endif; ?>
        </div>
    </section>
    <?php endif; ?>

    <section class="tt-command-section">
        <div class="tt-section-header">
            <div>
                <span class="tt-panel-kicker">Operations Workflow</span>
                <h2>Run Today's Session</h2>
            </div>
        </div>

        <div class="tt-workflow-grid">
            <a href="equipment/status.php" class="tt-workflow-card">
                <span>1</span>
                <strong>Check Car Status</strong>
                <small>Set active cars, locations, loads, and services.</small>
            </a>

            <a href="industries/list.php" class="tt-workflow-card">
                <span>2</span>
                <strong>Review Industries</strong>
                <small>Confirm what each location loads and unloads.</small>
            </a>

            <a href="operations/generate.php" class="tt-workflow-card tt-workflow-primary">
                <span>3</span>
                <strong>Start Operating Session</strong>
                <small>Build local switcher work from active cars.</small>
            </a>

            <a href="<?php echo htmlspecialchars($printSwitchListHref); ?>" class="tt-workflow-card">
                <span>4</span>
                <strong>Print Switch List</strong>
                <small><?php echo $hasGeneratedSession ? 'Open the latest generated work order.' : 'Generate a session before printing.'; ?></small>
            </a>
        </div>
    </section>

    <section class="tt-admin-summary-grid">
        <div class="tt-panel tt-admin-summary-card">
            <span class="tt-panel-kicker">Database Summary</span>
            <h3>Equipment</h3>
            <strong><?php echo $equipmentCount; ?></strong>
            <a href="equipment/list.php">Open Equipment</a>
        </div>

        <div class="tt-panel tt-admin-summary-card">
            <span class="tt-panel-kicker">Database Summary</span>
            <h3>Industries</h3>
            <strong><?php echo $industryCount; ?></strong>
            <a href="industries/list.php">Open Industries</a>
        </div>

        <div class="tt-panel tt-admin-summary-card">
            <span class="tt-panel-kicker">Database Summary</span>
            <h3>Waybills</h3>
            <strong><?php echo $waybillCount; ?></strong>
            <a href="waybills/list.php">Open Waybills</a>
        </div>
    </section>

    <section class="tt-recent-grid">
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
    </section>
</div>

<?php include 'includes/footer.php'; ?>
