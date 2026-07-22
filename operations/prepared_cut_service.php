<?php

function ttPreparedCutIsEditable(array $cut): bool
{
    return ($cut['status'] ?? '') === 'ready';
}

function ttPreparedCutCarIds($submitted): array
{
    $raw = array_values(array_filter(array_map('intval', (array)$submitted), static fn(int $id): bool => $id > 0));
    if (count($raw) !== count(array_unique($raw))) {
        throw new RuntimeException('A car can only appear once in a prepared cut.');
    }
    if (!$raw) {
        throw new RuntimeException('At least one car required.');
    }
    return $raw;
}

function ttPreparedCutValidateJob(PDO $pdo, int $jobId, int $railroadId): ?int
{
    if ($jobId <= 0) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT id FROM jobs WHERE id=? AND railroad_id=?');
    $stmt->execute([$jobId, $railroadId]);
    if (!$stmt->fetchColumn()) {
        throw new RuntimeException('Invalid intended Job Title.');
    }
    return $jobId;
}

function ttPreparedCutReplaceCars(PDO $pdo, int $cutId, int $railroadId, int $industryId, array $carIds): void
{
    $reserved = ttReservedEquipmentIds($pdo, $railroadId);
    $verify = $pdo->prepare("SELECT e.id FROM equipment e WHERE e.id=? AND e.railroad_id=? AND e.active=1 AND e.current_industry_id=? AND e.equipment_class IN ('Freight Car','Passenger Car','MOW') AND NOT EXISTS (SELECT 1 FROM prepared_cut_cars pc JOIN prepared_cuts c ON c.id=pc.prepared_cut_id WHERE pc.equipment_id=e.id AND pc.prepared_cut_id<>? AND c.railroad_id=? AND c.status IN('ready','assigned','in_use')) FOR UPDATE");
    foreach ($carIds as $carId) {
        if (in_array($carId, $reserved, true)) {
            throw new RuntimeException('A selected car is reserved by an active assignment.');
        }
        $verify->execute([$carId, $railroadId, $industryId, $cutId, $railroadId]);
        if (!$verify->fetchColumn()) {
            throw new RuntimeException('Every selected car must belong to this railroad and remain active, eligible, unreserved, and at the cut location.');
        }
    }

    $pdo->prepare('DELETE pc FROM prepared_cut_cars pc JOIN prepared_cuts c ON c.id=pc.prepared_cut_id WHERE pc.prepared_cut_id=? AND c.railroad_id=?')->execute([$cutId, $railroadId]);
    $insert = $pdo->prepare('INSERT INTO prepared_cut_cars (prepared_cut_id,equipment_id,position) VALUES (?,?,?)');
    foreach (array_values($carIds) as $position => $carId) {
        $insert->execute([$cutId, $carId, $position + 1]);
    }
}
