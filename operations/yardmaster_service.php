<?php

function ttYardmasterIndustrySql(string $alias = 'i'): string
{
    return "LOWER(TRIM($alias.industry_type)) IN ('yard','classification yard','terminal')";
}

function ttYardmasterAccessRailroad(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare("SELECT DISTINCT r.*,IF(r.user_id=?,'owner','yardmaster') operations_role
        FROM railroads r
        LEFT JOIN operation_session_roles osr ON osr.railroad_id=r.id AND osr.user_id=? AND osr.role='yardmaster'
        LEFT JOIN operating_sessions assigned_session ON assigned_session.id=osr.session_id AND assigned_session.railroad_id=r.id AND assigned_session.status='in_progress'
        WHERE r.user_id=? OR assigned_session.id IS NOT NULL
        ORDER BY (r.user_id=?) DESC LIMIT 1");
    $stmt->execute([$userId, $userId, $userId, $userId]);
    $railroad = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$railroad) {
        http_response_code(403);
        throw new RuntimeException('Yardmaster access is not assigned for an Active session.');
    }
    return $railroad;
}

function ttYardmasterRequireAccess(PDO $pdo, int $railroadId, int $sessionId, int $userId): void
{
    if (ttOperationsIsRailroadOwner($pdo, $railroadId, $userId)) return;
    if (!ttOperationsModuleEnabled($pdo, $railroadId, 'advanced_roles')) {
        http_response_code(403);
        throw new RuntimeException('Advanced Roles must be enabled for delegated Yardmaster access.');
    }
    $stmt = $pdo->prepare("SELECT 1 FROM operation_session_roles osr
        JOIN operating_sessions s ON s.id=osr.session_id AND s.railroad_id=osr.railroad_id
        WHERE osr.session_id=? AND osr.railroad_id=? AND osr.user_id=? AND osr.role='yardmaster' AND s.status='in_progress' LIMIT 1");
    $stmt->execute([$sessionId, $railroadId, $userId]);
    if (!$stmt->fetchColumn()) {
        http_response_code(403);
        throw new RuntimeException('Owner or assigned Yardmaster access is required for this session.');
    }
}

function ttYardmasterSessions(PDO $pdo, int $railroadId, int $userId): array
{
    $owner = ttOperationsIsRailroadOwner($pdo, $railroadId, $userId);
    $sql = "SELECT s.*,u.email yardmaster_email FROM operating_sessions s
        LEFT JOIN operation_session_roles osr ON osr.session_id=s.id AND osr.railroad_id=s.railroad_id AND osr.role='yardmaster'
        LEFT JOIN users u ON u.id=osr.user_id
        WHERE s.railroad_id=? AND s.status='in_progress'";
    $params = [$railroadId];
    if (!$owner) { $sql .= ' AND osr.user_id=?'; $params[] = $userId; }
    $sql .= ' ORDER BY s.started_at DESC,s.id DESC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function ttYardmasterAssignRole(PDO $pdo, int $sessionId, int $railroadId, int $ownerUserId, string $email): void
{
    ttOperationsRequireRailroadOwner($pdo, $railroadId, $ownerUserId);
    $email = trim($email);
    $pdo->beginTransaction();
    try {
        $session = $pdo->prepare("SELECT status FROM operating_sessions WHERE id=? AND railroad_id=? AND status IN('draft','ready','in_progress') FOR UPDATE");
        $session->execute([$sessionId, $railroadId]);
        if (!$session->fetchColumn()) throw new RuntimeException('Yardmaster roles can only be assigned to a current session.');
        $existing = $pdo->prepare("SELECT user_id FROM operation_session_roles WHERE session_id=? AND railroad_id=? AND role='yardmaster' FOR UPDATE");
        $existing->execute([$sessionId, $railroadId]);
        $previousUserId = (int)$existing->fetchColumn();
        if ($email === '') {
            $pdo->prepare("DELETE FROM operation_session_roles WHERE session_id=? AND railroad_id=? AND role='yardmaster'")->execute([$sessionId, $railroadId]);
            if ($previousUserId) $pdo->prepare("INSERT INTO operation_yard_history(railroad_id,session_id,event_type,detail,created_by_user_id) VALUES(?,?,'role_cleared',?,?)")
                ->execute([$railroadId, $sessionId, 'Yardmaster assignment cleared.', $ownerUserId]);
            $pdo->commit();
            return;
        }
        $user = $pdo->prepare('SELECT id FROM users WHERE LOWER(email)=LOWER(?) LIMIT 1');
        $user->execute([$email]);
        $assignedUserId = (int)$user->fetchColumn();
        if (!$assignedUserId) throw new RuntimeException('No TrainTote account matches that email address.');
        $pdo->prepare("INSERT IGNORE INTO operation_railroad_roles(railroad_id,user_id,role) VALUES(?,?,'yardmaster')")
            ->execute([$railroadId, $assignedUserId]);
        $pdo->prepare("INSERT INTO operation_session_roles(session_id,railroad_id,user_id,role,assigned_by_user_id) VALUES(?,?,?,'yardmaster',?) ON DUPLICATE KEY UPDATE user_id=VALUES(user_id),assigned_by_user_id=VALUES(assigned_by_user_id),assigned_at=NOW()")
            ->execute([$sessionId, $railroadId, $assignedUserId, $ownerUserId]);
        if ($previousUserId !== $assignedUserId) $pdo->prepare("INSERT INTO operation_yard_history(railroad_id,session_id,event_type,detail,created_by_user_id) VALUES(?,?,'role_assigned',?,?)")
            ->execute([$railroadId, $sessionId, 'Yardmaster assigned to '.$email.'.', $ownerUserId]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function ttYardmasterSaveAssignment(PDO $pdo, int $railroadId, int $sessionId, int $userId, array $input): void
{
    $equipmentId = (int)($input['equipment_id'] ?? 0);
    $yardIndustryId = (int)($input['yard_industry_id'] ?? 0);
    $track = substr(trim((string)($input['planned_track'] ?? '')), 0, 120);
    $group = substr(trim((string)($input['classification_group'] ?? '')), 0, 120);
    $notes = substr(trim((string)($input['notes'] ?? '')), 0, 255);
    if ($equipmentId <= 0 || $yardIndustryId <= 0) throw new RuntimeException('Choose a car and a yard track.');
    $pdo->beginTransaction();
    try {
        $session = $pdo->prepare("SELECT status FROM operating_sessions WHERE id=? AND railroad_id=? FOR UPDATE");
        $session->execute([$sessionId, $railroadId]);
        if ($session->fetchColumn() !== 'in_progress') throw new RuntimeException('Yard planning is allowed only during an Active session.');
        ttYardmasterRequireAccess($pdo, $railroadId, $sessionId, $userId);
        $car = $pdo->prepare("SELECT id FROM equipment WHERE id=? AND railroad_id=? AND COALESCE(equipment_class,'')<>'Locomotive'");
        $car->execute([$equipmentId, $railroadId]);
        if (!$car->fetchColumn()) throw new RuntimeException('Choose a car belonging to this railroad.');
        $yard = $pdo->prepare('SELECT id FROM industries i WHERE id=? AND railroad_id=? AND active=1 AND '.ttYardmasterIndustrySql('i'));
        $yard->execute([$yardIndustryId, $railroadId]);
        if (!$yard->fetchColumn()) throw new RuntimeException('Choose an active yard, classification-yard, or terminal track.');
        $old = $pdo->prepare('SELECT id,yard_industry_id FROM operation_yard_assignments WHERE session_id=? AND railroad_id=? AND equipment_id=? FOR UPDATE');
        $old->execute([$sessionId, $railroadId, $equipmentId]);
        $previous = $old->fetch(PDO::FETCH_ASSOC);
        $pdo->prepare("INSERT INTO operation_yard_assignments(railroad_id,session_id,equipment_id,yard_industry_id,planned_track,classification_group,notes,created_by_user_id,updated_by_user_id) VALUES(?,?,?,?,NULLIF(?,''),NULLIF(?,''),NULLIF(?,''),?,?) ON DUPLICATE KEY UPDATE yard_industry_id=VALUES(yard_industry_id),planned_track=VALUES(planned_track),classification_group=VALUES(classification_group),notes=VALUES(notes),updated_by_user_id=VALUES(updated_by_user_id)")
            ->execute([$railroadId, $sessionId, $equipmentId, $yardIndustryId, $track, $group, $notes, $userId, $userId]);
        $event = $previous ? 'moved' : 'assigned';
        $from = $previous ? (int)$previous['yard_industry_id'] : null;
        $pdo->prepare("INSERT INTO operation_yard_history(railroad_id,session_id,equipment_id,event_type,from_yard_industry_id,to_yard_industry_id,detail,created_by_user_id) VALUES(?,?,?,?,?,?,?,?)")
            ->execute([$railroadId, $sessionId, $equipmentId, $event, $from, $yardIndustryId, $group !== '' ? $group : null, $userId]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function ttYardmasterClearAssignment(PDO $pdo, int $railroadId, int $sessionId, int $userId, int $assignmentId): void
{
    $pdo->beginTransaction();
    try {
        $session = $pdo->prepare("SELECT status FROM operating_sessions WHERE id=? AND railroad_id=? FOR UPDATE");
        $session->execute([$sessionId, $railroadId]);
        if ($session->fetchColumn() !== 'in_progress') throw new RuntimeException('Yard planning is allowed only during an Active session.');
        ttYardmasterRequireAccess($pdo, $railroadId, $sessionId, $userId);
        $stmt = $pdo->prepare('SELECT equipment_id,yard_industry_id FROM operation_yard_assignments WHERE id=? AND session_id=? AND railroad_id=? FOR UPDATE');
        $stmt->execute([$assignmentId, $sessionId, $railroadId]);
        $assignment = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$assignment) throw new RuntimeException('Planned yard assignment was not found.');
        $pdo->prepare('DELETE FROM operation_yard_assignments WHERE id=? AND session_id=? AND railroad_id=?')->execute([$assignmentId, $sessionId, $railroadId]);
        $pdo->prepare("INSERT INTO operation_yard_history(railroad_id,session_id,equipment_id,event_type,from_yard_industry_id,detail,created_by_user_id) VALUES(?,?,?,'cleared',?,?,?)")
            ->execute([$railroadId, $sessionId, (int)$assignment['equipment_id'], (int)$assignment['yard_industry_id'], 'Planned assignment cleared.', $userId]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function ttYardmasterForecast(int $physicalCars, int $incomingPlans, int $capacity): array
{
    $forecast = $physicalCars + $incomingPlans;
    return ['forecast' => $forecast, 'available' => $capacity > 0 ? $capacity - $physicalCars : null, 'over_capacity' => $capacity > 0 && $forecast > $capacity];
}

function ttYardmasterOverview(PDO $pdo, int $railroadId, int $sessionId): array
{
    $yardSql = ttYardmasterIndustrySql('i');
    $trackStmt = $pdo->prepare("SELECT i.id,i.industry_name,i.location,i.track_capacity,
        COUNT(DISTINCT e.id) physical_cars,
        COUNT(DISTINCT CASE WHEN planned_equipment.current_industry_id<>i.id OR planned_equipment.current_industry_id IS NULL THEN ya.equipment_id END) incoming_plans
        FROM industries i
        LEFT JOIN equipment e ON e.railroad_id=i.railroad_id AND e.current_industry_id=i.id AND COALESCE(e.equipment_class,'')<>'Locomotive'
        LEFT JOIN operation_yard_assignments ya ON ya.railroad_id=i.railroad_id AND ya.session_id=? AND ya.yard_industry_id=i.id
        LEFT JOIN equipment planned_equipment ON planned_equipment.id=ya.equipment_id AND planned_equipment.railroad_id=ya.railroad_id
        WHERE i.railroad_id=? AND i.active=1 AND $yardSql
        GROUP BY i.id ORDER BY i.location,i.industry_name");
    $trackStmt->execute([$sessionId, $railroadId]);
    $tracks = $trackStmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($tracks as &$track) $track += ttYardmasterForecast((int)$track['physical_cars'], (int)$track['incoming_plans'], (int)$track['track_capacity']);
    unset($track);

    $carsStmt = $pdo->prepare("SELECT e.id,e.reporting_marks,e.road_number,e.current_industry_id,e.current_track,e.active,
        i.industry_name,EXISTS(SELECT 1 FROM operation_repairs r WHERE r.railroad_id=e.railroad_id AND r.equipment_id=e.id AND r.status<>'closed') in_repair
        FROM equipment e JOIN industries i ON i.id=e.current_industry_id AND i.railroad_id=e.railroad_id
        WHERE e.railroad_id=? AND ".ttYardmasterIndustrySql('i')." AND COALESCE(e.equipment_class,'')<>'Locomotive'
        ORDER BY i.location,i.industry_name,e.reporting_marks,e.road_number");
    $carsStmt->execute([$railroadId]);
    $trackCars = [];
    foreach ($carsStmt->fetchAll(PDO::FETCH_ASSOC) as $car) $trackCars[(int)$car['current_industry_id']][] = $car;

    $assignmentStmt = $pdo->prepare("SELECT ya.*,e.reporting_marks,e.road_number,e.active,e.current_industry_id,e.current_track,
        current_i.industry_name current_location,yard_i.industry_name yard_name,
        EXISTS(SELECT 1 FROM operation_repairs r WHERE r.railroad_id=ya.railroad_id AND r.equipment_id=ya.equipment_id AND r.status<>'closed') in_repair,
        (SELECT COUNT(*) FROM operation_yard_assignments duplicate_ya WHERE duplicate_ya.session_id=ya.session_id AND duplicate_ya.equipment_id=ya.equipment_id) duplicate_count
        FROM operation_yard_assignments ya
        JOIN equipment e ON e.id=ya.equipment_id AND e.railroad_id=ya.railroad_id
        JOIN industries yard_i ON yard_i.id=ya.yard_industry_id AND yard_i.railroad_id=ya.railroad_id
        LEFT JOIN industries current_i ON current_i.id=e.current_industry_id AND current_i.railroad_id=e.railroad_id
        WHERE ya.session_id=? AND ya.railroad_id=? ORDER BY yard_i.industry_name,e.reporting_marks,e.road_number");
    $assignmentStmt->execute([$sessionId, $railroadId]);
    $assignments = $assignmentStmt->fetchAll(PDO::FETCH_ASSOC);

    $candidateStmt = $pdo->prepare("SELECT e.id,e.reporting_marks,e.road_number,e.active,e.current_track,i.industry_name current_location,
        EXISTS(SELECT 1 FROM operation_repairs r WHERE r.railroad_id=e.railroad_id AND r.equipment_id=e.id AND r.status<>'closed') in_repair
        FROM equipment e LEFT JOIN industries i ON i.id=e.current_industry_id AND i.railroad_id=e.railroad_id
        WHERE e.railroad_id=? AND COALESCE(e.equipment_class,'')<>'Locomotive' ORDER BY e.active DESC,e.reporting_marks,e.road_number");
    $candidateStmt->execute([$railroadId]);

    $moveBase = " FROM operation_switch_list_moves m
        JOIN operation_switch_lists sl ON sl.id=m.switch_list_id AND sl.railroad_id=m.railroad_id
        JOIN operation_assignments a ON a.id=sl.assignment_id AND a.railroad_id=sl.railroad_id
        JOIN equipment e ON e.id=m.equipment_id AND e.railroad_id=m.railroad_id
        LEFT JOIN industries origin_i ON origin_i.id=m.origin_industry_id AND origin_i.railroad_id=m.railroad_id
        LEFT JOIN industries destination_i ON destination_i.id=m.destination_industry_id AND destination_i.railroad_id=m.railroad_id
        WHERE sl.session_id=? AND sl.railroad_id=? AND sl.status IN('approved','in_progress','needs_review') AND m.actual_outcome='pending'";
    $inbound = $pdo->prepare("SELECT m.id,e.reporting_marks,e.road_number,origin_i.industry_name origin_name,destination_i.industry_name destination_name,a.title_snapshot,sl.switch_list_number".$moveBase." AND m.destination_industry_id IS NOT NULL AND ".ttYardmasterIndustrySql('destination_i')." AND (e.current_industry_id<>m.destination_industry_id OR e.current_industry_id IS NULL) ORDER BY destination_i.industry_name,a.sequence_number,m.sequence_number");
    $inbound->execute([$sessionId, $railroadId]);
    $outbound = $pdo->prepare("SELECT m.id,e.reporting_marks,e.road_number,origin_i.industry_name origin_name,destination_i.industry_name destination_name,a.title_snapshot,sl.switch_list_number".$moveBase." AND m.origin_industry_id IS NOT NULL AND ".ttYardmasterIndustrySql('origin_i')." AND m.destination_industry_id<>m.origin_industry_id ORDER BY a.title_snapshot,destination_i.industry_name,m.sequence_number");
    $outbound->execute([$sessionId, $railroadId]);

    return ['tracks'=>$tracks,'track_cars'=>$trackCars,'assignments'=>$assignments,'candidates'=>$candidateStmt->fetchAll(PDO::FETCH_ASSOC),'inbound'=>$inbound->fetchAll(PDO::FETCH_ASSOC),'outbound'=>$outbound->fetchAll(PDO::FETCH_ASSOC)];
}
