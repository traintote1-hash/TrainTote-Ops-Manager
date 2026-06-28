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
    ");

    $stmt->execute([
        'railroad_id' => $railroad['id']
    ]);

    $allWaybills = $stmt->fetchAll(PDO::FETCH_ASSOC);

    shuffle($allWaybills);

    $sessionWaybills = array_slice(
        $allWaybills,
        0,
        min($carCount, count($allWaybills))
    );

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
            <p>Choose how much work to build, review the session workflow, and generate switch lists from existing waybills.</p>
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

            <p class="tt-session-panel-copy">Create a random switch list from existing waybills.</p>

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
        <strong>No waybills available.</strong>
        <span>Create some waybills first.</span>
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
                        <dt>Commodity</dt>
                        <dd><?php echo htmlspecialchars($waybill['commodity']); ?></dd>
                    </div>
                    <div>
                        <dt>Status</dt>
                        <dd><?php echo htmlspecialchars($waybill['status']); ?></dd>
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
