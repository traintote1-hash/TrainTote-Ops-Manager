<?php

session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);

    echo json_encode([
        'error' => 'Not logged in'
    ]);

    exit;
}

if (empty($_POST['image'])) {
    http_response_code(400);

    echo json_encode([
        'error' => 'No image received'
    ]);

    exit;
}

$imageData = $_POST['image'];

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

$inputFile =
    sys_get_temp_dir() .
    '/tt_input_' .
    uniqid() .
    '.png';

$outputFile =
    sys_get_temp_dir() .
    '/tt_output_' .
    uniqid() .
    '.png';

file_put_contents(
    $inputFile,
    base64_decode($imageData)
);

$command =
    'HOME=/home/pku1w37u7apo ' .
    '/home/pku1w37u7apo/virtualenv/bgtest/3.11/bin/python ' .
    '/home/pku1w37u7apo/public_html/ops/photo_editor/remove_background.py ' .
    escapeshellarg($inputFile) . ' ' .
    escapeshellarg($outputFile) .
    ' 2>&1';

$result = shell_exec($command);

if (!file_exists($outputFile)) {

    if (file_exists($inputFile)) {
        unlink($inputFile);
    }

    echo json_encode([
        'error' => $result
    ]);

    exit;
}

$outputData =
    file_get_contents($outputFile);

unlink($inputFile);
unlink($outputFile);

echo json_encode([
    'success' => true,
    'image' =>
        'data:image/png;base64,' .
        base64_encode($outputData)
]);