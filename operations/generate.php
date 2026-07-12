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

$completionMessage = is_string($_SESSION['switch_completion_message'] ?? null)
    ? $_SESSION['switch_completion_message']
    : '';
$completionError = is_string($_SESSION['switch_completion_error'] ?? null)
    ? $_SESSION['switch_completion_error']
    : '';
$completionStorageKeyToClear = is_string($_SESSION['switch_completion_clear_storage_key'] ?? null)
    ? $_SESSION['switch_completion_clear_storage_key']
    : '';
unset(
    $_SESSION['switch_completion_message'],
    $_SESSION['switch_completion_error'],
    $_SESSION['switch_completion_clear_storage_key']
);

if (!preg_match('/^tt_switch_progress_generated_[a-f0-9]{24}$/', $completionStorageKeyToClear)) {
    $completionStorageKeyToClear = '';
}

if (!is_string($_SESSION['switch_completion_csrf_token'] ?? null)) {
    $_SESSION['switch_completion_csrf_token'] = bin2hex(random_bytes(24));
}

$sessionWaybills = [];
$skippedCarDiagnostics = [];
$skippedNoOperationsService = 0;
$skippedNoCompatibleDestination = 0;
$skippedNoOperatingBase = 0;
$skippedNoLocomotive = 0;
$skippedCarCount = 0;
$setoutMoveCount = 0;
$pullMoveCount = 0;
$generatedSessionId = is_string($_SESSION['generated_session_id'] ?? null)
    ? $_SESSION['generated_session_id']
    : '';

if (
    $_SERVER['REQUEST_METHOD'] !== 'POST'
    && $generatedSessionId !== ''
    && is_array($_SESSION['generated_session'] ?? null)
) {
    $sessionWaybills = $_SESSION['generated_session'];
    $storedSkipCounts = is_array($_SESSION['generated_skip_counts'] ?? null)
        ? $_SESSION['generated_skip_counts']
        : [];
    $skippedNoOperationsService = (int)($storedSkipCounts['missing_operations_service'] ?? 0);
    $skippedNoCompatibleDestination = (int)($storedSkipCounts['no_compatible_destination'] ?? 0);
    $skippedNoOperatingBase = (int)($storedSkipCounts['no_operating_base'] ?? 0);
    $skippedNoLocomotive = (int)($storedSkipCounts['no_locomotive'] ?? 0);
    $skippedCarDiagnostics = is_array($_SESSION['generated_skip_diagnostics'] ?? null)
        ? $_SESSION['generated_skip_diagnostics']
        : [];
    $skippedCarCount = $skippedNoOperationsService
        + $skippedNoCompatibleDestination
        + $skippedNoOperatingBase
        + $skippedNoLocomotive;
    $setoutMoveCount = count(array_filter(
        $sessionWaybills,
        fn($move) => is_array($move) && ($move['move_type'] ?? '') === 'SETOUT'
    ));
    $pullMoveCount = count(array_filter(
        $sessionWaybills,
        fn($move) => is_array($move) && ($move['move_type'] ?? '') === 'PULL'
    ));
}

$difficulty = $_POST['difficulty']
    ?? $_SESSION['generated_difficulty']
    ?? 'medium';
$carCount = (int)(
    $_POST['car_count']
    ?? $_SESSION['generated_car_count']
    ?? 5
);

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

function isWildcardOperationsService(string $service): bool
{
    return in_array(
        normalizeOperationsServiceValue($service),
        ['all', 'any', '*', 'all / any service'],
        true
    );
}

function industrySupportsOperationsService(array $industry, string $serviceField, string $operationsService): bool
{
    $service = normalizeOperationsServiceValue($operationsService);

    if ($service === '') {
        return false;
    }

    $industryServices = parseOperationsServiceList($industry[$serviceField] ?? '');

    foreach ($industryServices as $industryService) {
        if (isWildcardOperationsService($industryService)) {
            return true;
        }
    }

    return in_array(
        $service,
        $industryServices,
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

function normalizeSelectedLocomotiveIds($values, array $locomotivesById): array
{
    if (!is_array($values)) {
        return [];
    }

    $normalizedIds = [];

    foreach ($values as $value) {
        if (is_int($value)) {
            $locomotiveId = $value;
        }
        elseif (is_string($value) && ctype_digit(trim($value))) {
            $locomotiveId = (int)trim($value);
        }
        else {
            continue;
        }

        if (
            $locomotiveId > 0
            && isset($locomotivesById[$locomotiveId])
            && !in_array($locomotiveId, $normalizedIds, true)
        ) {
            $normalizedIds[] = $locomotiveId;
        }
    }

    return $normalizedIds;
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

function getGeneratedMoveActionLabel(array $move): string
{
    $moveType = strtoupper(trim((string)($move['move_type'] ?? '')));

    if ($moveType === 'PULL') {
        return 'PULL';
    }

    if ($moveType === 'SETOUT') {
        return 'SPOT';
    }

    return $moveType ?: 'WORK';
}

function getGeneratedMoveWorkLocation(array $move): string
{
    $moveType = strtoupper(trim((string)($move['move_type'] ?? '')));

    if ($moveType === 'PULL') {
        $location = $move['origin_industry_name'] ?? ($move['origin_name'] ?? '');
    }
    else {
        $location = $move['destination_industry_name'] ?? ($move['destination_name'] ?? '');
    }

    $location = trim((string)$location);

    return $location !== '' ? $location : 'Unassigned Work Location';
}

function groupGeneratedMovesByLocation(array $moves): array
{
    $groups = [];

    foreach ($moves as $move) {
        $location = getGeneratedMoveWorkLocation($move);
        $key = strtolower($location);

        if (!isset($groups[$key])) {
            $groups[$key] = [
                'location' => $location,
                'moves' => []
            ];
        }

        $groups[$key]['moves'][] = $move;
    }

    return array_values($groups);
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
$selectedLocomotiveIds = [];
$selectedLocomotiveLabels = [];
$selectedLocomotiveDisplay = '';

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

    $locomotivesById = [];

    foreach ($locomotives as $locomotive) {
        $locomotivesById[(int)$locomotive['id']] = $locomotive;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (array_key_exists('locomotive_ids', $_POST)) {
            $selectedLocomotiveIds = normalizeSelectedLocomotiveIds(
                is_array($_POST['locomotive_ids']) ? $_POST['locomotive_ids'] : [],
                $locomotivesById
            );
        }

        if (empty($selectedLocomotiveIds) && array_key_exists('locomotive_id', $_POST)) {
            $selectedLocomotiveIds = normalizeSelectedLocomotiveIds(
                [$_POST['locomotive_id']],
                $locomotivesById
            );
        }
    }
    else {
        $rememberedLocomotiveIds = $_SESSION['generated_locomotive_ids'] ?? null;

        if (is_array($rememberedLocomotiveIds)) {
            $selectedLocomotiveIds = normalizeSelectedLocomotiveIds(
                $rememberedLocomotiveIds,
                $locomotivesById
            );
        }

        if (empty($selectedLocomotiveIds) && isset($_SESSION['generated_locomotive_id'])) {
            $selectedLocomotiveIds = normalizeSelectedLocomotiveIds(
                [$_SESSION['generated_locomotive_id']],
                $locomotivesById
            );
        }

        if (empty($selectedLocomotiveIds) && !empty($locomotives)) {
            $selectedLocomotiveIds = [(int)$locomotives[0]['id']];
        }
    }

    $validatedSelectedLocomotiveIds = [];

    foreach ($selectedLocomotiveIds as $selectedLocomotiveId) {
        $selectedLocomotiveLabel = buildLocomotiveLabel(
            $locomotivesById[$selectedLocomotiveId]
        );

        if ($selectedLocomotiveLabel === '') {
            continue;
        }

        $validatedSelectedLocomotiveIds[] = $selectedLocomotiveId;
        $selectedLocomotiveLabels[] = $selectedLocomotiveLabel;
    }

    $selectedLocomotiveIds = $validatedSelectedLocomotiveIds;
    $selectedLocomotiveDisplay = implode(', ', $selectedLocomotiveLabels);
}

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && $railroad
) {
    if ($selectedOperatingBaseId <= 0 || $selectedOperatingBaseName === '') {
        $skippedNoOperatingBase = 1;
    }

    if (empty($selectedLocomotiveIds) || empty($selectedLocomotiveLabels)) {
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
                i.industry_type AS origin_industry_type,
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

            if (industryLooksLikeOperatingBase([
                'industry_name' => $car['origin_name'] ?? '',
                'industry_type' => $car['origin_industry_type'] ?? ''
            ])) {
                $skippedCarDiagnostics[] = buildSkippedCarDiagnostic(
                    $car,
                    'At support location, not selected operating base',
                    'Select this location as the operating base or move this car to a customer industry.'
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

        foreach ($sessionWaybills as $moveIndex => $move) {
            $sessionWaybills[$moveIndex]['completion_key'] = 'move-' . $moveIndex;
        }

        $generatedSessionId = bin2hex(random_bytes(12));
        $setoutMoveCount = count(array_filter($sessionWaybills, fn($move) => ($move['move_type'] ?? '') === 'SETOUT'));
        $pullMoveCount = count(array_filter($sessionWaybills, fn($move) => ($move['move_type'] ?? '') === 'PULL'));
    }

    $skippedCarCount = $skippedNoOperationsService
        + $skippedNoCompatibleDestination
        + $skippedNoOperatingBase
        + $skippedNoLocomotive;

    $_SESSION['generated_session'] = $sessionWaybills;
    $_SESSION['generated_session_id'] = $generatedSessionId;
    $_SESSION['generated_difficulty'] = $difficulty;
    $_SESSION['generated_car_count'] = $carCount;
    $_SESSION['generated_operating_base_id'] = $selectedOperatingBaseId;
    $_SESSION['generated_operating_base_name'] = $selectedOperatingBaseName;
    $_SESSION['generated_locomotive_ids'] = $selectedLocomotiveIds;
    $_SESSION['generated_locomotive_labels'] = $selectedLocomotiveLabels;
    $_SESSION['generated_locomotive_id'] = $selectedLocomotiveIds[0] ?? 0;
    $_SESSION['generated_locomotive_label'] = $selectedLocomotiveDisplay;
    $_SESSION['generated_skip_counts'] = [
        'missing_operations_service' => $skippedNoOperationsService,
        'no_compatible_destination' => $skippedNoCompatibleDestination,
        'no_operating_base' => $skippedNoOperatingBase,
        'no_locomotive' => $skippedNoLocomotive
    ];
    $_SESSION['generated_skip_diagnostics'] = $skippedCarDiagnostics;

    if (!empty($sessionWaybills)) {
        header('Location: generate.php?generated=1#generated-switch-list');
        exit;
    }
}

$workLocationGroups = groupGeneratedMovesByLocation($sessionWaybills);
$generatedSwitchProgressStorageKey = $generatedSessionId !== ''
    ? 'tt_switch_progress_generated_' . $generatedSessionId
    : '';
$renderedLocomotiveIds = !empty($selectedLocomotiveIds)
    ? $selectedLocomotiveIds
    : [0];

?>

<?php
$pageTitle='Start Session';
include '../assets/components/header.php';
include '../assets/components/sidebar.php';
?>
<link rel="stylesheet" href="../assets/css/dashboard.css">
<link rel="stylesheet" href="../assets/css/switch_list_completion.css">

<div class="tt-session-start-page">
    <?php if ($completionStorageKeyToClear !== ''): ?>
    <span
    hidden
    data-switch-clear-storage-key="<?php echo htmlspecialchars($completionStorageKeyToClear); ?>"></span>
    <?php endif; ?>

    <?php if ($completionMessage !== ''): ?>
    <div class="alert alert-success tt-session-alert" role="status">
        <?php echo htmlspecialchars($completionMessage); ?>
    </div>
    <?php endif; ?>

    <?php if ($completionError !== ''): ?>
    <div class="alert alert-danger tt-session-alert" role="alert">
        <?php echo htmlspecialchars($completionError); ?>
    </div>
    <?php endif; ?>

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
                    <span class="form-label d-block">Assigned Locos</span>
                    <?php if (empty($locomotives)): ?>
                    <div class="tt-session-locomotive-empty">No active locomotives available</div>
                    <?php else: ?>
                    <div id="tt-session-locomotive-fields" class="tt-session-locomotive-fields">
                        <?php foreach ($renderedLocomotiveIds as $locomotiveIndex => $renderedLocomotiveId): ?>
                        <?php $isAdditionalLocomotive = $locomotiveIndex > 0; ?>
                        <div class="tt-session-locomotive-row<?php if ($isAdditionalLocomotive) echo ' is-removable'; ?>">
                            <label
                            class="visually-hidden"
                            for="tt-session-locomotive-<?php echo $locomotiveIndex + 1; ?>">
                                Assigned Loco <?php echo $locomotiveIndex + 1; ?>
                            </label>
                            <select
                            id="tt-session-locomotive-<?php echo $locomotiveIndex + 1; ?>"
                            name="locomotive_ids[]"
                            class="form-select tt-session-locomotive-select">
                                <option value="" <?php if ($renderedLocomotiveId === 0) echo 'selected'; ?>>Select a locomotive</option>
                                <?php foreach ($locomotives as $locomotive): ?>
                                <?php $locomotiveId = (int)$locomotive['id']; ?>
                                <option
                                value="<?php echo $locomotiveId; ?>"
                                <?php if ($locomotiveId === $renderedLocomotiveId) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars(buildLocomotiveLabel($locomotive)); ?>
                                </option>
                                <?php endforeach; ?>
                                <?php if ($isAdditionalLocomotive): ?>
                                <option value="" data-remove-loco>Remove Loco</option>
                                <?php endif; ?>
                            </select>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    <button
                    type="button"
                    id="tt-add-locomotive"
                    class="tt-add-locomotive"
                    data-locomotive-count="<?php echo count($locomotives); ?>"
                    <?php if (empty($locomotives)) echo 'disabled'; ?>>
                        + Add Another Loco
                    </button>
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
                    <span>Assigned Locos</span>
                    <strong><?php echo htmlspecialchars($selectedLocomotiveDisplay ?: 'Not assigned'); ?></strong>
                </div>
                <div class="tt-session-placeholder-row">
                    <span>Base</span>
                    <strong><?php echo htmlspecialchars($selectedOperatingBaseName ?: 'Not selected'); ?></strong>
                </div>
            </section>
        </aside>
    </div>

    <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' || count($sessionWaybills) > 0): ?>

    <?php if (count($sessionWaybills) == 0): ?>

    <div class="alert alert-warning tt-session-alert">
        <strong>No compatible operating moves available.</strong>
        <span>Select an operating base and at least one active locomotive, then make sure active cars have Operations Service and compatible industry service fields.</span>
        <?php if ($skippedCarCount > 0): ?>
        <span><?php echo $skippedCarCount; ?> item(s) skipped: <?php echo $skippedNoOperationsService; ?> missing Operations Service, <?php echo $skippedNoCompatibleDestination; ?> with no compatible destination, <?php echo $skippedNoOperatingBase; ?> missing operating base, <?php echo $skippedNoLocomotive; ?> missing locomotive.</span>
        <?php endif; ?>
    </div>

    <?php if (!empty($skippedCarDiagnostics)): ?>
    <details class="tt-panel tt-generated-session-panel tt-generated-skip-diagnostics">
        <summary>
            <strong>Cars Not Used This Session</strong>
            <span class="tt-muted-text"><?php echo count($skippedCarDiagnostics); ?> cars were not used. Open details.</span>
        </summary>

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
    </details>
    <?php endif; ?>

    <?php else: ?>

    <section
    id="generated-switch-list"
    class="tt-panel tt-generated-session-panel"
    data-switch-progress
    data-switch-exceptions="1"
    data-switch-storage-key="<?php echo htmlspecialchars($generatedSwitchProgressStorageKey); ?>">
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
                <span>Assigned Locos</span>
                <strong><?php echo htmlspecialchars($selectedLocomotiveDisplay ?: '-'); ?></strong>
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

        <form
        method="post"
        action="complete_switch_list.php"
        class="tt-switch-completion-form"
        data-switch-completion-form>
            <input
            type="hidden"
            name="csrf_token"
            value="<?php echo htmlspecialchars($_SESSION['switch_completion_csrf_token']); ?>">

            <input
            type="hidden"
            name="generated_session_id"
            value="<?php echo htmlspecialchars($generatedSessionId); ?>">

            <div class="tt-switch-progress no-print" data-switch-progress-counter>
                0 moved, 0 not moved, <?php echo count($sessionWaybills); ?> pending
            </div>

            <div class="tt-generated-work-by-location">
            <div class="tt-panel-heading">
                <div>
                    <span class="tt-panel-kicker">Work by Location</span>
                    <h3>Local Switcher Work Order</h3>
                </div>
            </div>

            <?php foreach ($workLocationGroups as $group): ?>
            <section class="tt-generated-location-work">
                <h4><?php echo htmlspecialchars($group['location']); ?></h4>

                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead>
                            <tr>
                                <th class="tt-switch-done-column">Moved</th>
                                <th class="tt-switch-exception-column">Not Moved</th>
                                <th>Action</th>
                                <th>Car</th>
                                <th>Type</th>
                                <th>Load</th>
                                <th>Service</th>
                                <th>From / Current Track</th>
                                <th>To / Destination</th>
                                <th class="tt-switch-destination-column">Destination Track</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($group['moves'] as $waybill): ?>
                            <?php
                            $carLabel = trim(($waybill['reporting_marks'] ?? '') . ' ' . ($waybill['road_number'] ?? ''));
                            $moveKey = (string)($waybill['completion_key'] ?? '');
                            ?>
                            <tr
                            data-switch-move-row
                            data-switch-move-key="<?php echo htmlspecialchars($moveKey); ?>">
                                <td class="tt-switch-done-cell">
                                    <label>
                                        <input
                                        type="checkbox"
                                        class="tt-switch-move-checkbox"
                                        data-switch-move-key="<?php echo htmlspecialchars($moveKey); ?>"
                                        aria-label="Mark <?php echo htmlspecialchars(strtolower(getGeneratedMoveActionLabel($waybill)) . ' ' . ($carLabel ?: 'car')); ?> moved">
                                    </label>
                                    <input
                                    type="hidden"
                                    name="moves[<?php echo htmlspecialchars($moveKey); ?>][outcome]"
                                    value="pending"
                                    data-switch-outcome>
                                </td>
                                <td class="tt-switch-exception-column">
                                    <button
                                    type="button"
                                    class="btn btn-sm btn-outline-warning tt-switch-not-moved-button"
                                    data-switch-not-moved
                                    aria-pressed="false">
                                        Not Moved
                                    </button>

                                    <div class="tt-switch-exception-fields" data-switch-exception-fields hidden>
                                        <label
                                        class="visually-hidden"
                                        for="tt-switch-reason-<?php echo htmlspecialchars($moveKey); ?>">
                                            Reason <?php echo htmlspecialchars($carLabel ?: 'car'); ?> was not moved
                                        </label>
                                        <select
                                        id="tt-switch-reason-<?php echo htmlspecialchars($moveKey); ?>"
                                        name="moves[<?php echo htmlspecialchars($moveKey); ?>][reason_code]"
                                        class="form-select form-select-sm"
                                        data-switch-reason
                                        disabled>
                                            <option value="">Select a reason</option>
                                            <option value="track_blocked">Track blocked</option>
                                            <option value="car_inaccessible">Car inaccessible</option>
                                            <option value="industry_track_full">Industry track full</option>
                                            <option value="bad_order">Bad order</option>
                                            <option value="wrong_car">Wrong car</option>
                                            <option value="customer_not_ready">Customer not ready</option>
                                            <option value="locomotive_or_crew_issue">Locomotive or crew issue</option>
                                            <option value="other">Other</option>
                                        </select>

                                        <label
                                        class="visually-hidden"
                                        for="tt-switch-reason-notes-<?php echo htmlspecialchars($moveKey); ?>">
                                            Not Moved notes for <?php echo htmlspecialchars($carLabel ?: 'car'); ?>
                                        </label>
                                        <input
                                        id="tt-switch-reason-notes-<?php echo htmlspecialchars($moveKey); ?>"
                                        type="text"
                                        name="moves[<?php echo htmlspecialchars($moveKey); ?>][reason_notes]"
                                        class="form-control form-control-sm"
                                        maxlength="250"
                                        placeholder="Optional note (required for Other)"
                                        data-switch-reason-notes
                                        disabled>
                                    </div>
                                </td>
                                <td><strong><?php echo htmlspecialchars(getGeneratedMoveActionLabel($waybill)); ?></strong></td>
                                <td class="tt-switch-primary-move"><?php echo htmlspecialchars($carLabel ?: '-'); ?></td>
                                <td><?php echo htmlspecialchars($waybill['equipment_type'] ?: '-'); ?></td>
                                <td><?php echo htmlspecialchars($waybill['load_status'] ?: '-'); ?></td>
                                <td><?php echo htmlspecialchars($waybill['operations_service'] ?: '-'); ?></td>
                                <td>
                                    <?php echo htmlspecialchars($waybill['origin_industry_name'] ?? ($waybill['origin_name'] ?? '-')); ?>
                                    <?php if (!empty($waybill['current_track'])): ?>
                                    <br><small><?php echo htmlspecialchars($waybill['current_track']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($waybill['destination_industry_name'] ?? ($waybill['destination_name'] ?? '-')); ?></td>
                                <td class="tt-switch-destination-column">
                                    <label
                                    class="visually-hidden"
                                    for="tt-switch-destination-track-<?php echo htmlspecialchars($moveKey); ?>">
                                        Destination Track for <?php echo htmlspecialchars($carLabel ?: 'car'); ?>
                                    </label>
                                    <input
                                    id="tt-switch-destination-track-<?php echo htmlspecialchars($moveKey); ?>"
                                    type="text"
                                    name="moves[<?php echo htmlspecialchars($moveKey); ?>][destination_track]"
                                    class="form-control form-control-sm tt-switch-destination-input"
                                    maxlength="50"
                                    placeholder="Optional"
                                    data-switch-destination-track
                                    disabled>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
            <?php endforeach; ?>
            </div>

            <section class="tt-panel tt-switch-completion-panel no-print">
                <div>
                    <h3>Complete Switch List</h3>
                    <p class="tt-muted-text" data-switch-completion-status>
                        Resolve every pending move before completing the switch list.
                    </p>
                </div>
                <button
                type="submit"
                class="btn btn-success"
                data-switch-complete-button
                disabled>
                    Complete Switch List
                </button>
            </section>
        </form>

        <?php if (!empty($skippedCarDiagnostics)): ?>
        <details class="tt-generated-skip-diagnostics">
            <summary>
                <strong>Cars Not Used This Session</strong>
                <span class="tt-muted-text"><?php echo count($skippedCarDiagnostics); ?> cars were not used. Open details.</span>
            </summary>

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
        </details>
        <?php endif; ?>

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

            <?php foreach ($selectedLocomotiveIds as $selectedLocomotiveId): ?>
            <input
            type="hidden"
            name="locomotive_ids[]"
            value="<?php echo (int)$selectedLocomotiveId; ?>">
            <?php endforeach; ?>

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

<script src="../assets/js/switch_list_progress.js"></script>

<script>
(function () {
    const fields = document.getElementById('tt-session-locomotive-fields');
    const addButton = document.getElementById('tt-add-locomotive');

    if (!fields || !addButton) {
        return;
    }

    const locomotiveCount = Number.parseInt(
        addButton.dataset.locomotiveCount || '0',
        10
    );

    function getRows() {
        return Array.from(fields.querySelectorAll('.tt-session-locomotive-row'));
    }

    function getSelects() {
        return Array.from(fields.querySelectorAll('.tt-session-locomotive-select'));
    }

    function updateRowLabels() {
        getRows().forEach(function (row, index) {
            const select = row.querySelector('.tt-session-locomotive-select');
            const label = row.querySelector('label');
            const rowNumber = index + 1;

            if (!select || !label) {
                return;
            }

            select.id = 'tt-session-locomotive-' + rowNumber;
            label.htmlFor = select.id;
            label.textContent = 'Assigned Loco ' + rowNumber;
        });
    }

    function ensureRemoveOption(row) {
        const select = row.querySelector('.tt-session-locomotive-select');

        if (!select || select.querySelector('[data-remove-loco]')) {
            return;
        }

        const removeOption = document.createElement('option');
        removeOption.value = '';
        removeOption.textContent = 'Remove Loco';
        removeOption.setAttribute('data-remove-loco', '');
        select.appendChild(removeOption);
    }

    function refreshLocomotiveOptions() {
        const selects = getSelects();
        const selectedValues = new Set(
            selects.map(function (select) {
                return select.value;
            }).filter(function (value) {
                return value !== '';
            })
        );

        selects.forEach(function (select) {
            Array.from(select.options).forEach(function (option) {
                if (option.value === '' || option.hasAttribute('data-remove-loco')) {
                    option.disabled = false;
                    return;
                }

                option.disabled = selectedValues.has(option.value)
                    && select.value !== option.value;
            });
        });

        const allAssigned = locomotiveCount > 0
            && selectedValues.size >= locomotiveCount;
        const atRowCapacity = locomotiveCount > 0
            && selects.length >= locomotiveCount;

        addButton.disabled = locomotiveCount === 0
            || allAssigned
            || atRowCapacity;

        if (allAssigned) {
            addButton.title = 'All active locomotives are assigned';
        }
        else if (atRowCapacity) {
            addButton.title = 'All locomotive dropdowns are in use';
        }
        else if (locomotiveCount === 0) {
            addButton.title = 'No active locomotives are available';
        }
        else {
            addButton.removeAttribute('title');
        }
    }

    function handleLocomotiveChange(event) {
        const select = event.currentTarget;
        const row = select.closest('.tt-session-locomotive-row');
        const selectedOption = select.options[select.selectedIndex];

        if (
            row
            && row.classList.contains('is-removable')
            && selectedOption
            && selectedOption.hasAttribute('data-remove-loco')
        ) {
            row.remove();
            updateRowLabels();
        }

        refreshLocomotiveOptions();
    }

    function bindLocomotiveSelect(select) {
        select.addEventListener('change', handleLocomotiveChange);
    }

    getSelects().forEach(bindLocomotiveSelect);
    updateRowLabels();
    refreshLocomotiveOptions();

    addButton.addEventListener('click', function () {
        if (addButton.disabled) {
            return;
        }

        const firstRow = getRows()[0];

        if (!firstRow) {
            return;
        }

        const newRow = firstRow.cloneNode(true);
        const newSelect = newRow.querySelector('.tt-session-locomotive-select');

        if (!newSelect) {
            return;
        }

        newRow.classList.add('is-removable');

        Array.from(newSelect.options).forEach(function (option) {
            option.disabled = false;
            option.selected = option.value === ''
                && !option.hasAttribute('data-remove-loco');
        });

        ensureRemoveOption(newRow);
        fields.appendChild(newRow);
        updateRowLabels();
        bindLocomotiveSelect(newSelect);
        refreshLocomotiveOptions();
        newSelect.focus();
    });
}());
</script>

<?php include '../assets/components/footer.php'; ?>
