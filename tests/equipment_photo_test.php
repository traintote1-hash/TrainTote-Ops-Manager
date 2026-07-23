<?php
require_once dirname(__DIR__) . '/equipment/photo_service.php';

function photoExpect($condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$root = sys_get_temp_dir() . '/traintote_photo_' . bin2hex(random_bytes(4));
$temp = $root . '/temp';
$uploads = $root . '/uploads';
mkdir($temp, 0777, true);
mkdir($uploads, 0777, true);
$sessionName = 'ai_test.jpg';
$sessionPath = $temp . '/' . $sessionName;
$replacementPath = $root . '/replacement.png';
file_put_contents($sessionPath, 'scanned-image');
file_put_contents($replacementPath, 'replacement-image');

try {
    $sessionSource = ttEquipmentPhotoSource([], $sessionName, $temp);
    photoExpect(realpath($sessionSource['path']) === realpath($sessionPath) && $sessionSource['temporary_session_photo'] === true, 'A scanned session photo must survive into the Add Equipment save flow.');
    $saved = ttEquipmentPersistPhoto($sessionSource, $uploads);
    photoExpect(is_file($saved['path']) && file_get_contents($saved['path']) === 'scanned-image', 'The scanned photo must be copied into permanent equipment storage.');

    $replacement = ttEquipmentPhotoSource([
        'error' => UPLOAD_ERR_OK,
        'tmp_name' => $replacementPath,
        'name' => 'replacement.png',
        'size' => filesize($replacementPath),
    ], $sessionName, $temp);
    photoExpect($replacement['path'] === $replacementPath && $replacement['temporary_session_photo'] === false, 'An explicitly selected replacement must take precedence over the scanned photo.');

    $expiredRejected = false;
    try {
        ttEquipmentPhotoSource([], 'missing.jpg', $temp);
    } catch (RuntimeException $e) {
        $expiredRejected = strpos($e->getMessage(), 'expired') !== false;
    }
    photoExpect($expiredRejected, 'A missing temporary scan must produce a useful expired-photo error.');

    $add = file_get_contents(dirname(__DIR__) . '/equipment/add.php');
    $service = file_get_contents(dirname(__DIR__) . '/equipment/photo_service.php');
    photoExpect(strpos($add, 'photo_filename') !== false && strpos($add, 'ttEquipmentPersistPhoto') !== false, 'Add Equipment must save the persisted photo filename on the equipment record.');
    photoExpect(strpos($service, '$uploadError !== UPLOAD_ERR_NO_FILE') < strpos($service, '$sessionPhoto === \'\''), 'The replacement upload must be evaluated ahead of the AI session photo.');
} finally {
    foreach (glob($uploads . '/*') ?: [] as $file) {
        unlink($file);
    }
    foreach (glob($temp . '/*') ?: [] as $file) {
        unlink($file);
    }
    if (is_file($replacementPath)) {
        unlink($replacementPath);
    }
    rmdir($uploads);
    rmdir($temp);
    rmdir($root);
}

echo "equipment_photo_test: OK\n";
