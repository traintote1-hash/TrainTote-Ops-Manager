<?php

function ttDispatcherStatuses(): array
{
    return ['not_started' => 'Not Started', 'working' => 'Working', 'delayed' => 'Delayed', 'complete' => 'Complete'];
}
function ttDispatcherEffectiveEnabled(array $railroad, array $session): bool
{
    return $session['dispatcher_enabled'] === null
        ? !empty($railroad['operations_dispatcher_enabled'])
        : (bool)$session['dispatcher_enabled'];
}

function ttDispatcherAccessRailroad(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare("SELECT r.*,IF(r.user_id=?,'owner',orr.role) operations_role
        FROM railroads r
        LEFT JOIN operation_railroad_roles orr ON orr.railroad_id=r.id AND orr.user_id=? AND orr.role='dispatcher'
        WHERE r.user_id=? OR orr.user_id=? ORDER BY (r.user_id=?) DESC LIMIT 1");
    $stmt->execute([$userId, $userId, $userId, $userId, $userId]);
    $railroad = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$railroad) {
        http_response_code(403);
        throw new RuntimeException('Dispatcher access is not assigned for this railroad.');
    }
    return $railroad;
}

function ttDispatcherRequireAccess(array $railroad): void
{
    if (!in_array((string)($railroad['operations_role'] ?? ''), ['owner', 'dispatcher'], true)) {
        http_response_code(403);
        throw new RuntimeException('Owner or dispatcher access is required.');
    }
}

function ttDispatcherStatus(array $assignment): string
{
    if ((string)($assignment['assignment_status'] ?? $assignment['status'] ?? '') === 'completed') return 'complete';
    $status = (string)($assignment['dispatcher_status'] ?? 'not_started');
    return array_key_exists($status, ttDispatcherStatuses()) && $status !== 'complete' ? $status : 'not_started';
}

function ttDispatcherSession(PDO $pdo, int $sessionId, int $railroadId): array
{
    $stmt = $pdo->prepare('SELECT * FROM operating_sessions WHERE id=? AND railroad_id=? LIMIT 1');
    $stmt->execute([$sessionId, $railroadId]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$session) { http_response_code(404); throw new RuntimeException('Operating session not found.'); }
    return $session;
}

function ttDispatcherAssignments(PDO $pdo, int $sessionId, int $railroadId): array
{
    $sql = "SELECT a.id,a.assignment_number,a.unit_identifier,a.title_snapshot,a.operating_pattern,a.crew_name,a.engineer_name,a.conductor_name,a.brakeman_names,
        a.status assignment_status,a.dispatcher_status,a.dispatcher_note,a.dispatcher_crew_message,a.dispatcher_updated_at,
        (SELECT GROUP_CONCAT(CONCAT_WS(' ',e.reporting_marks,e.road_number) ORDER BY al.position SEPARATOR ', ')
         FROM operation_assignment_locomotives al JOIN equipment e ON e.id=al.equipment_id AND e.railroad_id=a.railroad_id WHERE al.assignment_id=a.id) locomotives,
        COALESCE((SELECT sl.planned_move_count FROM operation_switch_lists sl WHERE sl.assignment_id=a.id AND sl.railroad_id=a.railroad_id AND sl.status NOT IN('cancelled','superseded') ORDER BY sl.revision_number DESC LIMIT 1),0) planned_moves,
        COALESCE((SELECT COUNT(*) FROM operation_switch_list_moves m JOIN operation_switch_lists sl ON sl.id=m.switch_list_id AND sl.railroad_id=m.railroad_id WHERE sl.assignment_id=a.id AND sl.status NOT IN('cancelled','superseded') AND m.equipment_id IS NOT NULL AND m.progress_complete=1),0) completed_moves,
        COALESCE((SELECT COUNT(*) FROM operation_switch_list_moves m JOIN operation_switch_lists sl ON sl.id=m.switch_list_id AND sl.railroad_id=m.railroad_id WHERE sl.assignment_id=a.id AND sl.status NOT IN('cancelled','superseded') AND (m.actual_outcome='not_moved' OR (m.actual_industry_id IS NOT NULL AND m.actual_industry_id<>m.destination_industry_id) OR (m.actual_track IS NOT NULL AND m.actual_track<>m.destination_track))),0) exception_count,
        GREATEST(a.updated_at,COALESCE(a.dispatcher_updated_at,a.updated_at),COALESCE((SELECT MAX(sl.updated_at) FROM operation_switch_lists sl WHERE sl.assignment_id=a.id AND sl.railroad_id=a.railroad_id),a.updated_at),COALESCE((SELECT MAX(m.progress_updated_at) FROM operation_switch_list_moves m JOIN operation_switch_lists sl ON sl.id=m.switch_list_id AND sl.railroad_id=m.railroad_id WHERE sl.assignment_id=a.id),a.updated_at)) last_activity
        FROM operation_assignments a WHERE a.session_id=? AND a.railroad_id=? ORDER BY a.sequence_number";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$sessionId, $railroadId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$row) {
        $row['operating_status'] = ttDispatcherStatus($row);
        unset($row['dispatcher_status']);
    }
    unset($row);
    return $rows;
}

function ttDispatcherUpdateAssignment(PDO $pdo, int $assignmentId, int $sessionId, int $railroadId, int $userId, array $input): void
{
    $status = (string)($input['operating_status'] ?? 'not_started');
    if (!in_array($status, ['not_started', 'working', 'delayed'], true)) throw new RuntimeException('Choose a valid dispatcher status. Complete is controlled by work-order completion.');
    $note = substr(trim((string)($input['dispatcher_note'] ?? '')), 0, 5000);
    $message = substr(trim((string)($input['crew_message'] ?? '')), 0, 255);
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("SELECT a.status assignment_status,a.dispatcher_crew_message,s.status session_status FROM operation_assignments a JOIN operating_sessions s ON s.id=a.session_id AND s.railroad_id=a.railroad_id WHERE a.id=? AND a.session_id=? AND a.railroad_id=? FOR UPDATE");
    $stmt->execute([$assignmentId, $sessionId, $railroadId]);
    $state = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$state || $state['session_status'] !== 'in_progress') throw new RuntimeException('Dispatcher updates are allowed only during an Active session.');
    if ($state['assignment_status'] === 'completed') throw new RuntimeException('Completed assignments are lifecycle-controlled and read-only.');
    if (!ttOperationsModuleEnabled($pdo, $railroadId, 'crew_messaging')) $message = (string)$state['dispatcher_crew_message'];
    $stmt = $pdo->prepare('UPDATE operation_assignments SET dispatcher_status=?,dispatcher_note=NULLIF(?,\'\'),dispatcher_crew_message=NULLIF(?,\'\'),dispatcher_updated_at=NOW(),dispatcher_updated_by_user_id=? WHERE id=? AND session_id=? AND railroad_id=?');
    $stmt->execute([$status, $note, $message, $userId, $assignmentId, $sessionId, $railroadId]);
    $pdo->commit();
}
