<?php

session_start();

require_once '../config/database.php';

function redirectToGeneratedSessionWithError(string $message): void
{
    $_SESSION['switch_completion_error'] = $message;
    header('Location: generate.php#generated-switch-list');
    exit;
}

function switchCompletionTextLength(string $value): int
{
    return function_exists('mb_strlen')
        ? mb_strlen($value, 'UTF-8')
        : strlen($value);
}

ini_set('display_errors', '0');

set_exception_handler(function (Throwable $exception): void {
    error_log('Generated switch list completion request failed: ' . $exception->getMessage());
    redirectToGeneratedSessionWithError('The switch list could not be completed. No cars were changed.');
});

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectToGeneratedSessionWithError('Complete Switch List accepts confirmed switch-list submissions only.');
}

$submittedToken = is_string($_POST['csrf_token'] ?? null)
    ? $_POST['csrf_token']
    : '';
$sessionToken = is_string($_SESSION['switch_completion_csrf_token'] ?? null)
    ? $_SESSION['switch_completion_csrf_token']
    : '';

if (
    $submittedToken === ''
    || $sessionToken === ''
    || !hash_equals($sessionToken, $submittedToken)
) {
    redirectToGeneratedSessionWithError('The switch-list confirmation expired. Review the active session and try again.');
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

if (!$railroad) {
    redirectToGeneratedSessionWithError('No railroad is available for this account. No cars were changed.');
}

$railroadId = (int)$railroad['id'];
$userId = (int)$_SESSION['user_id'];
$activeSessionId = is_string($_SESSION['generated_session_id'] ?? null)
    ? $_SESSION['generated_session_id']
    : '';
$submittedSessionId = is_string($_POST['generated_session_id'] ?? null)
    ? trim($_POST['generated_session_id'])
    : '';
$activeMoves = $_SESSION['generated_session'] ?? null;

if (
    $activeSessionId === ''
    || !preg_match('/^[a-f0-9]{24}$/', $activeSessionId)
    || $submittedSessionId === ''
    || !hash_equals($activeSessionId, $submittedSessionId)
    || !is_array($activeMoves)
    || empty($activeMoves)
) {
    redirectToGeneratedSessionWithError('This generated switch list is no longer active. Generate a new session before completing work.');
}

$movesByKey = [];
$equipmentIds = [];
$industryIds = [];

foreach ($activeMoves as $move) {
    if (!is_array($move)) {
        redirectToGeneratedSessionWithError('The active generated switch list is invalid. Generate a new session before completing work.');
    }

    $moveKey = is_string($move['completion_key'] ?? null)
        ? $move['completion_key']
        : '';
    $equipmentId = (int)($move['equipment_id'] ?? 0);
    $originIndustryId = (int)($move['origin_industry_id'] ?? 0);
    $destinationIndustryId = (int)($move['destination_industry_id'] ?? 0);

    if (
        !preg_match('/^move-[0-9]+$/', $moveKey)
        || isset($movesByKey[$moveKey])
        || $equipmentId <= 0
        || isset($equipmentIds[$equipmentId])
        || $originIndustryId <= 0
        || $destinationIndustryId <= 0
    ) {
        redirectToGeneratedSessionWithError('The active generated switch list is invalid. Generate a new session before completing work.');
    }

    $movesByKey[$moveKey] = $move;
    $equipmentIds[$equipmentId] = $equipmentId;
    $industryIds[$originIndustryId] = $originIndustryId;
    $industryIds[$destinationIndustryId] = $destinationIndustryId;
}

$submittedMoves = $_POST['moves'] ?? null;

if (!is_array($submittedMoves)) {
    redirectToGeneratedSessionWithError('Every generated move must be marked Moved or Not Moved before completion.');
}

$expectedMoveKeys = array_keys($movesByKey);
$submittedMoveKeys = array_keys($submittedMoves);
sort($expectedMoveKeys);
sort($submittedMoveKeys);

if ($expectedMoveKeys !== $submittedMoveKeys) {
    redirectToGeneratedSessionWithError('The submitted moves do not match the active generated switch list. No cars were changed.');
}

$allowedReasonCodes = [
    'track_blocked',
    'car_inaccessible',
    'industry_track_full',
    'bad_order',
    'wrong_car',
    'customer_not_ready',
    'locomotive_or_crew_issue',
    'other'
];
$completionResults = [];
$movedCount = 0;
$skippedCount = 0;

foreach ($movesByKey as $moveKey => $move) {
    $submittedMove = $submittedMoves[$moveKey] ?? null;

    if (!is_array($submittedMove)) {
        redirectToGeneratedSessionWithError('Every generated move must be marked Moved or Not Moved before completion.');
    }

    $outcome = is_string($submittedMove['outcome'] ?? null)
        ? $submittedMove['outcome']
        : '';
    $reasonCode = is_string($submittedMove['reason_code'] ?? null)
        ? trim($submittedMove['reason_code'])
        : '';
    $reasonNotes = is_string($submittedMove['reason_notes'] ?? null)
        ? trim($submittedMove['reason_notes'])
        : '';
    $destinationTrack = is_string($submittedMove['destination_track'] ?? null)
        ? trim($submittedMove['destination_track'])
        : '';

    if (!in_array($outcome, ['moved', 'not_moved'], true)) {
        redirectToGeneratedSessionWithError('Every generated move must be marked Moved or Not Moved before completion.');
    }

    if (switchCompletionTextLength($destinationTrack) > 50) {
        redirectToGeneratedSessionWithError('Destination Track entries must be 50 characters or fewer.');
    }

    if (switchCompletionTextLength($reasonNotes) > 250) {
        redirectToGeneratedSessionWithError('Exception notes must be 250 characters or fewer.');
    }

    if ($outcome === 'not_moved') {
        if (!in_array($reasonCode, $allowedReasonCodes, true)) {
            redirectToGeneratedSessionWithError('Choose a valid reason for every car marked Not Moved.');
        }

        if ($reasonCode === 'other' && $reasonNotes === '') {
            redirectToGeneratedSessionWithError('A short note is required when the Not Moved reason is Other.');
        }

        $destinationTrack = '';
        $skippedCount++;
    }
    else {
        $reasonCode = '';
        $reasonNotes = '';
        $movedCount++;
    }

    $completionResults[$moveKey] = [
        'outcome' => $outcome,
        'reason_code' => $reasonCode,
        'reason_notes' => $reasonNotes,
        'destination_track' => $destinationTrack
    ];
}

$equipmentIdList = array_values($equipmentIds);
$equipmentPlaceholders = implode(',', array_fill(0, count($equipmentIdList), '?'));
$equipmentParams = $equipmentIdList;
$equipmentParams[] = $railroadId;
$stmt = $pdo->prepare("
    SELECT
        id,
        current_industry_id,
        current_track,
        load_status,
        active
    FROM equipment
    WHERE id IN ($equipmentPlaceholders)
        AND railroad_id = ?
");
$stmt->execute($equipmentParams);
$authorizedEquipment = [];

foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $equipment) {
    $authorizedEquipment[(int)$equipment['id']] = $equipment;
}

if (count($authorizedEquipment) !== count($equipmentIdList)) {
    redirectToGeneratedSessionWithError('One or more cars no longer belong to this railroad. No cars were changed.');
}

$industryIdList = array_values($industryIds);
$industryPlaceholders = implode(',', array_fill(0, count($industryIdList), '?'));
$industryParams = $industryIdList;
$industryParams[] = $railroadId;
$stmt = $pdo->prepare("
    SELECT id
    FROM industries
    WHERE id IN ($industryPlaceholders)
        AND railroad_id = ?
");
$stmt->execute($industryParams);
$authorizedIndustryIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

if (count(array_unique($authorizedIndustryIds)) !== count($industryIdList)) {
    redirectToGeneratedSessionWithError('One or more switch-list industries no longer belong to this railroad. No cars were changed.');
}

foreach ($movesByKey as $move) {
    $equipmentId = (int)$move['equipment_id'];
    $equipment = $authorizedEquipment[$equipmentId];
    $expectedIndustryId = (int)$move['origin_industry_id'];
    $expectedTrack = (string)($move['origin_track'] ?? ($move['current_track'] ?? ''));
    $expectedLoadStatus = (string)($move['original_load_status'] ?? ($move['load_status'] ?? ''));

    if (
        (int)($equipment['active'] ?? 0) !== 1
        || (int)($equipment['current_industry_id'] ?? 0) !== $expectedIndustryId
        || (string)($equipment['current_track'] ?? '') !== $expectedTrack
        || (string)($equipment['load_status'] ?? '') !== $expectedLoadStatus
    ) {
        redirectToGeneratedSessionWithError('One or more cars changed after this session was generated. Generate a fresh switch list before completing work.');
    }
}

$createSessionsTableSql = "
    CREATE TABLE IF NOT EXISTS operation_switch_sessions (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        railroad_id INT NOT NULL,
        user_id INT NOT NULL,
        source_type VARCHAR(32) NOT NULL,
        source_key VARCHAR(64) NOT NULL,
        moved_count INT UNSIGNED NOT NULL DEFAULT 0,
        skipped_count INT UNSIGNED NOT NULL DEFAULT 0,
        completed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_operation_switch_source (railroad_id, source_type, source_key),
        KEY idx_operation_switch_railroad_completed (railroad_id, completed_at),
        KEY idx_operation_switch_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
";
$createMovesTableSql = "
    CREATE TABLE IF NOT EXISTS operation_switch_moves (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        switch_session_id BIGINT UNSIGNED NOT NULL,
        railroad_id INT NOT NULL,
        equipment_id INT NOT NULL,
        move_key VARCHAR(64) NOT NULL,
        outcome ENUM('moved', 'not_moved') NOT NULL,
        reason_code VARCHAR(64) NULL,
        reason_notes VARCHAR(255) NULL,
        old_industry_id INT NULL,
        new_industry_id INT NULL,
        old_track VARCHAR(255) NULL,
        new_track VARCHAR(255) NULL,
        old_load_status VARCHAR(64) NULL,
        new_load_status VARCHAR(64) NULL,
        completed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_operation_switch_move (switch_session_id, move_key),
        KEY idx_operation_switch_move_railroad (railroad_id),
        KEY idx_operation_switch_move_equipment (equipment_id),
        CONSTRAINT fk_operation_switch_moves_session
            FOREIGN KEY (switch_session_id)
            REFERENCES operation_switch_sessions (id)
            ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
";

try {
    $pdo->exec($createSessionsTableSql);
    $pdo->exec($createMovesTableSql);
}
catch (Throwable $exception) {
    error_log('Switch completion history setup failed: ' . $exception->getMessage());
    redirectToGeneratedSessionWithError('Switch completion history could not be prepared. No cars were changed.');
}

try {
    $pdo->beginTransaction();

    $lockEquipmentStmt = $pdo->prepare("
        SELECT
            id,
            current_industry_id,
            current_track,
            load_status,
            active
        FROM equipment
        WHERE id IN ($equipmentPlaceholders)
            AND railroad_id = ?
        FOR UPDATE
    ");
    $lockEquipmentStmt->execute($equipmentParams);
    $lockedEquipment = [];

    foreach ($lockEquipmentStmt->fetchAll(PDO::FETCH_ASSOC) as $equipment) {
        $lockedEquipment[(int)$equipment['id']] = $equipment;
    }

    if (count($lockedEquipment) !== count($equipmentIdList)) {
        throw new RuntimeException('Generated switch-list equipment changed before completion.');
    }

    $lockIndustryStmt = $pdo->prepare("
        SELECT id
        FROM industries
        WHERE id IN ($industryPlaceholders)
            AND railroad_id = ?
        FOR UPDATE
    ");
    $lockIndustryStmt->execute($industryParams);
    $lockedIndustryIds = array_map('intval', $lockIndustryStmt->fetchAll(PDO::FETCH_COLUMN));

    if (count(array_unique($lockedIndustryIds)) !== count($industryIdList)) {
        throw new RuntimeException('Generated switch-list industries changed before completion.');
    }

    foreach ($movesByKey as $move) {
        $equipmentId = (int)$move['equipment_id'];
        $equipment = $lockedEquipment[$equipmentId];
        $expectedIndustryId = (int)$move['origin_industry_id'];
        $expectedTrack = (string)($move['origin_track'] ?? ($move['current_track'] ?? ''));
        $expectedLoadStatus = (string)($move['original_load_status'] ?? ($move['load_status'] ?? ''));

        if (
            (int)($equipment['active'] ?? 0) !== 1
            || (int)($equipment['current_industry_id'] ?? 0) !== $expectedIndustryId
            || (string)($equipment['current_track'] ?? '') !== $expectedTrack
            || (string)($equipment['load_status'] ?? '') !== $expectedLoadStatus
        ) {
            throw new RuntimeException('Generated switch-list equipment became stale before completion.');
        }
    }

    $authorizedEquipment = $lockedEquipment;

    $stmt = $pdo->prepare("
        INSERT INTO operation_switch_sessions (
            railroad_id,
            user_id,
            source_type,
            source_key,
            moved_count,
            skipped_count,
            completed_at
        ) VALUES (?, ?, 'generated', ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $railroadId,
        $userId,
        $activeSessionId,
        $movedCount,
        $skippedCount
    ]);
    $switchSessionId = (int)$pdo->lastInsertId();

    $updateMovedEquipment = $pdo->prepare("
        UPDATE equipment
        SET
            current_industry_id = ?,
            current_track = ?,
            load_status = ?
        WHERE id = ?
            AND railroad_id = ?
    ");
    $insertMoveHistory = $pdo->prepare("
        INSERT INTO operation_switch_moves (
            switch_session_id,
            railroad_id,
            equipment_id,
            move_key,
            outcome,
            reason_code,
            reason_notes,
            old_industry_id,
            new_industry_id,
            old_track,
            new_track,
            old_load_status,
            new_load_status,
            completed_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");

    foreach ($movesByKey as $moveKey => $move) {
        $equipmentId = (int)$move['equipment_id'];
        $equipment = $authorizedEquipment[$equipmentId];
        $result = $completionResults[$moveKey];
        $oldIndustryId = $equipment['current_industry_id'] !== null
            ? (int)$equipment['current_industry_id']
            : null;
        $oldTrack = $equipment['current_track'];
        $oldLoadStatus = $equipment['load_status'];
        $newIndustryId = $oldIndustryId;
        $newTrack = $oldTrack;
        $newLoadStatus = $oldLoadStatus;

        if ($result['outcome'] === 'moved') {
            $newIndustryId = (int)$move['destination_industry_id'];
            $newTrack = $result['destination_track'];
            $plannedLoadStatus = (string)($move['load_status'] ?? '');
            $newLoadStatus = $plannedLoadStatus !== ''
                ? $plannedLoadStatus
                : $oldLoadStatus;

            $updateMovedEquipment->execute([
                $newIndustryId,
                $newTrack,
                $newLoadStatus,
                $equipmentId,
                $railroadId
            ]);
        }

        $insertMoveHistory->execute([
            $switchSessionId,
            $railroadId,
            $equipmentId,
            $moveKey,
            $result['outcome'],
            $result['reason_code'] !== '' ? $result['reason_code'] : null,
            $result['reason_notes'] !== '' ? $result['reason_notes'] : null,
            $oldIndustryId,
            $newIndustryId,
            $oldTrack,
            $newTrack,
            $oldLoadStatus,
            $newLoadStatus
        ]);
    }

    $pdo->commit();
}
catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Generated switch list completion failed: ' . $exception->getMessage());
    redirectToGeneratedSessionWithError('The switch list could not be completed. No cars were changed.');
}

unset(
    $_SESSION['generated_session'],
    $_SESSION['generated_session_id'],
    $_SESSION['generated_skip_counts'],
    $_SESSION['generated_skip_diagnostics']
);

$_SESSION['switch_completion_message'] = $movedCount . ' car'
    . ($movedCount === 1 ? '' : 's')
    . ' moved. '
    . $skippedCount . ' exception'
    . ($skippedCount === 1 ? '' : 's')
    . ' recorded.';
$_SESSION['switch_completion_clear_storage_key'] = 'tt_switch_progress_generated_' . $activeSessionId;
unset($_SESSION['switch_completion_csrf_token']);

header('Location: generate.php?switch_completed=1');
exit;

?>
