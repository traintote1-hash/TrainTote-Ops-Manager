<?php

session_start();

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

require_once __DIR__ . '/config/database.php';

function ttDemoTableExists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare(
        'SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1'
    );
    $stmt->execute([$table]);
    return (bool)$stmt->fetchColumn();
}

function ttDemoColumns(PDO $pdo, string $table): array
{
    static $cache = [];
    if (isset($cache[$table])) {
        return $cache[$table];
    }

    if (!ttDemoTableExists($pdo, $table)) {
        return $cache[$table] = [];
    }

    $stmt = $pdo->prepare(
        'SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ?'
    );
    $stmt->execute([$table]);
    return $cache[$table] = array_fill_keys($stmt->fetchAll(PDO::FETCH_COLUMN), true);
}

function ttDemoHasColumn(PDO $pdo, string $table, string $column): bool
{
    $columns = ttDemoColumns($pdo, $table);
    return isset($columns[$column]);
}

function ttDemoInsert(PDO $pdo, string $table, array $values): int
{
    $columns = ttDemoColumns($pdo, $table);
    $filtered = [];
    foreach ($values as $column => $value) {
        if (isset($columns[$column])) {
            $filtered[$column] = $value;
        }
    }
    if (!$filtered) {
        throw new RuntimeException('No compatible columns for ' . $table . '.');
    }

    $names = array_keys($filtered);
    $quoted = array_map(static fn($column) => '`' . str_replace('`', '``', $column) . '`', $names);
    $params = array_map(static fn($column) => ':' . $column, $names);
    $stmt = $pdo->prepare(
        'INSERT INTO `' . str_replace('`', '``', $table) . '` (' . implode(',', $quoted) . ') VALUES (' . implode(',', $params) . ')'
    );
    $stmt->execute($filtered);
    return (int)$pdo->lastInsertId();
}

function ttDemoCopyRow(PDO $pdo, string $table, array $row, array $overrides = [], array $exclude = ['id']): int
{
    $values = $row;
    foreach ($exclude as $column) {
        unset($values[$column]);
    }
    foreach ($overrides as $column => $value) {
        $values[$column] = $value;
    }
    return ttDemoInsert($pdo, $table, $values);
}

function ttDemoSeedRows(PDO $pdo, string $table, ?int $seedRailroadId = null): array
{
    if (!ttDemoTableExists($pdo, $table)) {
        return [];
    }

    $orderSql = ttDemoHasColumn($pdo, $table, 'id') ? ' ORDER BY id' : '';

    if (ttDemoHasColumn($pdo, $table, 'seed_data')) {
        $stmt = $pdo->query("SELECT * FROM `$table` WHERE seed_data = 1" . $orderSql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    if ($seedRailroadId !== null && ttDemoHasColumn($pdo, $table, 'railroad_id')) {
        $stmt = $pdo->prepare("SELECT * FROM `$table` WHERE railroad_id = ?" . $orderSql);
        $stmt->execute([$seedRailroadId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    return [];
}

function ttDemoCopyByRailroad(
    PDO $pdo,
    string $table,
    int $seedRailroadId,
    int $newRailroadId,
    array $mapColumns = [],
    array $exclude = ['id']
): array {
    $idMap = [];
    foreach (ttDemoSeedRows($pdo, $table, $seedRailroadId) as $row) {
        $overrides = ['railroad_id' => $newRailroadId];
        foreach ($mapColumns as $column => $map) {
            if (array_key_exists($column, $row)) {
                $overrides[$column] = $row[$column] === null ? null : ($map[(int)$row[$column]] ?? null);
            }
        }
        if (ttDemoHasColumn($pdo, $table, 'seed_data')) {
            $overrides['seed_data'] = 0;
        }
        $idMap[(int)$row['id']] = ttDemoCopyRow($pdo, $table, $row, $overrides, $exclude);
    }
    return $idMap;
}

function ttDemoCopyJoinRows(
    PDO $pdo,
    string $table,
    string $parentColumn,
    array $parentMap,
    array $mapColumns = []
): void {
    if (!ttDemoTableExists($pdo, $table) || !$parentMap) {
        return;
    }

    $placeholders = implode(',', array_fill(0, count($parentMap), '?'));
    $stmt = $pdo->prepare("SELECT * FROM `$table` WHERE `$parentColumn` IN ($placeholders)");
    $stmt->execute(array_keys($parentMap));

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $overrides = [$parentColumn => $parentMap[(int)$row[$parentColumn]]];
        foreach ($mapColumns as $column => $map) {
            if (array_key_exists($column, $row)) {
                $overrides[$column] = $row[$column] === null ? null : ($map[(int)$row[$column]] ?? null);
            }
        }
        ttDemoCopyRow($pdo, $table, $row, $overrides, ['id']);
    }
}

function ttDemoCopyRailroadRows(PDO $pdo, string $table, int $seedRailroadId, int $newRailroadId): void
{
    foreach (ttDemoSeedRows($pdo, $table, $seedRailroadId) as $row) {
        ttDemoCopyRow($pdo, $table, $row, ['railroad_id' => $newRailroadId], ['id']);
    }
}

try {
    $pdo->beginTransaction();

    $email = 'demo_' . bin2hex(random_bytes(8)) . '@demo.traintote.com';
    $newUserId = ttDemoInsert($pdo, 'users', [
        'first_name' => 'Demo',
        'last_name' => 'User',
        'email' => $email,
        'password_hash' => '',
        'demo_user' => 1,
        'expires_at' => gmdate('Y-m-d H:i:s', time() + 4 * 60 * 60),
    ]);

    $railroadWhere = ttDemoHasColumn($pdo, 'railroads', 'seed_data') ? 'seed_data = 1' : 'user_id = 0';
    $stmt = $pdo->query("SELECT * FROM railroads WHERE $railroadWhere LIMIT 1");
    $seedRailroad = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$seedRailroad) {
        throw new RuntimeException('No demo seed railroad is configured.');
    }

    $seedRailroadId = (int)$seedRailroad['id'];
    $newRailroadId = ttDemoCopyRow($pdo, 'railroads', $seedRailroad, [
        'user_id' => $newUserId,
        'is_default' => 1,
        'seed_data' => 0,
    ]);

    $industryMap = ttDemoCopyByRailroad($pdo, 'industries', $seedRailroadId, $newRailroadId);
    $equipmentMap = ttDemoCopyByRailroad($pdo, 'equipment', $seedRailroadId, $newRailroadId, [
        'current_industry_id' => $industryMap,
    ]);
    $jobMap = ttDemoCopyByRailroad($pdo, 'jobs', $seedRailroadId, $newRailroadId);

    ttDemoCopyJoinRows($pdo, 'job_industries', 'job_id', $jobMap, ['industry_id' => $industryMap]);
    ttDemoCopyJoinRows($pdo, 'job_locomotives', 'job_id', $jobMap, ['equipment_id' => $equipmentMap]);
    ttDemoCopyJoinRows($pdo, 'job_cars', 'job_id', $jobMap, ['equipment_id' => $equipmentMap]);
    ttDemoCopyJoinRows($pdo, 'job_operation_profiles', 'job_id', $jobMap);
    ttDemoCopyJoinRows($pdo, 'job_route_stops', 'job_id', $jobMap, [
        'industry_id' => $industryMap,
        'pull_destination_industry_id' => $industryMap,
        'replacement_source_industry_id' => $industryMap,
    ]);

    $waybillMap = ttDemoCopyByRailroad($pdo, 'waybills', $seedRailroadId, $newRailroadId, [
        'equipment_id' => $equipmentMap,
        'origin_industry_id' => $industryMap,
        'destination_industry_id' => $industryMap,
    ]);
    ttDemoCopyJoinRows($pdo, 'waybill_cycles', 'waybill_id', $waybillMap, [
        'origin_industry_id' => $industryMap,
        'destination_industry_id' => $industryMap,
    ]);

    $cutMap = ttDemoCopyByRailroad($pdo, 'prepared_cuts', $seedRailroadId, $newRailroadId, [
        'current_industry_id' => $industryMap,
        'intended_job_template_id' => $jobMap,
    ]);
    ttDemoCopyJoinRows($pdo, 'prepared_cut_cars', 'prepared_cut_id', $cutMap, ['equipment_id' => $equipmentMap]);

    ttDemoCopyRailroadRows($pdo, 'operation_module_settings', $seedRailroadId, $newRailroadId);

    $pdo->commit();

    session_regenerate_id(true);
    $_SESSION['user_id'] = $newUserId;
    $_SESSION['first_name'] = 'Demo';

    header('Location: dashboard.php');
    exit;
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo 'Unable to start the demo. Please try again later.';
    error_log('TrainTote demo launch failed: ' . $exception->getMessage());
}
