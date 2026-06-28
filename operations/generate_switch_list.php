<?php

session_start();

require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

if (!isset($_GET['job_id'])) {
    die('Job ID missing.');
}

$jobId = (int)$_GET['job_id'];

/*
|--------------------------------------------------------------------------
| Find Railroad
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT id
    FROM railroads
    WHERE user_id = ?
    LIMIT 1
");

$stmt->execute([
    $_SESSION['user_id']
]);

$railroad = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$railroad) {
    die('Railroad not found.');
}

/*
|--------------------------------------------------------------------------
| Verify Job Belongs To Railroad
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT *
    FROM jobs
    WHERE id = ?
    AND railroad_id = ?
    LIMIT 1
");

$stmt->execute([
    $jobId,
    $railroad['id']
]);

$job = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$job) {
    die('Job not found.');
}

/*
|--------------------------------------------------------------------------
| Assigned Industries
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        industry_id,
        sequence_number
    FROM job_industries
    WHERE job_id = ?
    ORDER BY sequence_number
");

$stmt->execute([
    $jobId
]);

$industries = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($industries) == 0) {
    die('No industries assigned to this job.');
}

$industryIds = [];

foreach ($industries as $industry) {
    $industryIds[] = $industry['industry_id'];
}

/*
|--------------------------------------------------------------------------
| Delete Previous Switch List
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    DELETE
    FROM job_cars
    WHERE job_id = ?
");

$stmt->execute([
    $jobId
]);

$sequence = 1;

/*
|--------------------------------------------------------------------------
| Generate Pickups
|--------------------------------------------------------------------------
*/

$placeholders = implode(
    ',',
    array_fill(
        0,
        count($industryIds),
        '?'
    )
);

$sql = "

SELECT

    e.id AS equipment_id,

    w.id AS waybill_id,

    e.current_industry_id,

    w.destination_industry_id

FROM waybills w

JOIN equipment e
    ON w.equipment_id = e.id

WHERE

    w.active = 1

    AND

    e.current_industry_id IN ($placeholders)

";

$stmt = $pdo->prepare($sql);

$stmt->execute(
    $industryIds
);

$pickups = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Save Pickup Rows
|--------------------------------------------------------------------------
*/

$stmtInsert = $pdo->prepare("

    INSERT INTO job_cars (

        job_id,

        car_id,

        equipment_id,

        waybill_id,

        move_type,

        from_industry_id,

        to_industry_id,

        sequence_num,

        status,

        completed

    )

    VALUES (

        ?,?,?,?,?,?,?,?,?,?

    )

");

foreach ($pickups as $car) {

    $stmtInsert->execute([

        $jobId,

        $car['equipment_id'],

        $car['equipment_id'],

        $car['waybill_id'],

        'pickup',

        $car['current_industry_id'],

        $car['destination_industry_id'],

        $sequence,

        'Pending',

        0

    ]);

    $sequence++;

}

/*
|--------------------------------------------------------------------------
| Generate Setouts
|--------------------------------------------------------------------------
*/

$sql = "

SELECT

    e.id AS equipment_id,

    w.id AS waybill_id,

    e.current_industry_id,

    w.destination_industry_id

FROM waybills w

JOIN equipment e
    ON w.equipment_id = e.id

WHERE

    w.active = 1

    AND

    w.destination_industry_id IN ($placeholders)

    AND

    e.current_industry_id <> w.destination_industry_id

";

$stmt = $pdo->prepare($sql);

$stmt->execute(
    $industryIds
);

$setouts = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Save Setout Rows
|--------------------------------------------------------------------------
*/

foreach ($setouts as $car) {

    $stmtInsert->execute([

        $jobId,

        $car['equipment_id'],

        $car['equipment_id'],

        $car['waybill_id'],

        'setout',

        $car['current_industry_id'],

        $car['destination_industry_id'],

        $sequence,

        'Pending',

        0

    ]);

    $sequence++;

}

/*
|--------------------------------------------------------------------------
| Switch List
|--------------------------------------------------------------------------
*/

header(
    "Location: switch_list.php?job_id=$jobId"
);

exit;