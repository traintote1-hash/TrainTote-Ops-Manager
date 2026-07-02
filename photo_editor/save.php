<?php

session_start();

require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

function photoEditorJsonResponse(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit;
}

function photoEditorWantsJson(): bool
{
    return ($_POST['action'] ?? '') === 'save_image'
        || isset($_FILES['edited_image']);
}

function photoEditorSafeTempPhoto(): string
{
    $tempPhoto = $_SESSION['ai_photo'] ?? '';
    $safeTempPhoto = basename($tempPhoto);

    if ($tempPhoto === '' || $safeTempPhoto !== $tempPhoto) {
        throw new RuntimeException('No temporary photo found.');
    }

    return $safeTempPhoto;
}

function photoEditorReadImageBytes(): string
{
    if (
        isset($_FILES['edited_image'])
        && ($_FILES['edited_image']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK
    ) {
        if (($_FILES['edited_image']['size'] ?? 0) > 8 * 1024 * 1024) {
            throw new RuntimeException('Edited image is too large.');
        }

        $bytes = file_get_contents($_FILES['edited_image']['tmp_name']);

        if ($bytes === false || $bytes === '') {
            throw new RuntimeException('Invalid image data.');
        }

        return $bytes;
    }

    $imageData = $_POST['cropped_image'] ?? '';

    if ($imageData === '') {
        throw new RuntimeException('Invalid request.');
    }

    $imageData = preg_replace('#^data:image/\w+;base64,#i', '', $imageData);
    $imageData = str_replace(' ', '+', $imageData);
    $decodedImage = base64_decode($imageData, true);

    if (!$decodedImage) {
        throw new RuntimeException('Invalid image data.');
    }

    return $decodedImage;
}

function photoEditorSavePng(string $imageBytes, string $path): void
{
    $image = imagecreatefromstring($imageBytes);

    if (!$image) {
        throw new RuntimeException('Failed to process image.');
    }

    imagealphablending($image, false);
    imagesavealpha($image, true);

    if (!imagepng($image, $path)) {
        imagedestroy($image);
        throw new RuntimeException('Failed to save image.');
    }

    imagedestroy($image);
}

$wantsJson = photoEditorWantsJson();

try {
    $type = $_POST['type'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    if (!in_array($type, ['equipment', 'industry', 'temp'], true)) {
        throw new RuntimeException('Invalid request.');
    }

    if ($type !== 'temp' && $id <= 0) {
        throw new RuntimeException('Invalid request.');
    }

    $imageBytes = photoEditorReadImageBytes();

    if ($type === 'temp') {
        $safeTempPhoto = photoEditorSafeTempPhoto();
        $tempDir = dirname(__DIR__) . '/uploads/temp';
        $oldPath = $tempDir . '/' . $safeTempPhoto;

        if (!is_file($oldPath)) {
            throw new RuntimeException('No temporary photo found.');
        }

        $newFilename = 'temp_equipment_' . (int)$_SESSION['user_id'] . '_' . time() . '.png';
        $newPath = $tempDir . '/' . $newFilename;

        photoEditorSavePng($imageBytes, $newPath);

        if ($safeTempPhoto !== $newFilename && is_file($oldPath)) {
            unlink($oldPath);
        }

        $_SESSION['ai_photo'] = $newFilename;

        if ($wantsJson) {
            photoEditorJsonResponse([
                'success' => true,
                'redirect_url' => '../equipment/add.php'
            ]);
        }

        header('Location: ../equipment/add.php');
        exit;
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
            throw new RuntimeException('Equipment not found.');
        }

        $prefix = 'equipment';
    }
    else {
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
            throw new RuntimeException('Industry not found.');
        }

        $prefix = 'industry';
    }

    $newFilename = $prefix . '_' . $id . '_' . time() . '.png';
    $newPath = dirname(__DIR__) . '/uploads/' . $newFilename;
    photoEditorSavePng($imageBytes, $newPath);

    if (!empty($record['photo_filename'])) {
        $oldPath = dirname(__DIR__) . '/uploads/' . $record['photo_filename'];

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
    }
    else {
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

    $redirectUrl = $type === 'industry'
        ? '../industries/view.php?id=' . $id
        : '../equipment/view.php?id=' . $id;

    if ($wantsJson) {
        photoEditorJsonResponse([
            'success' => true,
            'redirect_url' => $redirectUrl
        ]);
    }

    header('Location: ' . $redirectUrl);
    exit;
}
catch (Throwable $exception) {
    if ($wantsJson) {
        photoEditorJsonResponse([
            'success' => false,
            'error' => $exception->getMessage()
        ], 400);
    }

    die($exception->getMessage());
}
