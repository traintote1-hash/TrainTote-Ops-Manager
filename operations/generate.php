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

$sessionWaybills = [];

$difficulty = $_POST['difficulty'] ?? 'medium';
$carCount = (int)($_POST['car_count'] ?? 5);

if ($carCount < 1) {
    $carCount = 1;
}

if ($carCount > 50) {
    $carCount = 50;
}

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && $railroad
) {

    $stmt = $pdo->prepare("
        SELECT
            e.id AS equipment_id,
            e.reporting_marks,
            e.road_number,
            e.equipment_class,
            e.equipment_type,
            e.load_status,
            e.current_industry_id,
            e.current_track,
            i.industry_name AS origin_name
        FROM equipment e
        JOIN industries i
            ON e.current_industry_id = i.id
        WHERE e.railroad_id = :railroad_id
            AND e.current_industry_id IS NOT NULL
            AND e.current_industry_id <> 0
            AND (
                e.equipment_class IS NULL
                OR e.equipment_class = ''
                OR e.equipment_class <> 'Locomotive'
            )
        ORDER BY
            CASE
                WHEN e.equipment_class = 'Freight Car' THEN 0
                WHEN e.equipment_class IN ('Passenger Car', 'Caboose', 'MOW', 'Other') THEN 1
                ELSE 2
            END,
            e.reporting_marks,
            e.road_number
    ");

    $stmt->execute([
        'railroad_id' => $railroad['id']
    ]);

    $eligibleCars = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("
        SELECT
            id,
            industry_name
        FROM industries
        WHERE railroad_id = :railroad_id
        ORDER BY industry_name
    ");

    $stmt->execute([
        'railroad_id' => $railroad['id']
    ]);

    $industries = $stmt->fetchAll(PDO::FETCH_ASSOC);

    shuffle($eligibleCars);

    foreach ($eligibleCars as $car) {

        if (count($sessionWaybills) >= $carCount) {
            break;
        }

        $destinationOptions = array_values(array_filter(
            $industries,
            fn($industry) => (int)$industry['id'] !== (int)$car['current_industry_id']
        ));

        if (count($destinationOptions) === 0) {
            continue;
        }

        $destination = $destinationOptions[array_rand($destinationOptions)];

        $sessionWaybills[] = [
            'equipment_id' => $car['equipment_id'],
            'waybill_id' => null,
            'reporting_marks' => $car['reporting_marks'],
            'road_number' => $car['road_number'],
            'equipment_class' => $car['equipment_class'],
            'equipment_type' => $car['equipment_type'],
            'load_status' => $car['load_status'],
            'origin_industry_id' => $car['current_industry_id'],
            'destination_industry_id' => $destination['id'],
            'origin_name' => $car['origin_name'],
            'destination_name' => $destination['industry_name'],
            'origin_track' => $car['current_track'],
            'current_track' => $car['current_track'],
            'destination_track' => '',
            'commodity' => $car['load_status'] ?: ($car['equipment_type'] ?: ''),
            'status' => $car['load_status'] ?: 'Ready'
        ];
    }

    $_SESSION['generated_session'] = $sessionWaybills;
    $_SESSION['generated_difficulty'] = $difficulty;
    $_SESSION['generated_car_count'] = $carCount;
}

?>

<?php
$pageTitle='Start Session';
include '../assets/components/header.php';
include '../assets/components/sidebar.php';
?>
<link rel="stylesheet" href="../assets/css/dashboard.css">

<div class="tt-session-start-page">
    <div class="tt-session-hero">
        <div>
            <span class="tt-session-kicker">Operations Mission Control</span>
            <h1>Start Operating Session</h1>
            <p>Choose how much work to build, review the session workflow, and generate switch lists from cars currently placed on your railroad.</p>
        </div>

        <div class="tt-session-hero-actions">
            <a class="tt-session-link" href="/operations/select_job.php">Available Jobs</a>
            <a class="tt-session-link" href="/operations/switch_list.php">Switch Lists</a>
        </div>
    </div>

    <div class="tt-session-workflow" aria-label="Start session workflow">
        <div class="tt-session-step is-current">
            <span>1</span>
            <strong>Session Options</strong>
        </div>
        <div class="tt-session-step">
            <span>2</span>
            <strong>Available Jobs</strong>
        </div>
        <div class="tt-session-step">
            <span>3</span>
            <strong>Crew Assignment</strong>
        </div>
        <div class="tt-session-step">
            <span>4</span>
            <strong>Generate Switch Lists</strong>
        </div>
    </div>

    <div class="tt-session-grid">
        <section class="tt-panel tt-session-primary-panel">
            <div class="tt-panel-heading">
                <div>
                    <span class="tt-panel-kicker">Session Options</span>
                    <h2>Build Operating Work</h2>
                </div>
                <span class="tt-status-pill tt-status-ready">Ready</span>
            </div>

            <p class="tt-session-panel-copy">Create an operating session from cars currently placed on your railroad.</p>

            <form method="post" class="tt-session-options-form">
                <div class="tt-session-fieldset">
                    <label class="form-label">Difficulty</label>

                    <div class="tt-session-radio-grid">
                        <label class="tt-session-radio-card">
                            <input
                            class="form-check-input"
                            type="radio"
                            name="difficulty"
                            value="easy"
                            <?php if ($difficulty === 'easy') echo 'checked'; ?>>
                            <span>
                                <strong>Easy</strong>
                                <small>Shorter, simpler work</small>
                            </span>
                        </label>

                        <label class="tt-session-radio-card">
                            <input
                            class="form-check-input"
                            type="radio"
                            name="difficulty"
                            value="medium"
                            <?php if ($difficulty === 'medium') echo 'checked'; ?>>
                            <span>
                                <strong>Medium</strong>
                                <small>Balanced session plan</small>
                            </span>
                        </label>

                        <label class="tt-session-radio-card">
                            <input
                            class="form-check-input"
                            type="radio"
                            name="difficulty"
                            value="hard"
                            <?php if ($difficulty === 'hard') echo 'checked'; ?>>
                            <span>
                                <strong>Hard</strong>
                                <small>More demanding work</small>
                            </span>
                        </label>
                    </div>
                </div>

                <div class="tt-session-fieldset">
                    <label class="form-label" for="tt-session-car-count">Cars To Switch</label>
                    <input
                    id="tt-session-car-count"
                    type="number"
                    name="car_count"
                    class="form-control tt-session-number-input"
                    value="<?php echo $carCount; ?>"
                    min="1"
                    max="50">
                </div>

                <button
                type="submit"
                class="btn btn-success tt-session-start-button">
                    Generate Session
                </button>
            </form>
        </section>

        <aside class="tt-session-side-stack" aria-label="Session planning areas">
            <section class="tt-panel tt-session-side-panel">
                <div class="tt-panel-heading">
                    <div>
                        <span class="tt-panel-kicker">Available Jobs</span>
                        <h3>Job-Based Work</h3>
                    </div>
                </div>
                <p class="tt-muted-text">Use saved jobs when you want a specific operating assignment instead of a random session.</p>
                <a class="tt-session-secondary-action" href="/operations/select_job.php">Select Job</a>
            </section>

            <section class="tt-panel tt-session-side-panel">
                <div class="tt-panel-heading">
                    <div>
                        <span class="tt-panel-kicker">Crew Assignment</span>
                        <h3>Assign Crew</h3>
                    </div>
                </div>
                <p class="tt-muted-text">Crew assignment controls will live here when that workflow is ready.</p>
                <div class="tt-session-placeholder-row">
                    <span>Engineer</span>
                    <strong>Not assigned</strong>
                </div>
                <div class="tt-session-placeholder-row">
                    <span>Conductor</span>
                    <strong>Not assigned</strong>
                </div>
            </section>
        </aside>
    </div>

    <?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>

    <?php if (count($sessionWaybills) == 0): ?>

    <div class="alert alert-warning tt-session-alert">
        <strong>No cars with current locations available.</strong>
        <span>Place cars at industries before generating an operating session.</span>
    </div>

    <?php else: ?>

    <section class="tt-panel tt-generated-session-panel">
        <div class="tt-panel-heading">
            <div>
                <span class="tt-panel-kicker">Generated Switch List</span>
                <h2>Generated Session</h2>
            </div>
        </div>

        <div class="tt-generated-summary">
            <div>
                <span>Difficulty</span>
                <strong><?php echo ucfirst($difficulty); ?></strong>
            </div>
            <div>
                <span>Cars Requested</span>
                <strong><?php echo $carCount; ?></strong>
            </div>
        </div>

        <div class="tt-generated-moves">
            <?php foreach ($sessionWaybills as $index => $waybill): ?>

            <article class="tt-generated-move">
                <div class="tt-generated-move-header">
                    <span>Move <?php echo $index + 1; ?></span>
                    <strong>
                        <?php
                        echo htmlspecialchars(
                            $waybill['reporting_marks']
                            . ' '
                            . $waybill['road_number']
                        );
                        ?>
                    </strong>
                </div>

                <dl>
                    <div>
                        <dt>Origin</dt>
                        <dd><?php echo htmlspecialchars($waybill['origin_name']); ?></dd>
                    </div>
                    <div>
                        <dt>Destination</dt>
                        <dd><?php echo htmlspecialchars($waybill['destination_name']); ?></dd>
                    </div>
                    <div>
                        <dt>Car Type</dt>
                        <dd><?php echo htmlspecialchars($waybill['equipment_type'] ?: '-'); ?></dd>
                    </div>
                    <div>
                        <dt>Load Status</dt>
                        <dd><?php echo htmlspecialchars($waybill['load_status'] ?: '-'); ?></dd>
                    </div>
                    <div>
                        <dt>Current Track</dt>
                        <dd><?php echo htmlspecialchars($waybill['current_track'] ?: '-'); ?></dd>
                    </div>
                    <div>
                        <dt>Destination Track</dt>
                        <dd><?php echo htmlspecialchars($waybill['destination_track'] ?: '-'); ?></dd>
                    </div>
                </dl>
            </article>

            <?php endforeach; ?>
        </div>

        <form method="post" class="tt-generated-actions">
            <input
            type="hidden"
            name="difficulty"
            value="<?php echo htmlspecialchars($difficulty); ?>">

            <input
            type="hidden"
            name="car_count"
            value="<?php echo $carCount; ?>">

            <button
            type="submit"
            class="btn btn-success">
                Generate Again
            </button>

            <a
            href="print.php"
            target="_blank"
            class="btn btn-primary">
                Print Switch List
            </a>
        </form>
    </section>

    <?php endif; ?>

    <?php endif; ?>
</div>

<?php include '../assets/components/footer.php'; ?>
