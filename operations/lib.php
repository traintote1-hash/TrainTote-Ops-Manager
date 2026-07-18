<?php

function ttOperationsRailroad(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare('SELECT id, name, operations_dispatcher_enabled FROM railroads WHERE user_id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $railroad = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$railroad) {
        throw new RuntimeException('No railroad found.');
    }
    return $railroad;
}

function ttOperationsCsrfToken(): string
{
    if (!isset($_SESSION['operations_csrf']) || !is_string($_SESSION['operations_csrf'])) {
        $_SESSION['operations_csrf'] = bin2hex(random_bytes(24));
    }
    return $_SESSION['operations_csrf'];
}

function ttOperationsRequireCsrf(): void
{
    $submitted = isset($_POST['csrf_token']) && is_string($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    if ($submitted === '' || !hash_equals(ttOperationsCsrfToken(), $submitted)) {
        http_response_code(403);
        throw new RuntimeException('The form expired. Refresh the page and try again.');
    }
}

function ttOperationsRequireRailroadOwner(PDO $pdo, int $railroadId, int $userId): void
{
    // The current application has no implemented delegated management-role model.
    // Until one exists, the railroads.user_id relationship is the owner authority.
    if (!ttOperationsIsRailroadOwner($pdo, $railroadId, $userId)) {
        http_response_code(403);
        throw new RuntimeException('Only the railroad owner may update persistent load status.');
    }
}

function ttOperationsIsRailroadOwner(PDO $pdo, int $railroadId, int $userId): bool
{
    $stmt = $pdo->prepare('SELECT id FROM railroads WHERE id=? AND user_id=? LIMIT 1');
    $stmt->execute([$railroadId, $userId]);
    return (bool)$stmt->fetchColumn();
}

function ttDispatcherNavEnabled(PDO $pdo, int $userId): bool
{
    $stmt = $pdo->prepare("SELECT 1 FROM railroads r
        LEFT JOIN operation_railroad_roles orr ON orr.railroad_id=r.id AND orr.user_id=? AND orr.role='dispatcher'
        JOIN operating_sessions s ON s.railroad_id=r.id AND s.status='in_progress'
        WHERE (r.user_id=? OR orr.user_id=?)
          AND COALESCE(s.dispatcher_enabled,r.operations_dispatcher_enabled)=1 LIMIT 1");
    $stmt->execute([$userId, $userId, $userId]);
    return (bool)$stmt->fetchColumn();
}

function ttAssignmentSuffix(int $sequence): string
{
    $suffix = '';
    while ($sequence > 0) {
        $sequence--;
        $suffix = chr(65 + ($sequence % 26)) . $suffix;
        $sequence = intdiv($sequence, 26);
    }
    return $suffix;
}

function ttNextScopedNumber(PDO $pdo, string $table, string $column, int $railroadId, string $prefix, int $width): string
{
    $allowed = [
        'operating_sessions.session_number',
        'prepared_cuts.cut_number'
    ];
    if (!in_array($table . '.' . $column, $allowed, true)) {
        throw new InvalidArgumentException('Unsupported number sequence.');
    }
    $stmt = $pdo->prepare("SELECT $column FROM $table WHERE railroad_id = ? ORDER BY id DESC LIMIT 1 FOR UPDATE");
    $stmt->execute([$railroadId]);
    $last = (string)$stmt->fetchColumn();
    $next = $last !== '' ? ((int)preg_replace('/\D/', '', $last) + 1) : 1;
    return $prefix . str_pad((string)$next, $width, '0', STR_PAD_LEFT);
}

function ttJobTypes(): array
{
    return [
        'local_turn' => 'Local Turn', 'point_to_point_local' => 'Point-to-Point Local',
        'yard_job' => 'Yard Job', 'transfer' => 'Transfer', 'interchange_job' => 'Interchange Job',
        'manifest' => 'Manifest / Through Freight', 'industry_switcher' => 'Industry Switcher',
        'light_engine' => 'Light Engine / Hostler', 'custom' => 'Custom'
    ];
}

function ttActiveAssignmentStatuses(): array
{
    return ['draft', 'ready', 'waiting', 'in_progress', 'needs_review'];
}

function ttOperationsStatusLabel(string $status, string $recordType = ''): string
{
    if ($status === 'in_progress') { return 'Active'; }
    if ($recordType === 'switch_list' && $status === 'draft') { return 'Generated'; }
    return ucwords(str_replace('_', ' ', $status));
}

function ttNormalizeService($value): string
{
    return strtolower(trim(preg_replace('/\s+/', ' ', (string)$value)));
}

function ttServiceList($value): array
{
    $result = [];
    foreach (preg_split('/[\r\n,]+/', (string)$value) as $item) {
        $item = ttNormalizeService($item);
        if ($item !== '') { $result[$item] = true; }
    }
    return array_keys($result);
}

function ttIndustrySupports(array $industry, string $field, string $service): bool
{
    $service = ttNormalizeService($service);
    foreach (ttServiceList($industry[$field] ?? '') as $supported) {
        if (in_array($supported, ['all', 'any', '*', 'all / any service'], true) || $supported === $service) {
            return true;
        }
    }
    return false;
}

function ttPhotoUrl(?string $filename): ?string
{
    $filename = trim((string)$filename);
    return $filename === '' ? null : '/uploads/' . rawurlencode(basename($filename));
}

function ttReservedEquipmentIds(PDO $pdo, int $railroadId, ?int $excludeAssignmentId = null): array
{
    $params = [$railroadId];
    $exclude = '';
    if ($excludeAssignmentId !== null) { $exclude = ' AND a.id <> ?'; $params[] = $excludeAssignmentId; }
    $sql = "SELECT equipment_id FROM operation_assignment_locomotives al JOIN operation_assignments a ON a.id=al.assignment_id WHERE a.railroad_id=? AND a.status IN ('draft','ready','waiting','in_progress','needs_review')$exclude
            UNION SELECT equipment_id FROM operation_assignment_starting_cars ac JOIN operation_assignments a ON a.id=ac.assignment_id WHERE a.railroad_id=? AND a.status IN ('draft','ready','waiting','in_progress','needs_review')";
    $second = [$railroadId];
    if ($excludeAssignmentId !== null) { $sql .= ' AND a.id <> ?'; $second[] = $excludeAssignmentId; }
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge($params, $second));
    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

function ttHtml($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
