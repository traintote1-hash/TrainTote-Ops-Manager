<?php

function ttJmriNormalizeText($value): string
{
    return strtolower(trim(preg_replace('/\s+/', ' ', (string)$value)));
}

function ttJmriGetRailroad(PDO $pdo): array
{
    if (!isset($_SESSION['user_id'])) {
        header('Location: ../login.php');
        exit;
    }

    $stmt = $pdo->prepare("SELECT id FROM railroads WHERE user_id = :user_id LIMIT 1");
    $stmt->execute(['user_id' => $_SESSION['user_id']]);
    $railroad = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$railroad) {
        die('No railroad found.');
    }

    return $railroad;
}

function ttJmriCleanValue($value, int $limit = 255): string
{
    return substr(trim((string)$value), 0, $limit);
}

function ttJmriReportingMarks(string $road): string
{
    $road = trim($road);

    if (preg_match('/^[A-Za-z0-9]{2,6}$/', $road)) {
        return substr(strtoupper($road), 0, 20);
    }

    $parts = preg_split('/\s+/', $road);
    $first = $parts[0] ?? $road;

    return substr(strtoupper($first), 0, 20);
}

function ttJmriInferLocomotiveType(string $model): string
{
    $modelText = ttJmriNormalizeText($model);

    if (str_contains($modelText, 'steam') || preg_match('/^(\d+-\d+-\d+|\d+-\d+)$/', $modelText)) {
        return 'Steam';
    }

    if (str_contains($modelText, 'electric') || str_starts_with($modelText, 'gg1') || str_starts_with($modelText, 'e60')) {
        return 'Electric';
    }

    return 'Diesel';
}

function ttJmriReadImportRows(array $file): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload failed.');
    }

    if (($file['size'] ?? 0) > 1024 * 1024 * 2) {
        throw new RuntimeException('Upload is too large. Maximum size is 2 MB.');
    }

    $extension = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));

    if (!in_array($extension, ['csv', 'txt'], true)) {
        throw new RuntimeException('Only .csv and .txt files are accepted.');
    }

    $contents = file_get_contents($file['tmp_name']);

    if ($contents === false || trim($contents) === '') {
        throw new RuntimeException('Uploaded file is empty.');
    }

    $contents = str_replace(["\r\n", "\r"], "\n", $contents);
    $lines = array_values(array_filter(explode("\n", $contents), fn($line) => trim($line) !== ''));
    $commaMarker = isset($lines[0]) && strtolower(trim($lines[0])) === 'comma';
    $isComma = $commaMarker || $extension === 'csv';

    if ($commaMarker) {
        array_shift($lines);
    }

    $rows = [];

    foreach ($lines as $line) {
        if ($isComma) {
            $parsed = str_getcsv($line);
        }
        else {
            $parsed = preg_split('/\s+/', trim($line));
        }

        $parsed = array_map(fn($value) => trim((string)$value), $parsed ?: []);

        if (!empty($parsed)) {
            $rows[] = $parsed;
        }
    }

    return $rows;
}

function ttJmriLoadIndustries(PDO $pdo, int $railroadId): array
{
    $stmt = $pdo->prepare("SELECT id, industry_name, location FROM industries WHERE railroad_id = :railroad_id");
    $stmt->execute(['railroad_id' => $railroadId]);
    $industries = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $lookup = [];

    foreach ($industries as $industry) {
        foreach ([$industry['industry_name'] ?? '', $industry['location'] ?? ''] as $name) {
            $key = ttJmriNormalizeText($name);

            if ($key !== '' && !isset($lookup[$key])) {
                $lookup[$key] = (int)$industry['id'];
            }
        }
    }

    return $lookup;
}

function ttJmriFindIndustryId(array $industryLookup, string $location): ?int
{
    $key = ttJmriNormalizeText($location);

    return $key !== '' && isset($industryLookup[$key])
        ? $industryLookup[$key]
        : null;
}

function ttJmriFindDuplicate(PDO $pdo, int $railroadId, string $reportingMarks, string $roadNumber, string $equipmentClass): ?int
{
    $stmt = $pdo->prepare("
        SELECT id
        FROM equipment
        WHERE railroad_id = :railroad_id
            AND UPPER(reporting_marks) = UPPER(:reporting_marks)
            AND road_number = :road_number
            AND equipment_class = :equipment_class
        LIMIT 1
    ");

    $stmt->execute([
        'railroad_id' => $railroadId,
        'reporting_marks' => $reportingMarks,
        'road_number' => $roadNumber,
        'equipment_class' => $equipmentClass
    ]);

    $id = $stmt->fetchColumn();

    return $id ? (int)$id : null;
}

function ttJmriBuildNotes(array $parts): string
{
    $notes = ['Imported from JMRI'];

    foreach ($parts as $label => $value) {
        $value = trim((string)$value);

        if ($value !== '') {
            $notes[] = $label . ': ' . $value;
        }
    }

    return implode("\n", $notes);
}

function ttJmriMapRow(array $rawRow, string $importType, int $rowNumber, array $industryLookup, ?int $duplicateId = null): array
{
    $isCars = $importType === 'cars';
    $minimumColumns = $isCars ? 6 : 4;
    $errors = [];
    $warnings = [];

    if (count($rawRow) < $minimumColumns) {
        $errors[] = 'Missing required columns.';
    }

    $number = ttJmriCleanValue($rawRow[0] ?? '', 20);
    $road = ttJmriCleanValue($rawRow[1] ?? '', 100);
    $typeOrModel = ttJmriCleanValue($rawRow[2] ?? '', 100);
    $length = ttJmriCleanValue($rawRow[3] ?? '', 3);

    if ($number === '') {
        $errors[] = 'Missing number.';
    }

    if ($road === '') {
        $errors[] = 'Missing road name.';
    }

    if ($typeOrModel === '') {
        $errors[] = $isCars ? 'Missing car type.' : 'Missing locomotive model.';
    }

    $owner = '';
    $dateBuilt = '';
    $weight = '';
    $color = '';
    $location = '';
    $track = '';

    if ($isCars) {
        $weight = ttJmriCleanValue($rawRow[4] ?? '', 50);
        $color = ttJmriCleanValue($rawRow[5] ?? '', 20);
        $owner = ttJmriCleanValue($rawRow[6] ?? '', 100);
        $dateBuilt = ttJmriCleanValue($rawRow[7] ?? '', 50);
        $location = ttJmriCleanValue($rawRow[8] ?? '', 100);
        $track = (($rawRow[9] ?? '') === '-')
            ? ttJmriCleanValue($rawRow[10] ?? '', 50)
            : ttJmriCleanValue($rawRow[9] ?? '', 50);
    }
    else {
        $owner = ttJmriCleanValue($rawRow[4] ?? '', 100);
        $dateBuilt = ttJmriCleanValue($rawRow[5] ?? '', 50);
        $location = ttJmriCleanValue($rawRow[6] ?? '', 100);
        $track = (($rawRow[7] ?? '') === '-')
            ? ttJmriCleanValue($rawRow[8] ?? '', 50)
            : ttJmriCleanValue($rawRow[7] ?? '', 50);
    }

    $currentIndustryId = ttJmriFindIndustryId($industryLookup, $location);

    if ($location !== '' && $currentIndustryId === null) {
        $warnings[] = 'Location not matched.';
    }

    $reportingMarks = ttJmriReportingMarks($road);
    $equipmentClass = $isCars ? 'Freight Car' : 'Locomotive';
    $equipmentType = $isCars ? ttJmriCleanValue($typeOrModel, 30) : ttJmriInferLocomotiveType($typeOrModel);
    $notes = ttJmriBuildNotes([
        'Owner' => $owner,
        'Date Built' => $dateBuilt,
        'JMRI Weight' => $weight,
        'JMRI Location' => $currentIndustryId === null ? $location : '',
        'JMRI Track' => $currentIndustryId === null ? $track : ''
    ]);

    $status = 'ready';

    if (!empty($errors)) {
        $status = 'error';
    }
    elseif ($duplicateId !== null) {
        $status = 'duplicate';
    }
    elseif (!empty($warnings)) {
        $status = 'warning';
    }

    return [
        'row_number' => $rowNumber,
        'raw' => $rawRow,
        'import_type' => $importType,
        'reporting_marks' => $reportingMarks,
        'road_number' => $number,
        'road_name' => $road,
        'equipment_class' => $equipmentClass,
        'equipment_type' => $equipmentType,
        'prototype' => $isCars ? '' : ttJmriCleanValue($typeOrModel, 50),
        'manufacturer' => '',
        'length_ft' => $length,
        'color' => $isCars ? $color : '',
        'scale' => 'HO',
        'service' => '',
        'operations_service' => '',
        'load_status' => $isCars ? 'Empty' : '',
        'current_industry_id' => $currentIndustryId,
        'current_track' => $track,
        'notes' => $notes,
        'owner' => $owner,
        'date_built' => $dateBuilt,
        'weight' => $weight,
        'location' => $location,
        'duplicate_id' => $duplicateId,
        'status' => $status,
        'messages' => array_merge($errors, $warnings)
    ];
}

function ttJmriInsertEquipment(PDO $pdo, int $railroadId, array $row): void
{
    $stmt = $pdo->prepare("
        INSERT INTO equipment (
            railroad_id,
            reporting_marks,
            road_number,
            road_name,
            equipment_class,
            equipment_type,
            prototype,
            manufacturer,
            length_ft,
            color,
            scale,
            service,
            operations_service,
            load_status,
            current_industry_id,
            current_track,
            notes
        )
        VALUES (
            :railroad_id,
            :reporting_marks,
            :road_number,
            :road_name,
            :equipment_class,
            :equipment_type,
            :prototype,
            :manufacturer,
            :length_ft,
            :color,
            :scale,
            :service,
            :operations_service,
            :load_status,
            :current_industry_id,
            :current_track,
            :notes
        )
    ");

    $stmt->execute([
        'railroad_id' => $railroadId,
        'reporting_marks' => $row['reporting_marks'],
        'road_number' => $row['road_number'],
        'road_name' => $row['road_name'],
        'equipment_class' => $row['equipment_class'],
        'equipment_type' => $row['equipment_type'],
        'prototype' => $row['prototype'],
        'manufacturer' => $row['manufacturer'],
        'length_ft' => $row['length_ft'],
        'color' => $row['color'],
        'scale' => $row['scale'],
        'service' => $row['service'],
        'operations_service' => $row['operations_service'],
        'load_status' => $row['load_status'],
        'current_industry_id' => $row['current_industry_id'],
        'current_track' => $row['current_track'],
        'notes' => $row['notes']
    ]);
}

function ttJmriUpdateEquipment(PDO $pdo, int $equipmentId, array $row): void
{
    $stmt = $pdo->prepare("
        UPDATE equipment
        SET
            road_name = :road_name,
            equipment_type = :equipment_type,
            prototype = :prototype,
            length_ft = :length_ft,
            color = :color,
            scale = :scale,
            service = :service,
            operations_service = :operations_service,
            load_status = :load_status,
            current_industry_id = :current_industry_id,
            current_track = :current_track,
            notes = :notes
        WHERE id = :id
    ");

    $stmt->execute([
        'road_name' => $row['road_name'],
        'equipment_type' => $row['equipment_type'],
        'prototype' => $row['prototype'],
        'length_ft' => $row['length_ft'],
        'color' => $row['color'],
        'scale' => $row['scale'],
        'service' => $row['service'],
        'operations_service' => $row['operations_service'],
        'load_status' => $row['load_status'],
        'current_industry_id' => $row['current_industry_id'],
        'current_track' => $row['current_track'],
        'notes' => $row['notes'],
        'id' => $equipmentId
    ]);
}
