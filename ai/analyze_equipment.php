<?php

session_start();

require_once '../config/openai.php';

if (!isset($_SESSION['user_id'])) {

    header('Location: ../login.php');

    exit;

}

/*
|--------------------------------------------------------------------------
| Verify Upload
|--------------------------------------------------------------------------
*/

if (

    !isset($_FILES['photo'])

    ||

    $_FILES['photo']['error'] !== UPLOAD_ERR_OK

) {

    die('No photo uploaded.');

}

/*
|--------------------------------------------------------------------------
| Allowed File Types
|--------------------------------------------------------------------------
*/

$allowedExtensions = [

    'jpg',

    'jpeg',

    'png',

    'webp',

    'gif',

    'bmp',

    'heic',

    'heif',

    'avif'

];

$extension = strtolower(

    pathinfo(

        $_FILES['photo']['name'],

        PATHINFO_EXTENSION

    )

);

if (

    !in_array(

        $extension,

        $allowedExtensions

    )

) {

    die(

        'Invalid file type.'

    );

}

/*
|--------------------------------------------------------------------------
| Save Temporary Upload
|--------------------------------------------------------------------------
*/

$tempFilename =

    'ai_' .

    time() .

    '_' .

    bin2hex(

        random_bytes(4)

    ) .

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

    die(

        'Failed to save uploaded image.'

    );

}

/*
|--------------------------------------------------------------------------
| Save Photo Session
|--------------------------------------------------------------------------
*/

$_SESSION['ai_photo'] =

    $tempFilename;

/*
|--------------------------------------------------------------------------
| Read Image
|--------------------------------------------------------------------------
*/

$imageData =

    base64_encode(

        file_get_contents(

            $tempPath

        )

    );

/*
|--------------------------------------------------------------------------
| Mime Type
|--------------------------------------------------------------------------
*/

$mimeType = match (

    $extension

) {

    'png' =>

        'image/png',

    'webp' =>

        'image/webp',

    'gif' =>

        'image/gif',

    'bmp' =>

        'image/bmp',

    'avif' =>

        'image/avif',

    default =>

        'image/jpeg'

};

/*
|--------------------------------------------------------------------------
| AI Prompt
|--------------------------------------------------------------------------
*/

$prompt = <<<PROMPT
You are an expert railroad equipment identification assistant specializing in model railroads.

Analyze this model railroad equipment photo.

Return ONLY valid JSON.

{
  "reporting_marks":"",
  "road_number":"",
  "road_name":"",
  "equipment_class":"",
  "equipment_type":"",
  "prototype_design":"",
  "manufacturer":"",
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

equipment_type rules:

Freight Car:

Boxcar
Covered Hopper
Open Hopper
Tank Car
Flatcar
Centerbeam Flatcar
Bulkhead Flatcar
Intermodal Flatcar
Well Car
Gondola
Refrigerator Car
Mechanical Refrigerator Car
Autorack
Coil Car
Stock Car
Depressed Center Flatcar
Spine Car
Other

Locomotive:

Diesel
Steam
Electric
Gas Turbine
Slug
Other

Passenger Car:

Coach
Combine
Baggage
RPO
Diner
Sleeper
Observation
Business Car
Dome Car
Other

Caboose:

Cupola
Bay Window
Transfer Caboose
Wide Vision
Other

MOW:

Crane
Ballast Hopper
Snow Plow
Jordan Spreader
Tool Car
Track Geometry Car
Scale Test Car
Water Car
Rail Train Car
Other

Rules:

- reporting_marks should contain visible reporting marks.

- road_number should contain visible numbers.

- road_name should contain the railroad name if visible.

- equipment_type should describe WHAT the equipment is.

Examples:

Boxcar
Diesel
Tank Car
Coach

- prototype_design should describe WHICH one it is.

Examples:

50' Plate F Boxcar
PS-2 Covered Hopper
EMD GP40-2
GE AC4400CW

- manufacturer should contain the likely model manufacturer if recognizable.

Examples:

Athearn
Atlas
ScaleTrains
Rapido
Bowser
Walthers

If unknown return blank.

- color should be 20 characters or less.

Examples:

Brown
Silver
Gray
Black
Yellow
Pullman Green
Tuscan Red

- length_ft should contain ONLY a number.

Examples:

40
50
60
89

- scale should default to HO unless another scale is obvious.

- load_status should be Empty unless a visible load exists.

- service should contain likely service if obvious.

Examples:

General Freight
Coal Service
Intermodal
Grain Service

Otherwise blank.

- notes should contain assumptions or unusual observations.

- if uncertain, provide your best estimate.

- if unknown, return an empty string.

Return ONLY valid JSON.
PROMPT;

/*
|--------------------------------------------------------------------------
| API Payload
|--------------------------------------------------------------------------
*/

$payload = [

    'model' => 'gpt-4.1',

    'input' => [[

        'role' => 'user',

        'content' => [

            [

                'type' => 'input_text',

                'text' =>

                    $prompt

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

/*
|--------------------------------------------------------------------------
| Call OpenAI
|--------------------------------------------------------------------------
*/

$ch = curl_init();

curl_setopt(

    $ch,

    CURLOPT_URL,

    'https://api.openai.com/v1/responses'

);

curl_setopt(

    $ch,

    CURLOPT_POST,

    true

);

curl_setopt(

    $ch,

    CURLOPT_RETURNTRANSFER,

    true

);

curl_setopt(

    $ch,

    CURLOPT_HTTPHEADER,

    [

        'Content-Type: application/json',

        'Authorization: Bearer ' .

        $OPENAI_API_KEY

    ]

);

curl_setopt(

    $ch,

    CURLOPT_POSTFIELDS,

    json_encode(

        $payload

    )

);

$response = curl_exec(

    $ch

);

if (

    curl_errno(

        $ch

    )

) {

    die(

        'OpenAI Error: '

        .

        curl_error(

            $ch

        )

    );

}

curl_close(

    $ch

);

$result = json_decode(

    $response,

    true

);

/*
|--------------------------------------------------------------------------
| Verify OpenAI Response
|--------------------------------------------------------------------------
*/

if (

    empty(

        $result['output'][0]['content'][0]['text']

    )

) {

    echo '<h3>Unexpected OpenAI Response</h3>';

    echo '<pre>';

    print_r(

        $result

    );

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

    trim(

        $jsonText

    )

);

$jsonText = preg_replace(

    '/```$/',

    '',

    trim(

        $jsonText

    )

);

$jsonText = trim(

    $jsonText

);

/*
|--------------------------------------------------------------------------
| Decode JSON
|--------------------------------------------------------------------------
*/

$equipment = json_decode(

    $jsonText,

    true

);

if (

    !$equipment

) {

    echo '<h3>AI Returned Invalid JSON</h3>';

    echo '<pre>';

    echo htmlspecialchars(

        $jsonText

    );

    echo '</pre>';

    exit;

}

/*
|--------------------------------------------------------------------------
| Database
|--------------------------------------------------------------------------
*/

require_once '../config/database.php';

/*
|--------------------------------------------------------------------------
| Duplicate Detection
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("

SELECT

    id,

    reporting_marks,

    road_number

FROM equipment

WHERE

    reporting_marks = ?

AND

    road_number = ?

LIMIT 1

");

$stmt->execute([

    trim(

        $equipment['reporting_marks']

        ?? ''

    ),

    trim(

        $equipment['road_number']

        ?? ''

    )

]);

$existingEquipment =

    $stmt->fetch(

        PDO::FETCH_ASSOC

    );

if (

    $existingEquipment

) {

?>

<?php include '../includes/header.php'; ?>

<title>

Equipment Already Exists

</title>

</head>

<body>

<?php include '../includes/navbar.php'; ?>

<div class="container mt-5">

<div class="card">

<div class="card-header bg-warning">

Equipment Already Exists

</div>

<div class="card-body">

<h4>

<?php

echo htmlspecialchars(

    $existingEquipment['reporting_marks']

);

?>

<?php

echo htmlspecialchars(

    $existingEquipment['road_number']

);

?>

already exists.

</h4>

<p class="text-muted">

AI found matching equipment already in your roster.

</p>

<div class="mt-4">

<a

href="../equipment/view.php?id=<?php

echo $existingEquipment['id'];

?>"

class="btn btn-primary">

View Equipment

</a>

<a

href="../equipment/edit.php?id=<?php

echo $existingEquipment['id'];

?>"

class="btn btn-success">

Edit Existing

</a>

<a

href="scan_equipment.php"

class="btn btn-secondary">

Cancel

</a>

</div>

</div>

</div>

</div>

<?php include '../includes/footer.php'; ?>

<?php

exit;

}

/*
|--------------------------------------------------------------------------
| Save AI Data To Session
|--------------------------------------------------------------------------
*/

$_SESSION['ai_data'] = [

    'reporting_marks' =>

        $equipment['reporting_marks']

        ?? '',

    'road_number' =>

        $equipment['road_number']

        ?? '',

    'road_name' =>

        $equipment['road_name']

        ?? '',

    'equipment_class' =>

        $equipment['equipment_class']

        ?? '',

    'equipment_type' =>

        $equipment['equipment_type']

        ?? '',

    'prototype' =>

        $equipment['prototype_design']

        ?? '',

    'manufacturer' =>

        $equipment['manufacturer']

        ?? '',

    'service' =>

        $equipment['service']

        ?? '',

    'color' =>

        $equipment['color']

        ?? '',

    'length_ft' =>

        $equipment['length_ft']

        ?? '',

    'scale' =>

        $equipment['scale']

        ?? 'HO',

    'load_status' =>

        $equipment['load_status']

        ?? 'Empty',

    'notes' =>

        $equipment['notes']

        ?? ''

];

/*
|--------------------------------------------------------------------------
| Defaults
|--------------------------------------------------------------------------
*/

if (

    empty(

        $_SESSION['ai_data']['load_status']

    )

) {

    $_SESSION['ai_data']['load_status'] =

        'Empty';

}

if (

    empty(

        $_SESSION['ai_data']['scale']

    )

) {

    $_SESSION['ai_data']['scale'] =

        'HO';

}

/*
|--------------------------------------------------------------------------
| Redirect To Add Equipment
|--------------------------------------------------------------------------
*/

header(

    'Location: ../equipment/add.php'

);

exit;