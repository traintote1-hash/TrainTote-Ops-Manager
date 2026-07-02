<?php
session_start();
require_once '../config/database.php';
require_once '../includes/jmri_import_helpers.php';
$railroad = ttJmriGetRailroad($pdo);
$type = $_GET['type'] ?? 'cars';

if (!in_array($type, ['cars', 'locomotives'], true)) {
    die('Invalid export type.');
}

$isCars = $type === 'cars';
$filename = $isCars ? 'traintote_jmri_cars.csv' : 'traintote_jmri_locomotives.csv';
$whereClass = $isCars ? "e.equipment_class <> 'Locomotive'" : "e.equipment_class = 'Locomotive'";

$stmt = $pdo->prepare("
    SELECT e.*, i.industry_name AS current_location
    FROM equipment e
    LEFT JOIN industries i ON e.current_industry_id = i.id
    WHERE e.railroad_id = :railroad_id
        AND $whereClass
    ORDER BY e.reporting_marks, e.road_number
");

$stmt->execute(['railroad_id' => $railroad['id']]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');
fputcsv($output, ['comma']);

foreach ($rows as $row) {
    if ($isCars) {
        fputcsv($output, [
            $row['road_number'] ?? '',
            $row['road_name'] ?: ($row['reporting_marks'] ?? ''),
            $row['equipment_type'] ?? '',
            $row['length_ft'] ?? '',
            '',
            $row['color'] ?? '',
            '',
            '',
            $row['current_location'] ?? '',
            '-',
            $row['current_track'] ?? ''
        ]);
    }
    else {
        fputcsv($output, [
            $row['road_number'] ?? '',
            $row['road_name'] ?: ($row['reporting_marks'] ?? ''),
            $row['prototype'] ?: ($row['equipment_type'] ?? ''),
            $row['length_ft'] ?? '',
            '',
            '',
            $row['current_location'] ?? '',
            '-',
            $row['current_track'] ?? ''
        ]);
    }
}

fclose($output);
exit;
