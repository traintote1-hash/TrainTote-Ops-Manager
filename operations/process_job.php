<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();

require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die('Invalid request.');
}

if (!isset($_POST['job_id'])) {
    die('Job ID missing.');
}

$jobId = (int)$_POST['job_id'];

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
| Get industries assigned to this job
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT industry_id
    FROM job_industries
    WHERE job_id = ?
    ORDER BY sequence_number
");

$stmt->execute([
    $jobId
]);

$industryIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

if (empty($industryIds)) {
    die('No industries assigned to this job.');
}

$placeholders = implode(',', array_fill(0, count($industryIds), '?'));

/*
|--------------------------------------------------------------------------
| Find active waybills for cars currently at those industries
|--------------------------------------------------------------------------
*/

$sql = "

SELECT

    w.id AS waybill_id,
    w.equipment_id,
    w.current_cycle,
    w.cycle_count,
    w.status,
    w.origin_industry_id,
    w.destination_industry_id,

    e.reporting_marks,
    e.road_number,
    e.current_industry_id,
    e.load_status

FROM waybills w

JOIN equipment e
    ON w.equipment_id = e.id

WHERE

    w.active = 1

    AND

    e.current_industry_id IN ($placeholders)

ORDER BY

    e.reporting_marks,
    e.road_number

";

$stmt = $pdo->prepare($sql);
$stmt->execute($industryIds);

$waybills = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Prepare operation history entries
|--------------------------------------------------------------------------
*/

$logEntries = [];
/*
|--------------------------------------------------------------------------
| Process Job
|--------------------------------------------------------------------------
*/

$pdo->beginTransaction();

try {

    foreach ($waybills as $waybill) {

        /*
        --------------------------------------------------------------
        Save previous values
        --------------------------------------------------------------
        */

        $oldIndustry = $waybill['current_industry_id'];

        $oldStatus = $waybill['status'];

        $oldCycle = $waybill['current_cycle'];

        /*
        --------------------------------------------------------------
        Toggle Loaded / Empty
        --------------------------------------------------------------
        */

        $newStatus = ($waybill['status'] == 'Loaded')
            ? 'Empty'
            : 'Loaded';

        /*
        --------------------------------------------------------------
        Move Equipment
        --------------------------------------------------------------
        */

        $stmt = $pdo->prepare("

            UPDATE equipment

            SET

                current_industry_id = ?,

                load_status = ?

            WHERE id = ?

        ");

        $stmt->execute([

            $waybill['destination_industry_id'],

            $newStatus,

            $waybill['equipment_id']

        ]);

        /*
        --------------------------------------------------------------
        Advance Cycle
        --------------------------------------------------------------
        */

        $nextCycle = $waybill['current_cycle'] + 1;

        if ($nextCycle > $waybill['cycle_count']) {

            $nextCycle = 1;

        }

        /*
        --------------------------------------------------------------
        Reverse Direction
        --------------------------------------------------------------
        */

        $stmt = $pdo->prepare("

            UPDATE waybills

            SET

                current_cycle = ?,

                origin_industry_id = ?,

                destination_industry_id = ?,

                status = ?

            WHERE id = ?

        ");

        $stmt->execute([

            $nextCycle,

            $waybill['destination_industry_id'],

            $waybill['origin_industry_id'],

            $newStatus,

            $waybill['waybill_id']

        ]);

        /*
        --------------------------------------------------------------
        Save history entry for later
        --------------------------------------------------------------
        */

        $logEntries[] = [

            'railroad_id' => $railroad['id'],

            'job_id' => $jobId,

            'equipment_id' => $waybill['equipment_id'],

            'old_industry_id' => $oldIndustry,

            'new_industry_id' => $waybill['destination_industry_id'],

            'old_status' => $oldStatus,

            'new_status' => $newStatus,

            'old_cycle' => $oldCycle,

            'new_cycle' => $nextCycle

        ];

    }
    /*
    |--------------------------------------------------------------------------
    | Commit Railroad Changes
    |--------------------------------------------------------------------------
    */

    $pdo->commit();

} catch (Exception $e) {

    $pdo->rollBack();

    die($e->getMessage());

}

/*
|--------------------------------------------------------------------------
| Write Operation History
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("

    INSERT INTO operation_log (

        railroad_id,
        job_id,
        equipment_id,

        old_industry_id,
        new_industry_id,

        old_status,
        new_status,

        old_cycle,
        new_cycle

    )

    VALUES (

        ?,?,?,?,?,?,?,?,?

    )

");

foreach ($logEntries as $log) {

    $stmt->execute([

        $log['railroad_id'],

        $log['job_id'],

        $log['equipment_id'],

        $log['old_industry_id'],

        $log['new_industry_id'],

        $log['old_status'],

        $log['new_status'],

        $log['old_cycle'],

        $log['new_cycle']

    ]);

}
/*
|--------------------------------------------------------------------------
| Return To Switch List
|--------------------------------------------------------------------------
*/

header(

    "Location: switch_list.php?job_id=$jobId&completed=1"

);

exit;

?>