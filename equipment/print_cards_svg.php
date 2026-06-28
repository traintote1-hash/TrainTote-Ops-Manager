<?php

session_start();

require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {

    header('Location: ../login.php');
    exit;

}

if (empty($_POST['equipment_ids'])) {

    die('No equipment selected.');

}

$ids = array_map(
    'intval',
    $_POST['equipment_ids']
);

$idList = implode(
    ',',
    $ids
);

$stmt = $pdo->query("
    SELECT *
    FROM equipment
    WHERE id IN ($idList)
    ORDER BY reporting_marks, road_number
");

$equipmentList = $stmt->fetchAll(
    PDO::FETCH_ASSOC
);

if (!$equipmentList) {

    die('No equipment found.');

}

/*
|--------------------------------------------------------------------------
| SVG TEMPLATE FILE
|--------------------------------------------------------------------------
*/

$tripleSvgFile =
    __DIR__ .
    '/../templates/car card triple.svg';

/*
|--------------------------------------------------------------------------
| VERIFY SVG TEMPLATE
|--------------------------------------------------------------------------
*/

if (!file_exists($tripleSvgFile)) {

    die(
        'Missing triple card SVG template.'
    );

}

/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/
function esc($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}

function photoFilename($car)
{
    if (
        empty($car)
        || empty($car['photo_filename'])
    ) {

        return '';

    }

    return '../uploads/' .
        $car['photo_filename'];
}

function replaceFirstPlaceholder(
    $svg,
    $placeholder,
    $value
)
{
    return preg_replace(
        '/>' . preg_quote($placeholder, '/') . '</',
        '>' . $value . '<',
        $svg,
        1
    );
}

function buildPhotoTag($car)
{
    $photo = photoFilename($car);

    if ($photo === '') {

        return '';

    }

    return '
<image
width="70"
height="95"
preserveAspectRatio="xMidYMid meet"
href="' .
esc($photo) .
'?v=' .
time() .
'" />
';
}

function buildCardData($car)
{
    if (!$car) {

        return [

            'card_info'     => '',

            'card_info_svg' => '',

            'photo'         => ''

        ];

    }

    $reportingMarks =
        trim(
            $car['reporting_marks']
            ?? ''
        );

    $roadNumber =
        trim(
            $car['road_number']
            ?? ''
        );

    $type =
        trim(
            $car['equipment_type']
            ?? ''
        );

    $length =
        !empty($car['length_ft'])
            ? $car['length_ft'] . "'"
            : '';

    $color =
        trim(
            $car['color']
            ?? ''
        );

    $notes =
        trim(
            $car['notes']
            ?? ''
        );

    $cardInfo =
        "Rpt Marks: {$reportingMarks}\n" .
        "Car #: {$roadNumber}\n" .
        "Type: {$type}" .
        ($length ? " {$length}" : "") .
        "\n" .
        "Color: {$color}\n" .
        "Notes:";

    $cardInfoSvg = '';

    $lines = explode("\n", $cardInfo);

    foreach ($lines as $i => $line)
    {
        if ($i == 0)
        {
            $cardInfoSvg .=
                '<tspan x="0" dy="0">' .
                htmlspecialchars($line) .
                '</tspan>';
        }
        else
        {
            $cardInfoSvg .=
                '<tspan x="0" dy="12">' .
                htmlspecialchars($line) .
                '</tspan>';
        }
    }

    return [

        'card_info'     => $cardInfo,

        'card_info_svg' => $cardInfoSvg,

        'photo'         => buildPhotoTag($car)

    ];
}

/*
|--------------------------------------------------------------------------
| RENDER PAGE
|--------------------------------------------------------------------------
*/
function renderTriplePage(
    $topCar,
    $middleCar,
    $bottomCar
)
{
    global $tripleSvgFile;

    $svg = file_get_contents(
        $tripleSvgFile
    );

    $cards = [

        buildCardData($topCar),
        buildCardData($middleCar),
        buildCardData($bottomCar)

    ];

    /*
    ------------------------------------------------------------
    CARD_INFO PLACEHOLDERS
    ------------------------------------------------------------
    */

    foreach ($cards as $card)
    {

        $svg = replaceFirstPlaceholder(
    $svg,
    'CARD_INFO',
    $card['card_info_svg']
);

    }

    /*
    ------------------------------------------------------------
    REMOVE PHOTO_BOX PLACEHOLDERS
    ------------------------------------------------------------
    */

    for ($i = 0; $i < 3; $i++)
    {

        $svg = preg_replace(

            '/>PHOTO_BOX</',

            '><',

            $svg,

            1

        );

    }

    /*
    ------------------------------------------------------------
    ADD IMAGES
    ------------------------------------------------------------
    */

    $images = '';

    /*
    ------------------------------------------------------------
    TOP CARD
    ------------------------------------------------------------
    */

    if (

        $topCar &&
        photoFilename($topCar) != ''

    )
    {

        $images .= '

<image
x="325"
y="72"
width="150"
height="190"
preserveAspectRatio="xMidYMid meet"
href="' .

esc(
    photoFilename($topCar)
) .

'?v=' .

time()

. '" />

';

    }

    /*
    ------------------------------------------------------------
    LEFT CARD
    ------------------------------------------------------------
    */

    if (

        $middleCar &&
        photoFilename($middleCar) != ''

    )
    {

        $images .= '

<image
x="75"
y="216"
width="160"
height="200"
transform="rotate(180 190 380)"
preserveAspectRatio="xMidYMid meet"
href="' .

esc(
    photoFilename($middleCar)
) .

'?v=' .

time()

. '" />

';

    }

    /*
    ------------------------------------------------------------
    RIGHT CARD
    ------------------------------------------------------------
    */

    if (

        $bottomCar &&
        photoFilename($bottomCar) != ''

    )
    {

        $images .= '

<image
x="412"
y="217"
width="160"
height="200"
transform="rotate(180 530 380)"
preserveAspectRatio="xMidYMid meet"
href="' .

esc(
    photoFilename($bottomCar)
) .

'?v=' .

time()

. '" />

';

    }

    /*
    ------------------------------------------------------------
    APPEND IMAGES TO SVG
    ------------------------------------------------------------
    */

    $svg = str_replace(

        '</svg>',

        $images . PHP_EOL . '</svg>',

        $svg

    );

    return $svg;
}

/*
|--------------------------------------------------------------------------
| HTML
|--------------------------------------------------------------------------
*/
?>
<?php include '../includes/header.php'; ?>

<title>Car Card Print</title>

<style>

@page {

    size: letter landscape;
    margin: 0;

}

html,
body {

    margin: 0;
    padding: 0;
    background: #f4f5f7;
    font-family: Arial, Helvetica, sans-serif;

}

.preview-wrapper {

    max-width: 1200px;
    margin: 0 auto;
    padding-bottom: 40px;

}

.page {

    text-align: center;
    margin-bottom: 30px;

    page-break-after: always;

}

.page svg {

    display: block;
    margin: auto;

    background: white;

    box-shadow:
        0 2px 8px rgba(0,0,0,.15);

}

.toolbar {

    background: white;

    padding: 20px;

    margin-bottom: 25px;

    border-bottom: 1px solid #ddd;

}

.toolbar h1 {

    margin: 0 0 5px 0;

    font-size: 28px;

}

.toolbar p {

    margin: 0 0 20px 0;

    color: #666;

}

.print-button {

    background: #0d6efd;

    color: white;

    border: none;

    border-radius: 8px;

    padding: 12px 20px;

    font-size: 16px;

    font-weight: bold;

    cursor: pointer;

}

.print-button:hover {

    background: #0b5ed7;

}

.back-button {

    display: inline-block;

    margin-left: 12px;

    background: #6c757d;

    color: white;

    text-decoration: none;

    border-radius: 8px;

    padding: 12px 20px;

    font-size: 16px;

    font-weight: bold;

}

.back-button:hover {

    background: #5a6268;

    color: white;

    text-decoration: none;

}

@media print {

    .navbar,
    .toolbar {

        display: none !important;

    }

    body {

        background: white;

    }

    .preview-wrapper {

        max-width: none;

        margin: 0;

        padding: 0;

    }

    .page {

        margin: 0;

    }

    .page svg {

        box-shadow: none;

    }

}

</style>

</head>

<body>

<?php include '../includes/navbar.php'; ?>

<div class="toolbar">

    <div class="container">

        <h1>
            Car Card Printing
        </h1>

        <p>
            <?= count($equipmentList) ?>
            cars selected
        </p>

        <button
            class="print-button"
            onclick="window.print();">

            🖨 Print Car Cards

        </button>

        <a
            href="list.php"
            class="back-button">

            ← Back to Equipment

        </a>

    </div>

</div>

<div class="preview-wrapper">

<?php

$totalCars = count(
    $equipmentList
);

for (
    $i = 0;
    $i < $totalCars;
    $i += 3
)
{

    $topCar =
        $equipmentList[$i]
        ?? null;

    $middleCar =
        $equipmentList[$i + 1]
        ?? null;

    $bottomCar =
        $equipmentList[$i + 2]
        ?? null;

?>

<div class="page">

<?php

echo renderTriplePage(

    $topCar,
    $middleCar,
    $bottomCar

);

?>

</div>

<?php

}

?>

</div>

<script>

window.addEventListener(

    'load',

    function () {

        // window.print();

    }

);

</script>

</body>
</html>
<?php

/*
==========================================================================
NOTES
==========================================================================

MASTER SVG

    ../templates/car card triple.svg

CURRENT PLACEHOLDERS

    CARD_INFO
    PHOTO_BOX

PRINT SIZE

    Letter Landscape

    11 x 8.5

ONE PAGE CONTAINS

    Top Card
    Left Card
    Right Card

IMAGE POSITIONS

TOP CARD

    x="325"
    y="72"
    width="150"
    height="190"

LEFT CARD

    x="75"
    y="216"
    width="160"
    height="200"

RIGHT CARD

    x="412"
    y="217"
    width="160"
    height="200"

FUTURE PLACEHOLDERS

    RETURN_TO
    OWNER
    AAR_TYPE
    LOAD_STATUS
    BUILD_DATE
    QR_CODE
    BAR_CODE

FUTURE FEATURES

    Reporting Mark Logos
    Single Card Template
    Double Card Template
    QR Codes
    JMRI Compatibility
    Interactive Operations
    Switch Lists
    Waybills
    Print PDF Option

==========================================================================

CARD_INFO FORMAT

Rpt Marks: AM
Car #: 2339
Type: Covered Hopper 50'
Color: Brown
Notes:

==========================================================================

TODO

1. Convert CARD_INFO to multiline SVG tspans.

2. Add RETURN_TO placeholder.

3. Optional reporting mark logo.

4. Optional owner.

5. QR code support.

6. PDF download button.

==========================================================================

END OF FILE
==========================================================================
*/
?>