<?php

session_start();

require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$type = $_POST['type'] ?? '';
$id = (int)($_POST['id'] ?? 0);
$degrees = (int)($_POST['degrees'] ?? 0);

if (!in_array($type, ['equipment', 'industry', 'temp'])) {
    die('Invalid type.');
}

if (!in_array($degrees, [

    -90,
    -5,
    -1,
    1,
    5,
    90

])) {

    die('Invalid rotation.');

}

/*
|--------------------------------------------------------------------------
| TEMP PHOTO
|--------------------------------------------------------------------------
*/

if ($type === 'temp') {

    if (empty($_SESSION['ai_photo'])) {
        die('Temporary image not found.');
    }

    $filename =
        dirname(__DIR__) .
        '/uploads/temp/' .
        $_SESSION['ai_photo'];
}

/*
|--------------------------------------------------------------------------
| EQUIPMENT
|--------------------------------------------------------------------------
*/

elseif ($type === 'equipment') {

    $stmt = $pdo->prepare("
        SELECT e.photo_filename
        FROM equipment e
        JOIN railroads r
            ON e.railroad_id = r.id
        WHERE
            e.id = :id
        AND
            r.user_id = :user_id
        LIMIT 1
    ");

    $stmt->execute([
        'id' => $id,
        'user_id' => $_SESSION['user_id']
    ]);

    $record = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$record || empty($record['photo_filename'])) {
        die('Photo not found.');
    }

    $filename =
        dirname(__DIR__) .
        '/uploads/' .
        $record['photo_filename'];
}

/*
|--------------------------------------------------------------------------
| INDUSTRY
|--------------------------------------------------------------------------
*/

else {

    $stmt = $pdo->prepare("
        SELECT i.photo_filename
        FROM industries i
        JOIN railroads r
            ON i.railroad_id = r.id
        WHERE
            i.id = :id
        AND
            r.user_id = :user_id
        LIMIT 1
    ");

    $stmt->execute([
        'id' => $id,
        'user_id' => $_SESSION['user_id']
    ]);

    $record = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$record || empty($record['photo_filename'])) {
        die('Photo not found.');
    }

    $filename =
        dirname(__DIR__) .
        '/uploads/' .
        $record['photo_filename'];
}

/*
|--------------------------------------------------------------------------
| Rotate Image
|--------------------------------------------------------------------------
*/

try {

    $image = new Imagick($filename);

    $image->rotateImage('white', $degrees);

    $image->setImagePage(0, 0, 0, 0);

    $image->writeImage($filename);

    $image->clear();

    $image->destroy();

}
catch (Exception $e) {

    die(
        'Rotate failed: ' .
        $e->getMessage()
    );

}

/*
|--------------------------------------------------------------------------
| Return
|--------------------------------------------------------------------------
*/

header(
    'Location: edit.php?type=' .
    urlencode($type) .
    '&id=' .
    $id
);

exit;