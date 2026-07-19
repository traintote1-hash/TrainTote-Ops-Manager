<?php

function ttSaveSessionYardmasterName(PDO $pdo, int $railroadId, int $sessionId, int $userId, string $yardmasterName): void
{
    ttOperationsRequireRailroadOwner($pdo, $railroadId, $userId);
    $yardmasterName = substr(trim($yardmasterName), 0, 120);
    $pdo->beginTransaction();
    try {
        $sessionStmt = $pdo->prepare("SELECT status,yardmaster_name FROM operating_sessions WHERE id=? AND railroad_id=? AND status IN('draft','ready','in_progress') FOR UPDATE");
        $sessionStmt->execute([$sessionId, $railroadId]);
        $session = $sessionStmt->fetch(PDO::FETCH_ASSOC);
        if (!$session) throw new RuntimeException('The Yardmaster can only change on a current operating session.');
        $previous = trim((string)$session['yardmaster_name']);
        $pdo->prepare("UPDATE operating_sessions SET yardmaster_name=NULLIF(?,'') WHERE id=? AND railroad_id=?")->execute([$yardmasterName, $sessionId, $railroadId]);
        if ($previous !== $yardmasterName) {
            $event = $yardmasterName === '' ? 'role_cleared' : 'role_assigned';
            $detail = $yardmasterName === '' ? 'Yardmaster name cleared.' : 'Yardmaster assigned to '.$yardmasterName.'.';
            $pdo->prepare('INSERT INTO operation_yard_history(railroad_id,session_id,event_type,detail,created_by_user_id) VALUES(?,?,?,?,?)')
                ->execute([$railroadId, $sessionId, $event, $detail, $userId]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}
