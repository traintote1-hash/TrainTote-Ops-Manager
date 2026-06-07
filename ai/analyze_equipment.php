<?php

session_start();

require_once '../config/openai.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

if (
    !isset($_FILES['photo']) ||
    $_FILES['photo']['error'] !== UPLOAD_ERR_OK
) {
    die('No photo uploaded.');
}

$allowedExtensions = [
    'jpg',
    'jpeg',
    'png',
    'webp'
];

$extension = strtolower(
    pathinfo(
        $_FILES['photo']['name'],
        PATHINFO_EXTENSION
    )
);

if (!in_array($extension, $allowedExtensions)) {
    die('Invalid file type.');
}

$tempFilename =
    'ai_' .
    time() .
    '_' .
    bin2hex(random_bytes(4)) .
    '.' .
    $extension;

$tempPath =
    dirname(__DIR__) .
    '/uploads/temp/' .
    $tempFilename;

if (
    !move_uploaded_file(
        $_FILES['photo']['tmp_name'],
        $tempPath
    )
) {
    die('Failed to save uploaded image.');
}

$_SESSION['ai_photo'] = $tempFilename;

$imageData = base64_encode(
    file_get_contents($tempPath)
);

$mimeType = match ($extension) {
    'png' => 'image/png',
    'webp' => 'image/webp',
    default => 'image/jpeg'
};

$prompt = <<<PROMPT
You are an expert railroad equipment identification assistant specializing in model railroads.

Analyze this model railroad equipment photo.

Identify all visible information and infer any additional information that can reasonably be determined.

Return ONLY valid JSON.

{
  "reporting_marks":"",
  "road_number":"",
  "road_name":"",
  "equipment_class":"",
  "equipment_type":"",
  "prototype_design":"",
  "service":"",
  "color":"",
  "length_ft":"",
  "scale":"",
  "load_status":"",
  "notes":""
}

equipment_class must be one of:

Freight Car
Locomotive
Passenger Car
Caboose
MOW

Rules:

- reporting_marks should contain the visible reporting marks.
- road_number should contain the visible equipment number.
- road_name should contain the railroad name if visible.
- equipment_type should identify the equipment as specifically as possible.
- prototype_design should identify the likely prototype design if recognizable.
- service should identify the most likely railroad service.
- estimate car length in feet.
- estimate scale if possible.
- load_status should be Empty unless a visible load is present.
- notes should explain assumptions.
- if uncertain, provide your best estimate.
- if unknown, return an empty string.
- return only valid JSON.
PROMPT;

$payload = [
    'model' => 'gpt-4.1',
    'input' => [[
        'role' => 'user',
        'content' => [
            [
                'type' => 'input_text',
                'text' => $prompt
            ],
            [
                'type' => 'input_image',
                'image_url' =>
                    'data:' .
                    $mimeType .
                    ';base64,' .
                    $imageData
            ]
        ]
    ]]
];

$ch = curl_init();

curl_setopt(
    $ch,
    CURLOPT_URL,
    'https://api.openai.com/v1/responses'
);

curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

curl_setopt(
    $ch,
    CURLOPT_HTTPHEADER,
    [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $OPENAI_API_KEY
    ]
);

curl_setopt(
    $ch,
    CURLOPT_POSTFIELDS,
    json_encode($payload)
);

$response = curl_exec($ch);

if (curl_errno($ch)) {
    die('OpenAI Error: ' . curl_error($ch));
}

curl_close($ch);

$result = json_decode($response, true);

if (
    empty($result['output'][0]['content'][0]['text'])
) {
    echo '<h3>Unexpected OpenAI Response</h3>';
    echo '<pre>';
    print_r($result);
    echo '</pre>';
    exit;
}

$jsonText =
    $result['output'][0]['content'][0]['text'];

/*
|--------------------------------------------------------------------------
| Remove Markdown Code Fences
|--------------------------------------------------------------------------
*/

$jsonText = preg_replace(
    '/^```json\s*/i',
    '',
    trim($jsonText)
);

$jsonText = preg_replace(
    '/```$/',
    '',
    trim($jsonText)
);

$jsonText = trim($jsonText);

$equipment =
    json_decode($jsonText, true);

if (!$equipment) {

    echo '<h3>AI Returned Invalid JSON</h3>';

    echo '<pre>';
    echo htmlspecialchars($jsonText);
    echo '</pre>';

    exit;

}



$query = http_build_query([

    'reporting_marks' =>
        $equipment['reporting_marks'] ?? '',

    'road_number' =>
        $equipment['road_number'] ?? '',

    'road_name' =>
        $equipment['road_name'] ?? '',

    'equipment_class' =>
        $equipment['equipment_class'] ?? '',

    'equipment_type' =>
        $equipment['equipment_type'] ?? '',

    'prototype' =>
        $equipment['prototype_design'] ?? '',

    'service' =>
        $equipment['service'] ?? '',

    'manufacturer' =>
        $equipment['manufacturer'] ?? '',

    'color' =>
        $equipment['color'] ?? '',

    'length_ft' =>
        $equipment['length_ft'] ?? '',

    'scale' =>
        $equipment['scale'] ?? '',

    'load_status' =>
        $equipment['load_status'] ?? '',

    'notes' =>
        $equipment['notes'] ?? ''

]);

header(
    'Location: ../equipment/add.php?' . $query
);

exit;

/*
$query = http_build_query([
    'reporting_marks' => $equipment['reporting_marks'] ?? '',
    'road_number' => $equipment['road_number'] ?? '',
    'road_name' => $equipment['road_name'] ?? '',
    'equipment_class' => $equipment['equipment_class'] ?? '',
    'equipment_type' => $equipment['equipment_type'] ?? '',
    'prototype_design' => $equipment['prototype_design'] ?? '',
    'service' => $equipment['service'] ?? '',
    'color' => $equipment['color'] ?? '',
    'length_ft' => $equipment['length_ft'] ?? '',
    'scale' => $equipment['scale'] ?? '',
    'load_status' => $equipment['load_status'] ?? '',
    'notes' => $equipment['notes'] ?? ''
]);

header(
    'Location: ../equipment/add.php?' . $query
);

exit;
*/
?>
