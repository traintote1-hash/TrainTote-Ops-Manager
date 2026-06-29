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
$skippedCarDiagnostics = [];
$skippedNoOperationsService = 0;
$skippedNoCompatibleDestination = 0;
$skippedNoOperatingBase = 0;
$skippedNoLocomotive = 0;
$skippedCarCount = 0;
$setoutMoveCount = 0;
$pullMoveCount = 0;

$difficulty = $_POST['difficulty'] ?? 'medium';
$carCount = (int)($_POST['car_count'] ?? 5);

if ($carCount < 1) {
    $carCount = 1;
}

if ($carCount > 50) {
    $carCount = 50;
}

function normalizeOperationsServiceValue($value): string
{
    return strtolower(trim(preg_replace('/\s+/', ' ', (string)$value)));
}

function parseOperationsServiceList($value): array
{
    $parts = preg_split('/[\r\n,]+/', (string)$value);
    $services = [];

    foreach ($parts as $part) {
        $service = normalizeOperationsServiceValue($part);

        if ($service !== '') {
            $services[] = $service;
        }
    }

    return array_values(array_unique($services));
}

function industrySupportsOperationsService(array $industry, string $serviceField, string $operationsService): bool
{
    $service = normalizeOperationsServiceValue($operationsService);

    if ($service === '') {
        return false;
    }

    return in_array(
        $service,
        parseOperationsServiceList($industry[$serviceField] ?? ''),
        true
    );
}

function industryLooksLikeOperatingBase(array $industry): bool
{
    $text = normalizeOperationsServiceValue(
        ($industry['industry_name'] ?? '')
        . ' '
        . ($industry['industry_type'] ?? '')
    );

    foreach (['yard', 'interchange', 'staging', 'classification'] as $keyword) {
        if (str_contains($text, $keyword)) {
            return true;
        }
    }

    return false;
}

function findIndustryById(array $industries, int $industryId): ?array
{
    foreach ($industries as $industry) {
        if ((int)$industry['id'] === $industryId) {
            return $industry;
        }
    }

    return null;
}

function buildLocomotiveLabel(array $locomotive): string
{
    return trim(
        ($locomotive['reporting_marks'] ?? '')
        . ' '
        . ($locomotive['road_number'] ?? '')
        . ' - '
        . ($locomotive['road_name'] ?? '')
        . ' '
        . ($locomotive['equipment_type'] ?? '')
    );
}

function buildSkippedCarDiagnostic(array $car, string $reason, string $lookingFor): array
{
    return [
        'reporting_marks' => $car['reporting_marks'] ?? '',
        'road_number' => $car['road_number'] ?? '',
        'equipment_type' => $car['equipment_type'] ?? '',
        'load_status' => $car['load_status'] ?? '',
        'operations_service' => $car['operations_service'] ?? '',
        'origin_name' => $car['origin_name'] ?? '',
        'current_track' => $car['current_track'] ?? '',
        'reason' => $reason,
        'looking_for' => $lookingFor
    ];
}

function buildGeneratedMove(array $car, array $destination, string $moveType, string $instruction): array
{
    return [
        'equipment_id' => $car['equipment_id'],
        'waybill_id' => null,
        'reporting_marks' => $car['reporting_marks'],
        'road_number' => $car['road_number'],
        'equipment_class' => $car['equipment_class'],
        'equipment_type' => $car['equipment_type'],
        'operations_service' => $car['operations_service'],
        'load_status' => $car['load_status'],
        'original_load_status' => $car['original_load_status'] ?? ($car['load_status'] ?? ''),
        'origin_industry_id' => $car['current_industry_id'],
        'destination_industry_id' => $destination['id'],
        'origin_industry_name' => $car['origin_name'],
        'destination_industry_name' => $destination['industry_name'],
        'origin_name' => $car['origin_name'],
        'destination_name' => $destination['industry_name'],
        'origin_track' => $car['current_track'],
        'current_track' => $car['current_track'],
        'destination_track' => '',
        'move_type' => $moveType,
        'instruction' => $instruction,
        'commodity' => $car['operations_service'] ?: ($car['load_status'] ?: ($car['equipment_type'] ?: '')),
        'status' => $car['load_status'] ?: 'Ready'
    ];
}

function getCompatibleSetoutDestinations(array $industries, array $car, int $operatingBaseId): array
{
    $serviceField = strcasecmp($car['load_status'] ?? '', 'Loaded') === 0
        ? 'receives_services'
        : 'ships_services';

    return array_values(array_filter(
        $industries,
        function ($industry) use ($car, $serviceField, $operatingBaseId) {
            if ((int)$industry['id'] === $operatingBaseId) {
                return false;
            }

            if (industryLooksLikeOperatingBase($industry)) {
                return false;
            }

            return industrySupportsOperationsService(
                $industry,
                $serviceField,
                $car['operations_service'] ?? ''
            );
        }
    ));
}

function getPullLoadStatus(array $car): ?string
{
    if (industrySupportsOperationsService(
        $car,
        'origin_ships_services',
        $car['operations_service'] ?? ''
    )) {
        return 'Loaded';
    }

    if (industrySupportsOperationsService(
        $car,
        'origin_receives_services',
        $car['operations_service'] ?? ''
    )) {
        return 'Empty';
    }

    return null;
}

function chooseBalancedMoves(array $setoutMoves, array $pullMoves, int $carCount): array
{
    shuffle($setoutMoves);
    shuffle($pullMoves);

    $selectedMoves = [];
    $takeSetoutNext = true;

    while (count($selectedMoves) < $carCount && (!empty($setoutMoves) || !empty($pullMoves))) {
        if ($takeSetoutNext && !empty($setoutMoves)) {
            $selectedMoves[] = array_shift($setoutMoves);
        }
        elseif (!$takeSetoutNext && !empty($pullMoves)) {
            $selectedMoves[] = array_shift($pullMoves);
        }
        elseif (!empty($setoutMoves)) {
            $selectedMoves[] = array_shift($setoutMoves);
        }
        elseif (!empty($pullMoves)) {
            $selectedMoves[] = array_shift($pullMoves);
        }

        $takeSetoutNext = !$takeSetoutNext;
    }

    return $selectedMoves;
}

$industries = [];
$operatingBaseOptions = [];
$locomotives = [];
$selectedOperatingBaseId = 0;
$selectedOperatingBaseName = '';
$selectedLocomotiveId = 0;
$selectedLocomotiveLabel = '';

if ($railroad) {
    $stmt = $pdo->prepare("
        SELECT
            id,
            industry_name,
            industry_type,
            receives_services,
            ships_services
        FROM industries
        WHERE railroad_id = :railroad_id
        ORDER BY industry_name
    ");

    $stmt->execute([
        'railroad_id' => $railroad['id']
    ]);

    $industries = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $preferredOperatingBases = array_values(array_filter($industries, 'industryLooksLikeOperatingBase'));
    $otherOperatingBases = array_values(array_filter(
        $industries,
        fn($industry) => !industryLooksLikeOperatingBase($industry)
    ));

    $operatingBaseOptions = !empty($preferredOperatingBases)
        ? array_merge($preferredOperatingBases, $otherOperatingBases)
        : $industries;

    $fallbackOperatingBaseId = !empty($operatingBaseOptions)
        ? (int)$operatingBaseOptions[0]['id']
        : 0;

    $selectedOperatingBaseId = (int)(
        $_POST['operating_base_id']
        ?? $_SESSION['generated_operating_base_id']
        ?? $fallbackOperatingBaseId
    );

    $selectedOperatingBase = findIndustryById($industries, $selectedOperatingBaseId);

    if ($selectedOperatingBase) {
        $selectedOperatingBaseName = $selectedOperatingBase['industry_name'];
    }

    $stmt = $pdo->prepare("
        SELECT
            id,
            reporting_marks,
            road_number,
            road_name,
            equipment_type
        FROM equipment
        WHERE railroad_id = :railroad_id
            AND active = 1
            AND equipment_class = 'Locomotive'
        ORDER BY reporting_marks, road_number
    ");

    $stmt->execute([
        'railroad_id' => $railroad['id']
    ]);

    $locomotives = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $fallbackLocomotiveId = !empty($locomotives)
        ? (int)$locomotives[0]['id']
        : 0;

    $selectedLocomotiveId = (int)(
        $_POST['locomotive_id']
        ?? $_SESSION['generated_locomotive_id']
        ?? $fallbackLocomotiveId
    );

    foreach ($locomotives as $locomotive) {
        if ((int)$locomotive['id'] === $selectedLocomotiveId) {
            $selectedLocomotiveLabel = buildLocomotiveLabel($locomotive);
            break;
        }
    }
}

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && $railroad
) {
    if ($selectedOperatingBaseId <= 0 || $selectedOperatingBaseName === '') {
        $skippedNoOperatingBase = 1;
    }

    if ($selectedLocomotiveId <= 0 || $selectedLocomotiveLabel === '') {
        $skippedNoLocomotive = 1;
    }

    if ($skippedNoOperatingBase === 0 && $skippedNoLocomotive === 0) {
        $stmt = $pdo->prepare("
            SELECT
                e.id AS equipment_id,
                e.reporting_marks,
                e.road_number,
                e.equipment_class,
                e.equipment_type,
                e.operations_service,
                e.load_status,
                e.current_industry_id,
                e.current_track,
                i.industry_name AS origin_name,
                i.receives_services AS origin_receives_services,
                i.ships_services AS origin_ships_services
            FROM equipment e
            JOIN industries i
                ON e.current_industry_id = i.id
            WHERE e.railroad_id = :railroad_id
                AND e.current_industry_id IS NOT NULL
                AND e.current_industry_id <> 0
                AND e.active = 1
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
        $setoutMoves = [];
        $pullMoves = [];
        $operatingBase = [
            'id' => $selectedOperatingBaseId,
            'industry_name' => $selectedOperatingBaseName
        ];

        shuffle($eligibleCars);

        foreach ($eligibleCars as $car) {
            if (trim($car['operations_service'] ?? '') === '') {
                $skippedNoOperationsService++;
                $skippedCarDiagnostics[] = buildSkippedCarDiagnostic(
                    $car,
                    'Missing Operations Service',
                    'Add an Operations Service before this car can be matched to industry work'
                );
                continue;
            }

            if ((int)$car['current_industry_id'] === $selectedOperatingBaseId) {
                $destinationOptions = getCompatibleSetoutDestinations(
                    $industries,
                    $car,
                    $selectedOperatingBaseId
                );

                if (count($destinationOptions) === 0) {
                    $skippedNoCompatibleDestination++;
                    $setoutServiceField = strcasecmp($car['load_status'] ?? '', 'Loaded') === 0
                        ? 'receives'
                        : 'ships';
                    $setoutReason = $setoutServiceField === 'receives'
                        ? 'No compatible destination found: At operating base, loaded car needs an industry that receives this service'
                        : 'No compatible destination found: At operating base, empty car needs an industry that ships this service';

                    $skippedCarDiagnostics[] = buildSkippedCarDiagnostic(
                        $car,
                        $setoutReason,
                        'Looking for non-support industry that ' . $setoutServiceField . ' ' . $car['operations_service']
                    );
                    continue;
                }

                $destination = $destinationOptions[array_rand($destinationOptions)];
                $loadText = strtolower($car['load_status'] ?: 'ready');
                $serviceText = $car['operations_service'] ?: ($car['equipment_type'] ?: 'car');

                $setoutMoves[] = buildGeneratedMove(
                    $car,
                    $destination,
                    'SETOUT',
                    'Set out ' . $loadText . ' ' . $serviceText . ' car at ' . $destination['industry_name']
                );

                continue;
            }

            $pullLoadStatus = getPullLoadStatus($car);

            if ($pullLoadStatus === null) {
                $skippedNoCompatibleDestination++;
                $skippedCarDiagnostics[] = buildSkippedCarDiagnostic(
                    $car,
                    'At industry, origin industry does not ship or receive this service',
                    ($car['origin_name'] ?: 'Origin industry') . ' must ship ' . $car['operations_service'] . ' to pull as Loaded or receive ' . $car['operations_service'] . ' to pull as Empty'
                );
                continue;
            }

            $pullCar = $car;
            $pullCar['original_load_status'] = $car['load_status'] ?? '';
            $pullCar['load_status'] = $pullLoadStatus;

            $loadText = strtolower($pullLoadStatus);
            $serviceText = $pullCar['operations_service'] ?: ($pullCar['equipment_type'] ?: 'car');

            $pullMoves[] = buildGeneratedMove(
                $pullCar,
                $operatingBase,
                'PULL',
                'Pull ' . $loadText . ' ' . $serviceText . ' car from ' . $pullCar['origin_name'] . ' to ' . $selectedOperatingBaseName
            );
        }

        $sessionWaybills = chooseBalancedMoves($setoutMoves, $pullMoves, $carCount);
        $setoutMoveCount = count(array_filter($sessionWaybills, fn($move) => ($move['move_type'] ?? '') === 'SETOUT'));
        $pullMoveCount = count(array_filter($sessionWaybills, fn($move) => ($move['move_type'] ?? '') === 'PULL'));
    }

    $skippedCarCount = $skippedNoOperationsService
        + $skippedNoCompatibleDestination
        + $skippedNoOperatingBase
        + $skippedNoLocomotive;

    $_SESSION['generated_session'] = $sessionWaybills;
    $_SESSION['generated_difficulty'] = $difficulty;
    $_SESSION['generated_car_count'] = $carCount;
    $_SESSION['generated_operating_base_id'] = $selectedOperatingBaseId;
    $_SESSION['generated_operating_base_name'] = $selectedOperatingBaseName;
    $_SESSION['generated_locomotive_id'] = $selectedLocomotiveId;
    $_SESSION['generated_locomotive_label'] = $selectedLocomotiveLabel;
    $_SESSION['generated_skip_counts'] = [
        'missing_operations_service' => $skippedNoOperationsService,
        'no_compatible_destination' => $skippedNoCompatibleDestination,
        'no_operating_base' => $skippedNoOperatingBase,
        'no_locomotive' => $skippedNoLocomotive
    ];
    $_SESSION['generated_skip_diagnostics'] = $skippedCarDiagnostics;
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

            <p class="tt-session-panel-copy">Create an operating session from active cars and locomotives currently placed on your railroad.</p>

            <form method="post" class="tt-session-options-form">
                <div class="tt-session-fieldset">
                    <label class="form-label" for="tt-session-operating-base">Operating Base</label>
                    <select
                    id="tt-session-operating-base"
                    name="operating_base_id"
                    class="form-select">
                        <?php if (empty($operatingBaseOptions)): ?>
                        <option value="">No industries available</option>
                        <?php else: ?>
                        <?php foreach ($operatingBaseOptions as $industry): ?>
                        <option
                        value="<?php echo (int)$industry['id']; ?>"
                        <?php if ((int)$industry['id'] === $selectedOperatingBaseId) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($industry['industry_name']); ?>
                        </option>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="tt-session-fieldset">
                    <label class="form-label" for="tt-session-locomotive">Assigned Locomotive</label>
                    <select
                    id="tt-session-locomotive"
                    name="locomotive_id"
                    class="form-select">
                        <?php if (empty($locomotives)): ?>
                        <option value="">No active locomotives available</option>
                        <?php else: ?>
                        <?php foreach ($locomotives as $locomotive): ?>
                        <option
                        value="<?php echo (int)$locomotive['id']; ?>"
                        <?php if ((int)$locomotive['id'] === $selectedLocomotiveId) echo 'selected'; ?>>
                            <?php echo htmlspecialchars(buildLocomotiveLabel($locomotive)); ?>
                        </option>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>

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
                    <strong><?php echo htmlspecialchars($selectedLocomotiveLabel ?: 'Not assigned'); ?></strong>
                </div>
                <div class="tt-session-placeholder-row">
                    <span>Base</span>
                    <strong><?php echo htmlspecialchars($selectedOperatingBaseName ?: 'Not selected'); ?></strong>
                </div>
            </section>
        </aside>
    </div>

    <?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>

    <?php if (count($sessionWaybills) == 0): ?>

    <div class="alert alert-warning tt-session-alert">
        <strong>No compatible operating moves available.</strong>
        <span>Select an operating base and active locomotive, then make sure active cars have Operations Service and compatible industry service fields.</span>
        <?php if ($skippedCarCount > 0): ?>
        <span><?php echo $skippedCarCount; ?> item(s) skipped: <?php echo $skippedNoOperationsService; ?> missing Operations Service, <?php echo $skippedNoCompatibleDestination; ?> with no compatible destination, <?php echo $skippedNoOperatingBase; ?> missing operating base, <?php echo $skippedNoLocomotive; ?> missing locomotive.</span>
        <?php endif; ?>
    </div>

    <?php if (!empty($skippedCarDiagnostics)): ?>
    <section class="tt-panel tt-generated-session-panel">
        <div class="tt-panel-heading">
            <div>
                <span class="tt-panel-kicker">Skipped Cars</span>
                <h2>Skip Diagnostics</h2>
            </div>
        </div>

        <div class="tt-generated-moves">
            <?php foreach ($skippedCarDiagnostics as $diagnostic): ?>
            <article class="tt-generated-move">
                <div class="tt-generated-move-header">
                    <span><?php echo htmlspecialchars($diagnostic['reason']); ?></span>
                    <strong><?php echo htmlspecialchars(trim($diagnostic['reporting_marks'] . ' ' . $diagnostic['road_number']) ?: 'Unknown car'); ?></strong>
                </div>
                <p class="tt-muted-text"><?php echo htmlspecialchars($diagnostic['looking_for']); ?></p>
                <dl>
                    <div>
                        <dt>Car Type</dt>
                        <dd><?php echo htmlspecialchars($diagnostic['equipment_type'] ?: '-'); ?></dd>
                    </div>
                    <div>
                        <dt>Load Status</dt>
                        <dd><?php echo htmlspecialchars($diagnostic['load_status'] ?: '-'); ?></dd>
                    </div>
                    <div>
                        <dt>Operations Service</dt>
                        <dd><?php echo htmlspecialchars($diagnostic['operations_service'] ?: '-'); ?></dd>
                    </div>
                    <div>
                        <dt>Current Location</dt>
                        <dd><?php echo htmlspecialchars($diagnostic['origin_name'] ?: '-'); ?></dd>
                    </div>
                    <div>
                        <dt>Current Track</dt>
                        <dd><?php echo htmlspecialchars($diagnostic['current_track'] ?: '-'); ?></dd>
                    </div>
                </dl>
            </article>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

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
                <span>Operating Base</span>
                <strong><?php echo htmlspecialchars($selectedOperatingBaseName ?: '-'); ?></strong>
            </div>
            <div>
                <span>Locomotive</span>
                <strong><?php echo htmlspecialchars($selectedLocomotiveLabel ?: '-'); ?></strong>
            </div>
            <div>
                <span>Cars Requested</span>
                <strong><?php echo $carCount; ?></strong>
            </div>
            <div>
                <span>Setouts</span>
                <strong><?php echo $setoutMoveCount; ?></strong>
            </div>
            <div>
                <span>Pulls</span>
                <strong><?php echo $pullMoveCount; ?></strong>
            </div>
            <div>
                <span>Cars Skipped</span>
                <strong><?php echo $skippedCarCount; ?></strong>
            </div>
        </div>

        <?php if ($skippedCarCount > 0): ?>
        <p class="tt-muted-text">Skipped <?php echo $skippedNoOperationsService; ?> car(s) missing Operations Service, <?php echo $skippedNoCompatibleDestination; ?> car(s) with no compatible move, <?php echo $skippedNoOperatingBase; ?> missing base, and <?php echo $skippedNoLocomotive; ?> missing locomotive.</p>
        <?php endif; ?>

        <?php if (!empty($skippedCarDiagnostics)): ?>
        <div class="tt-generated-skip-diagnostics">
            <div class="tt-panel-heading">
                <div>
                    <span class="tt-panel-kicker">Skipped Cars</span>
                    <h3>Skip Diagnostics</h3>
                </div>
            </div>

            <div class="tt-generated-moves">
                <?php foreach ($skippedCarDiagnostics as $diagnostic): ?>
                <article class="tt-generated-move">
                    <div class="tt-generated-move-header">
                        <span><?php echo htmlspecialchars($diagnostic['reason']); ?></span>
                        <strong><?php echo htmlspecialchars(trim($diagnostic['reporting_marks'] . ' ' . $diagnostic['road_number']) ?: 'Unknown car'); ?></strong>
                    </div>
                    <p class="tt-muted-text"><?php echo htmlspecialchars($diagnostic['looking_for']); ?></p>
                    <dl>
                        <div>
                            <dt>Car Type</dt>
                            <dd><?php echo htmlspecialchars($diagnostic['equipment_type'] ?: '-'); ?></dd>
                        </div>
                        <div>
                            <dt>Load Status</dt>
                            <dd><?php echo htmlspecialchars($diagnostic['load_status'] ?: '-'); ?></dd>
                        </div>
                        <div>
                            <dt>Operations Service</dt>
                            <dd><?php echo htmlspecialchars($diagnostic['operations_service'] ?: '-'); ?></dd>
                        </div>
                        <div>
                            <dt>Current Location</dt>
                            <dd><?php echo htmlspecialchars($diagnostic['origin_name'] ?: '-'); ?></dd>
                        </div>
                        <div>
                            <dt>Current Track</dt>
                            <dd><?php echo htmlspecialchars($diagnostic['current_track'] ?: '-'); ?></dd>
                        </div>
                    </dl>
                </article>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="tt-generated-moves">
            <?php foreach ($sessionWaybills as $index => $waybill): ?>

            <article class="tt-generated-move">
                <div class="tt-generated-move-header">
                    <span><?php echo htmlspecialchars($waybill['move_type'] ?? 'Move'); ?> <?php echo $index + 1; ?></span>
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

                <?php if (!empty($waybill['instruction'])): ?>
                <p class="tt-muted-text"><?php echo htmlspecialchars($waybill['instruction']); ?></p>
                <?php endif; ?>

                <dl>
                    <div>
                        <dt>Move Type</dt>
                        <dd><?php echo htmlspecialchars($waybill['move_type'] ?? '-'); ?></dd>
                    </div>
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
                        <dt>Operations Service</dt>
                        <dd><?php echo htmlspecialchars($waybill['operations_service'] ?: '-'); ?></dd>
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

            <input
            type="hidden"
            name="operating_base_id"
            value="<?php echo (int)$selectedOperatingBaseId; ?>">

            <input
            type="hidden"
            name="locomotive_id"
            value="<?php echo (int)$selectedLocomotiveId; ?>">

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
