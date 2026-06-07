<?php

session_start();

require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$type = $_POST['type'] ?? '';
$id = (int)($_POST['id'] ?? 0);
$imageData = $_POST['cropped_image'] ?? '';

if (
    !in_array($type, ['equipment', 'industry']) ||
    $id <= 0 ||
    empty($imageData)
) {
    die('Invalid request.');
}

$imageData = preg_replace(
    '#^data:image/\w+;base64,#i',
    '',
    $imageData
);

$imageData = str_replace(
    ' ',
    '+',
    $imageData
);

$decodedImage = base64_decode($imageData);

if (!$decodedImage) {
    die('Invalid image data.');
}

if ($type === 'equipment') {

    $stmt = $pdo->prepare("
        SELECT e.*
        FROM equipment e
        JOIN railroads r
            ON e.railroad_id = r.id
        WHERE e.id = :id
        AND r.user_id = :user_id
        LIMIT 1
    ");

    $stmt->execute([
        'id' => $id,
        'user_id' => $_SESSION['user_id']
    ]);

    $record = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$record) {
        die('Equipment not found.');
    }

    $prefix = 'equipment';

} else {

    $stmt = $pdo->prepare("
        SELECT i.*
        FROM industries i
        JOIN railroads r
            ON i.railroad_id = r.id
        WHERE i.id = :id
        AND r.user_id = :user_id
        LIMIT 1
    ");

    $stmt->execute([
        'id' => $id,
        'user_id' => $_SESSION['user_id']
    ]);

    $record = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$record) {
        die('Industry not found.');
    }

    $prefix = 'industry';
}

$image = imagecreatefromstring($decodedImage);

if (!$image) {
    die('Failed to process image.');
}

$newFilename =    $prefix .    '_' .    $id .    '_' .    time() .    '.png';$newPath =    dirname(__DIR__) .    '/uploads/' .    $newFilename;imagealphablending($image, false);imagesavealpha($image, true);imagepng(    $image,    $newPath);

imagedestroy($image);

if (!empty($record['photo_filename'])) {

    $oldPath =
        dirname(__DIR__) .
        '/uploads/' .
        $record['photo_filename'];

    if (file_exists($oldPath)) {
        unlink($oldPath);
    }
}

if ($type === 'equipment') {

    $stmt = $pdo->prepare("
        UPDATE equipment
        SET photo_filename = :photo_filename
        WHERE id = :id
    ");

} else {

    $stmt = $pdo->prepare("
        UPDATE industries
        SET photo_filename = :photo_filename
        WHERE id = :id
    ");
}

$stmt->execute([
    'photo_filename' => $newFilename,
    'id' => $id
]);

if ($type === 'industry') {

    header(
        'Location: ../industries/view.php?id=' .
        $id
    );

} else {

    header(
        'Location: ../equipment/view.php?id=' .
        $id
    );
}

exit;

exit;