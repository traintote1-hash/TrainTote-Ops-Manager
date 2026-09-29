<?php

// Keep list and detail navigation on the same prepared filters and sort order.
function tt_equipment_list_query(array $query, int $railroadId): array
{
/*
|--------------------------------------------------------------------------
| Search
|--------------------------------------------------------------------------
*/

$search = trim(is_string($query['search'] ?? null) ? $query['search'] : '');

/*
|--------------------------------------------------------------------------
| Filters
|--------------------------------------------------------------------------
*/

$typeFilters         = $query['type']         ?? [];
$scaleFilters        = $query['scale']        ?? [];
$manufacturerFilters = $query['manufacturer'] ?? [];
$roadFilters         = $query['road_name']    ?? [];
$locationFilters     = $query['location']     ?? [];
$loadFilters         = $query['load_status']  ?? [];
$activeFilters       = $query['active']       ?? [];

// Sanitize: keep only non-empty strings
$typeFilters         = array_values(array_filter(array_map('strval', array_filter((array)$typeFilters, 'is_scalar'))));
$scaleFilters        = array_values(array_filter(array_map('strval', array_filter((array)$scaleFilters, 'is_scalar'))));
$manufacturerFilters = array_values(array_filter(array_map('strval', array_filter((array)$manufacturerFilters, 'is_scalar'))));
$roadFilters         = array_values(array_filter(array_map('strval', array_filter((array)$roadFilters, 'is_scalar'))));
$locationFilters     = array_values(array_filter(array_map('strval', array_filter((array)$locationFilters, 'is_scalar'))));
$loadFilters         = array_values(array_filter(array_map('strval', array_filter((array)$loadFilters, 'is_scalar'))));
$activeFilters       = array_values(array_filter(array_map('strval', array_filter((array)$activeFilters, 'is_scalar')), fn($v) => $v === '0' || $v === '1'));

/*
|--------------------------------------------------------------------------
| Sorting
|--------------------------------------------------------------------------
*/

$allowedSorts = [
    'reporting_marks',
    'road_number',
    'road_name',
    'created_at',
    'equipment_class',
    'equipment_type',
    'manufacturer',
    'operations_service',
    'load_status',
    'current_location',
    'current_track',
    'active'
];

$sort = $query['sort'] ?? 'reporting_marks';

if (!in_array($sort, $allowedSorts)) {
    $sort = 'reporting_marks';
}

$dir = strtolower(is_string($query['dir'] ?? null) ? $query['dir'] : 'asc');

if (!in_array($dir, ['asc', 'desc'])) {
    $dir = 'asc';
}

/*
|--------------------------------------------------------------------------
| Per Page
|--------------------------------------------------------------------------
*/

$page = max(1, (int)($query['page'] ?? 1));

$perPage = $query['per_page'] ?? 20;

if ($perPage !== 'all') {
    $perPage = (int)$perPage;
    if (!in_array($perPage, [10, 20, 50, 100])) {
        $perPage = 20;
    }
}

/*
|--------------------------------------------------------------------------
| Sort SQL
|--------------------------------------------------------------------------
*/

$orderBy = match ($sort) {
    'road_number'      => 'e.road_number',
    'road_name'        => 'e.road_name',
    'created_at'       => 'e.created_at',
    'equipment_class'  => 'e.equipment_class',
    'equipment_type'   => 'e.equipment_type',
    'manufacturer'     => 'e.manufacturer',
    'operations_service' => 'e.operations_service',
    'load_status'      => 'e.load_status',
    'current_location' => 'i.industry_name',
    'current_track'    => 'e.current_track',
    'active'           => 'e.active',
    default            => 'e.reporting_marks'
};

$orderBy .= ' ' . strtoupper($dir);

$where  = ["e.railroad_id = :railroad_id"];
$params = [':railroad_id' => $railroadId];

// Search
if ($search !== '') {
    $where[] = "(
        e.reporting_marks LIKE :search
        OR e.road_number   LIKE :search
        OR e.road_name     LIKE :search
        OR e.equipment_type LIKE :search
        OR e.operations_service LIKE :search
        OR e.prototype     LIKE :search
        OR i.industry_name LIKE :search
    )";
    $params[':search'] = '%' . $search . '%';
}

/*
|--------------------------------------------------------------------------
| Helper: build a safe IN() clause with individual named placeholders
|--------------------------------------------------------------------------
*/

if (!empty($typeFilters)) {
    $where[] = tt_equipment_in_clause('e.equipment_type', $typeFilters, 'type', $params);
}

if (!empty($scaleFilters)) {
    $where[] = tt_equipment_in_clause('e.scale', $scaleFilters, 'scale', $params);
}

if (!empty($manufacturerFilters)) {
    $where[] = tt_equipment_in_clause('e.manufacturer', $manufacturerFilters, 'mfr', $params);
}

if (!empty($roadFilters)) {
    $where[] = tt_equipment_in_clause('e.road_name', $roadFilters, 'road', $params);
}

if (!empty($locationFilters)) {
    $where[] = tt_equipment_in_clause('i.industry_name', $locationFilters, 'loc', $params);
}

if (!empty($loadFilters)) {
    $where[] = tt_equipment_in_clause('e.load_status', $loadFilters, 'load', $params);
}

if (!empty($activeFilters)) {
    $where[] = tt_equipment_in_clause('e.active', $activeFilters, 'active', $params);
}

$whereSQL = implode(' AND ', $where);
    $orderBy .= ', e.road_number ASC, e.id ASC';
    return compact('search', 'typeFilters', 'scaleFilters', 'manufacturerFilters',
        'roadFilters', 'locationFilters', 'loadFilters', 'activeFilters',
        'sort', 'dir', 'page', 'perPage', 'orderBy', 'whereSQL', 'params');
}

function tt_equipment_in_clause(string $column, array $values, string $prefix, array &$params): string
{
    $placeholders = [];
    foreach ($values as $i => $v) {
        $key           = ':' . $prefix . '_' . $i;
        $placeholders[]= $key;
        $params[$key]  = $v;
    }
    return $column . ' IN (' . implode(',', $placeholders) . ')';
}

function tt_equipment_list_context(): array
{
    $query = [];
    if (isset($_GET['list']) && is_string($_GET['list'])) {
        parse_str($_GET['list'], $query);
    }
    return array_intersect_key($query, array_flip([
        'search', 'type', 'scale', 'manufacturer', 'road_name', 'location',
        'load_status', 'active', 'sort', 'dir', 'page', 'per_page'
    ]));
}

function tt_equipment_context_suffix(array $query): string
{
    return $query ? '&' . http_build_query(['list' => http_build_query($query)]) : '';
}

function tt_equipment_list_url(array $query): string
{
    return 'list.php' . ($query ? '?' . http_build_query($query) : '');
}

function tt_equipment_navigation_ids(PDO $pdo, int $railroadId, array $query): array
{
    $settings = tt_equipment_list_query($query, $railroadId);
    $stmt = $pdo->prepare('SELECT e.id FROM equipment e
        LEFT JOIN industries i ON e.current_industry_id = i.id
        WHERE ' . $settings['whereSQL'] . ' ORDER BY ' . $settings['orderBy']);
    $stmt->execute($settings['params']);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}
