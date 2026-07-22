<?php

function ttEquipmentPhotoAllowedExtensions(): array
{
    return ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp', 'heic', 'heif', 'avif'];
}

function ttEquipmentPhotoSafeFilename(string $filename): string
{
    $filename = trim($filename);
    if ($filename === '' || basename($filename) !== $filename) {
        return '';
    }
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return in_array($extension, ttEquipmentPhotoAllowedExtensions(), true) ? $filename : '';
}

function ttEquipmentPhotoSource(array $file, string $sessionPhoto, string $tempDirectory): ?array
{
    $uploadError = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($uploadError !== UPLOAD_ERR_NO_FILE) {
        if ($uploadError !== UPLOAD_ERR_OK || empty($file['tmp_name'])) {
            throw new RuntimeException('The replacement photo could not be uploaded. Please select it again.');
        }
        if ((int)($file['size'] ?? 0) > 12 * 1024 * 1024) {
            throw new RuntimeException('The replacement photo is too large. Please choose an image under 12 MB.');
        }
        $safeName = ttEquipmentPhotoSafeFilename((string)($file['name'] ?? ''));
        if ($safeName === '' || !is_file((string)$file['tmp_name'])) {
            throw new RuntimeException('The replacement photo type is not supported.');
        }
        return [
            'path' => (string)$file['tmp_name'],
            'extension' => strtolower(pathinfo($safeName, PATHINFO_EXTENSION)),
            'temporary_session_photo' => false,
        ];
    }

    if ($sessionPhoto === '') {
        return null;
    }
    $safeName = ttEquipmentPhotoSafeFilename($sessionPhoto);
    $tempPath = rtrim($tempDirectory, '/\\') . DIRECTORY_SEPARATOR . $safeName;
    if ($safeName === '' || !is_file($tempPath)) {
        throw new RuntimeException('The scanned photo has expired or is no longer available. Scan it again or choose a replacement photo.');
    }
    return [
        'path' => $tempPath,
        'extension' => strtolower(pathinfo($safeName, PATHINFO_EXTENSION)),
        'temporary_session_photo' => true,
    ];
}

function ttEquipmentPersistPhoto(array $source, string $uploadDirectory): array
{
    if (!is_dir($uploadDirectory) || !is_writable($uploadDirectory)) {
        throw new RuntimeException('The equipment photo storage is unavailable. Please try again later.');
    }
    $filename = 'equipment_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $source['extension'];
    $destination = rtrim($uploadDirectory, '/\\') . DIRECTORY_SEPARATOR . $filename;
    if (!copy($source['path'], $destination)) {
        throw new RuntimeException('The equipment photo could not be saved. Please try again.');
    }
    return ['filename' => $filename, 'path' => $destination];
}
