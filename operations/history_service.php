<?php

function ttHistoryExceptionReasons(): array
{
    return [
        'track_blocked' => 'Track blocked',
        'car_inaccessible' => 'Car inaccessible',
        'no_capacity' => 'No capacity / track full',
        'bad_order' => 'Bad order',
        'wrong_car' => 'Wrong car',
        'customer_not_ready' => 'Customer not ready',
        'locomotive_issue' => 'Locomotive issue',
        'crew_issue' => 'Crew issue',
        'order_changed' => 'Order changed',
        'other' => 'Other',
        'moved_different_location' => 'Not separately recorded',
    ];
}

function ttHistoryRecordedValue($value, string $fallback = 'Not recorded'): string
{
    $value = trim((string)$value);
    return $value === '' ? $fallback : $value;
}

function ttHistoryOutcomeLabel(array $move): string
{
    if (($move['actual_outcome'] ?? '') === 'not_moved') { return 'Not moved'; }
    if (($move['actual_outcome'] ?? '') === 'moved') {
        return ($move['exception_reason_code'] ?? '') === 'moved_different_location'
            ? 'Moved to different location'
            : 'Moved as planned';
    }
    return 'Not recorded';
}

function ttHistoryExceptionType(array $move): string
{
    if (($move['actual_outcome'] ?? '') === 'not_moved') { return 'Not Moved'; }
    if (($move['exception_reason_code'] ?? '') === 'moved_different_location') { return 'Moved to Different Location'; }
    return '—';
}

function ttHistoryExceptionReason(array $move): string
{
    $code = trim((string)($move['exception_reason_code'] ?? ''));
    if ($code === '') { return '—'; }
    return ttHistoryExceptionReasons()[$code] ?? ucwords(str_replace('_', ' ', $code));
}

function ttHistoryMoveCounts(array $moves): array
{
    $counts = ['completed' => 0, 'exceptions' => 0];
    foreach ($moves as $move) {
        if (empty($move['equipment_id'])) { continue; }
        if (($move['actual_outcome'] ?? '') === 'moved') { $counts['completed']++; }
        if (trim((string)($move['exception_reason_code'] ?? '')) !== '') { $counts['exceptions']++; }
    }
    return $counts;
}

function ttLoadOperationsHistory(PDO $pdo, int $railroadId): array
{
    $sql = "SELECT s.*,
        (SELECT COUNT(DISTINCT sl.assignment_id)
           FROM operation_switch_lists sl
          WHERE sl.session_id=s.id AND sl.railroad_id=s.railroad_id
            AND sl.status IN('completed','cancelled')) work_order_count,
        (SELECT COUNT(*)
           FROM operation_switch_list_moves m
           JOIN operation_switch_lists sl ON sl.id=m.switch_list_id AND sl.railroad_id=m.railroad_id
          WHERE sl.session_id=s.id AND sl.railroad_id=s.railroad_id
            AND sl.status='completed' AND m.actual_outcome='moved') completed_move_count,
        (SELECT COUNT(*)
           FROM operation_switch_list_moves m
           JOIN operation_switch_lists sl ON sl.id=m.switch_list_id AND sl.railroad_id=m.railroad_id
          WHERE sl.session_id=s.id AND sl.railroad_id=s.railroad_id
            AND sl.status='completed' AND NULLIF(TRIM(m.exception_reason_code),'') IS NOT NULL) exception_count,
        (SELECT GROUP_CONCAT(DISTINCT NULLIF(TRIM(a.crew_name),'') ORDER BY a.sequence_number SEPARATOR ', ')
           FROM operation_assignments a
          WHERE a.session_id=s.id AND a.railroad_id=s.railroad_id) crews
      FROM operating_sessions s
     WHERE s.railroad_id=? AND s.status IN('completed','cancelled')
     ORDER BY s.operating_date DESC,s.id DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$railroadId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function ttLoadOperationsHistorySession(PDO $pdo, int $sessionId, int $railroadId): ?array
{
    $stmt = $pdo->prepare("SELECT * FROM operating_sessions WHERE id=? AND railroad_id=? AND status IN('completed','cancelled')");
    $stmt->execute([$sessionId, $railroadId]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);
    return $session ?: null;
}
