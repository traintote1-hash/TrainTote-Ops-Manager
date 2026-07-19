<?php

function ttSaveSessionCrewAssignments(PDO $pdo, int $railroadId, int $sessionId, int $userId, array $input, bool $yardmasterEnabled): void
{
    ttOperationsRequireRailroadOwner($pdo, $railroadId, $userId);
    $yardmasterName = substr(trim((string)($input['yardmaster_name'] ?? '')), 0, 120);
    $rows = is_array($input['crew_assignments'] ?? null) ? $input['crew_assignments'] : [];
    $pdo->beginTransaction();
    try {
        $sessionStmt = $pdo->prepare("SELECT status,yardmaster_name FROM operating_sessions WHERE id=? AND railroad_id=? AND status IN('draft','ready','in_progress') FOR UPDATE");
        $sessionStmt->execute([$sessionId, $railroadId]);
        $session = $sessionStmt->fetch(PDO::FETCH_ASSOC);
        if (!$session) throw new RuntimeException('Crew assignments can only change on a current operating session.');
        if ($yardmasterEnabled) {
            $previous = trim((string)$session['yardmaster_name']);
            $pdo->prepare("UPDATE operating_sessions SET yardmaster_name=NULLIF(?,'') WHERE id=? AND railroad_id=?")->execute([$yardmasterName, $sessionId, $railroadId]);
            if ($previous !== $yardmasterName) {
                $event = $yardmasterName === '' ? 'role_cleared' : 'role_assigned';
                $detail = $yardmasterName === '' ? 'Yardmaster name cleared.' : 'Yardmaster assigned to '.$yardmasterName.'.';
                $pdo->prepare('INSERT INTO operation_yard_history(railroad_id,session_id,event_type,detail,created_by_user_id) VALUES(?,?,?,?,?)')
                    ->execute([$railroadId, $sessionId, $event, $detail, $userId]);
            }
        }
        $lock = $pdo->prepare("SELECT id FROM operation_assignments WHERE id=? AND session_id=? AND railroad_id=? AND status IN('draft','ready','waiting','in_progress','needs_review') FOR UPDATE");
        $update = $pdo->prepare("UPDATE operation_assignments SET crew_name=NULLIF(?,''),engineer_name=NULLIF(?,''),conductor_name=NULLIF(?,''),brakeman_names=NULLIF(?,'') WHERE id=? AND session_id=? AND railroad_id=?");
        foreach ($rows as $assignmentId=>$row) {
            $assignmentId = (int)$assignmentId;
            if ($assignmentId <= 0 || !is_array($row)) continue;
            $lock->execute([$assignmentId, $sessionId, $railroadId]);
            if (!$lock->fetchColumn()) throw new RuntimeException('A crew assignment no longer belongs to this current session.');
            $engineer = substr(trim((string)($row['engineer_name'] ?? '')), 0, 120);
            $conductor = substr(trim((string)($row['conductor_name'] ?? '')), 0, 120);
            $brakemen = substr(trim((string)($row['brakeman_names'] ?? '')), 0, 255);
            $summary = ttOperationsCrewSummary($engineer,$conductor,$brakemen);
            $update->execute([$summary, $engineer, $conductor, $brakemen, $assignmentId, $sessionId, $railroadId]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}
