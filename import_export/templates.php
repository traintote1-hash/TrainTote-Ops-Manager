<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$type = $_GET['type'] ?? 'cars';

if (!in_array($type, ['cars', 'locomotives'], true)) {
    die('Invalid template type.');
}

$isCars = $type === 'cars';
$filename = $isCars ? 'jmri_car_import_template.csv' : 'jmri_locomotive_import_template.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');
fputcsv($output, ['comma']);

if ($isCars) {
    fputcsv($output, ['Car Number', 'Car Road Name', 'Car Type', 'Car Length', 'Car Weight', 'Car Color', 'Owner Name', 'Date Built', 'Location', '-', 'Track Name']);
    fputcsv($output, ['12345', 'TTX', 'Boxcar', '50', '263000', 'Yellow', 'TrainTote', '1978', 'Yard', '-', 'Track 1']);
}
else {
    fputcsv($output, ['Locomotive Number', 'Locomotive Road Name', 'Locomotive Model', 'Locomotive Length', 'Owner Name', 'Date Built', 'Location', '-', 'Track Name']);
    fputcsv($output, ['2900', 'KCS', 'GP40-2', '59', 'TrainTote', '1978', 'Yard', '-', 'Engine Track']);
}

fclose($output);
exit;
