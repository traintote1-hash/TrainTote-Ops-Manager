<?php
require_once __DIR__ . '/../equipment/list_context.php';

function navigation_expect($actual, $expected, string $message): void
{
    if ($actual !== $expected) {
        throw new RuntimeException($message . ': ' . json_encode($actual));
    }
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE industries (id INTEGER, industry_name TEXT)');
$pdo->exec('CREATE TABLE equipment (id INTEGER, railroad_id INTEGER,
    reporting_marks TEXT, road_number TEXT, road_name TEXT, equipment_type TEXT,
    operations_service TEXT, prototype TEXT, scale TEXT, manufacturer TEXT,
    load_status TEXT, active INTEGER, current_industry_id INTEGER)');
$pdo->exec("INSERT INTO industries VALUES (1, 'Yard')");
$pdo->exec("INSERT INTO equipment VALUES
    (1, 7, 'A', '10', 'Road', 'Diesel', '', '', 'HO', 'Atlas', 'Empty', 1, 1),
    (2, 7, 'A', '20', 'Road', 'Boxcar', '', '', 'HO', 'Atlas', 'Empty', 1, 1),
    (3, 7, 'B', '30', 'Road', 'Diesel', '', '', 'HO', 'Atlas', 'Empty', 1, 1),
    (4, 8, 'C', '40', 'Road', 'Diesel', '', '', 'HO', 'Atlas', 'Empty', 1, 1),
    (5, 7, 'C', '50', 'Road', 'Diesel', '', '', 'N', 'Atlas', 'Loaded', 0, 1)");

$query = ['type' => ['Diesel'], 'sort' => 'road_number', 'dir' => 'desc',
    'page' => 2, 'per_page' => 10];
$ids = fn(array $q): array => array_map('intval', tt_equipment_navigation_ids($pdo, 7, $q));
navigation_expect($ids($query), [5, 3, 1], 'Next/previous must follow filtered sort across pages');
navigation_expect($ids($query + ['scale' => ['HO'], 'active' => ['1']]), [3, 1], 'Combined filters');
navigation_expect($ids(['active' => ['0']]), [5], 'Inactive filter must retain zero');
navigation_expect($ids(['search' => 'Yard', 'type' => ['Boxcar']]), [2], 'Search with location join');
navigation_expect($ids([]), [1, 2, 3, 5], 'Unfiltered navigation is railroad scoped');

parse_str(ltrim(tt_equipment_context_suffix($query), '&'), $_GET);
navigation_expect(tt_equipment_list_context(), array_replace($query, ['page' => '2', 'per_page' => '10']), 'Context must round-trip through detail links');
navigation_expect(tt_equipment_list_url(tt_equipment_list_context()), 'list.php?' . http_build_query($query), 'Return URL preserves page and filters');
$_GET = ['list' => 'type[]=Diesel&redirect=https://example.com'];
navigation_expect(tt_equipment_list_context(), ['type' => ['Diesel']], 'Only list settings are accepted');
navigation_expect($ids(['type' => [['bad'], 'Diesel']]), [1, 3, 5], 'Malformed nested filters are ignored');

$pdo->exec("UPDATE equipment SET equipment_type = 'Steam' WHERE id = 3");
navigation_expect($ids($query), [5, 1], 'Navigation refreshes matches after an edit');
echo "Equipment navigation tests passed.\n";
