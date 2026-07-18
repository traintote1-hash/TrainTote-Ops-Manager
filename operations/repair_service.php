<?php

function ttRepairStatuses(): array
{
    return [
        'awaiting_repair' => 'Awaiting Repair',
        'in_repair' => 'In Repair',
        'ready_for_service' => 'Ready for Service',
        'closed' => 'Closed / Returned to Service',
    ];
}

function ttRepairStatusLabel(string $status): string
{
    return ttRepairStatuses()[$status] ?? 'Not recorded';
}

function ttEnsureBadOrderRepair(PDO $pdo, int $railroadId, int $equipmentId, int $moveId, ?string $notes, int $userId): int
{
    $open = $pdo->prepare("SELECT * FROM operation_repairs WHERE railroad_id=? AND equipment_id=? AND status<>'closed' FOR UPDATE");
    $open->execute([$railroadId, $equipmentId]);
    $repair = $open->fetch(PDO::FETCH_ASSOC);

    if ($repair) {
        $repairId = (int)$repair['id'];
        $pdo->prepare("INSERT IGNORE INTO operation_repair_history(repair_id,railroad_id,event_type,new_status,note,source_move_id,created_by_user_id) VALUES(?,?,'incident',?,?,?,?)")
            ->execute([$repairId, $railroadId, (string)$repair['status'], $notes, $moveId, $userId]);
        if (!(int)$repair['service_state_applied']) {
            $equipment = $pdo->prepare('SELECT active FROM equipment WHERE id=? AND railroad_id=? FOR UPDATE');
            $equipment->execute([$equipmentId, $railroadId]);
            $activeBefore = $equipment->fetchColumn();
            if ($activeBefore === false) {
                throw new RuntimeException('Bad Order equipment no longer belongs to this railroad.');
            }
            $pdo->prepare('UPDATE operation_repairs SET equipment_active_before=?,service_state_applied=1 WHERE id=? AND railroad_id=?')
                ->execute([(int)$activeBefore, $repairId, $railroadId]);
        }
    } else {
        $equipment = $pdo->prepare('SELECT active FROM equipment WHERE id=? AND railroad_id=? FOR UPDATE');
        $equipment->execute([$equipmentId, $railroadId]);
        $activeBefore = $equipment->fetchColumn();
        if ($activeBefore === false) {
            throw new RuntimeException('Bad Order equipment no longer belongs to this railroad.');
        }
        $insert = $pdo->prepare("INSERT INTO operation_repairs(railroad_id,equipment_id,source_move_id,status,reason_code,original_notes,reported_at,created_by_user_id,equipment_active_before,service_state_applied) VALUES(?,?,?,'awaiting_repair','bad_order',?,NOW(),?,?,1)");
        $insert->execute([$railroadId, $equipmentId, $moveId, $notes, $userId, (int)$activeBefore]);
        $repairId = (int)$pdo->lastInsertId();
        $pdo->prepare("INSERT INTO operation_repair_history(repair_id,railroad_id,event_type,new_status,note,source_move_id,created_by_user_id) VALUES(?,?,'reported','awaiting_repair',?,?,?)")
            ->execute([$repairId, $railroadId, $notes, $moveId, $userId]);
    }

    $pdo->prepare('UPDATE equipment SET active=0 WHERE id=? AND railroad_id=?')->execute([$equipmentId, $railroadId]);
    return $repairId;
}

function ttUpdateRepair(PDO $pdo, int $repairId, int $railroadId, int $userId, string $newStatus, string $note): void
{
    if (!array_key_exists($newStatus, ttRepairStatuses())) {
        throw new RuntimeException('Choose a valid repair status.');
    }
    $note = substr(trim($note), 0, 5000);
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT * FROM operation_repairs WHERE id=? AND railroad_id=? FOR UPDATE');
        $stmt->execute([$repairId, $railroadId]);
        $repair = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$repair) {
            throw new RuntimeException('Repair record not found.');
        }
        if ($repair['status'] === 'closed') {
            throw new RuntimeException('Closed repair history is read-only.');
        }
        if ($newStatus === $repair['status'] && $note === '') {
            throw new RuntimeException('Add a repair note or choose a different status.');
        }

        $closedAt = $newStatus === 'closed' ? 'NOW()' : 'NULL';
        $pdo->prepare("UPDATE operation_repairs SET status=?,closed_at=$closedAt WHERE id=? AND railroad_id=?")
            ->execute([$newStatus, $repairId, $railroadId]);
        $eventType = $newStatus === $repair['status'] ? 'note' : 'status_change';
        $pdo->prepare('INSERT INTO operation_repair_history(repair_id,railroad_id,event_type,previous_status,new_status,note,created_by_user_id) VALUES(?,?,?,?,?,?,?)')
            ->execute([$repairId, $railroadId, $eventType, $repair['status'], $newStatus, $note !== '' ? $note : null, $userId]);

        if ($newStatus === 'closed' && (int)$repair['service_state_applied'] && (int)$repair['equipment_active_before']) {
            $pdo->prepare('UPDATE equipment SET active=1 WHERE id=? AND railroad_id=?')
                ->execute([(int)$repair['equipment_id'], $railroadId]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}
