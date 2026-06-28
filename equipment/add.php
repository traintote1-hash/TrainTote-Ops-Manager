<?php

session_start();

require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {

    header('Location: ../login.php');

    exit;

}

/*
|--------------------------------------------------------------------------
| Railroad
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("

SELECT *

FROM railroads

WHERE user_id = ?

LIMIT 1

");

$stmt->execute([

    $_SESSION['user_id']

]);

$railroad =

    $stmt->fetch(

        PDO::FETCH_ASSOC

    );

if (

    !$railroad

) {

    die(

        'No railroad found.'

    );

}

/*
|--------------------------------------------------------------------------
| Industries
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("

SELECT

    id,

    industry_name

FROM industries

WHERE railroad_id = ?

ORDER BY industry_name

");

$stmt->execute([

    $railroad['id']

]);

$industries =

    $stmt->fetchAll(

        PDO::FETCH_ASSOC

    );

/*
|--------------------------------------------------------------------------
| AI Session Data
|--------------------------------------------------------------------------
*/

$aiData =

    $_SESSION['ai_data']

    ?? [];

$aiPhoto =

    $_SESSION['ai_photo']

    ?? '';

/*
|--------------------------------------------------------------------------
| Equipment Classes
|--------------------------------------------------------------------------
*/

$equipmentClasses = [

    'Freight Car',

    'Locomotive',

    'Passenger Car',

    'Caboose',

    'MOW',

    'Other'

];

/*
|--------------------------------------------------------------------------
| Freight Types
|--------------------------------------------------------------------------
*/

$freightTypes = [

    'Boxcar',

    'Covered Hopper',

    'Open Hopper',

    'Tank Car',

    'Flatcar',

    'Centerbeam Flatcar',

    'Bulkhead Flatcar',

    'Intermodal Flatcar',

    'Well Car',

    'Gondola',

    'Refrigerator Car',

    'Mechanical Refrigerator Car',

    'Autorack',

    'Coil Car',

    'Stock Car',

    'Depressed Center Flatcar',

    'Spine Car',

    'Other'

];

/*
|--------------------------------------------------------------------------
| Locomotive Types
|--------------------------------------------------------------------------
*/

$locomotiveTypes = [

    'Diesel',

    'Steam',

    'Electric',

    'Gas Turbine',

    'Slug',

    'Other'

];

/*
|--------------------------------------------------------------------------
| Passenger Types
|--------------------------------------------------------------------------
*/

$passengerTypes = [

    'Coach',

    'Combine',

    'Baggage',

    'RPO',

    'Diner',

    'Sleeper',

    'Observation',

    'Business Car',

    'Dome Car',

    'Other'

];

/*
|--------------------------------------------------------------------------
| Caboose Types
|--------------------------------------------------------------------------
*/

$cabooseTypes = [

    'Cupola',

    'Bay Window',

    'Transfer Caboose',

    'Wide Vision',

    'Other'

];

/*
|--------------------------------------------------------------------------
| MOW Types
|--------------------------------------------------------------------------
*/

$mowTypes = [

    'Crane',

    'Ballast Hopper',

    'Snow Plow',

    'Jordan Spreader',

    'Tool Car',

    'Track Geometry Car',

    'Scale Test Car',

    'Water Car',

    'Rail Train Car',

    'Other'

];

/*
|--------------------------------------------------------------------------
| Manufacturers
|--------------------------------------------------------------------------
*/

$manufacturers = [

    'Athearn',

    'Atlas',

    'Rapido',

    'ScaleTrains',

    'InterMountain',

    'Walthers',

    'Broadway Limited',

    'Bowser',

    'Bachmann',

    'Proto 2000',

    'ExactRail',

    'Accurail',

    'Roundhouse',

    'Kato',

    'Lionel',

    'MTH',

    'Other'

];

/*
|--------------------------------------------------------------------------
| Existing Values
|--------------------------------------------------------------------------
*/

$reporting_marks =

    $_POST['reporting_marks']

    ?? $aiData['reporting_marks']

    ?? '';

$road_number =

    $_POST['road_number']

    ?? $aiData['road_number']

    ?? '';

$road_name =

    $_POST['road_name']

    ?? $aiData['road_name']

    ?? '';

$equipment_class =

    $_POST['equipment_class']

    ?? $aiData['equipment_class']

    ?? '';

$equipment_type =

    $_POST['equipment_type']

    ?? $aiData['equipment_type']

    ?? '';

$prototype =

    $_POST['prototype']

    ?? $aiData['prototype']

    ?? '';

$manufacturer =

    $_POST['manufacturer']

    ?? $aiData['manufacturer']

    ?? '';

$length_ft =

    $_POST['length_ft']

    ?? $aiData['length_ft']

    ?? '';

$color =

    $_POST['color']

    ?? $aiData['color']

    ?? '';

$scale =

    $_POST['scale']

    ?? $aiData['scale']

    ?? 'HO';

$service =

    $_POST['service']

    ?? $aiData['service']

    ?? '';

$load_status =

    $_POST['load_status']

    ?? $aiData['load_status']

    ?? 'Empty';

$operations_service =

    $_POST['operations_service']

    ?? $aiData['operations_service']

    ?? '';

$notes =

    $_POST['notes']

    ?? $aiData['notes']

    ?? '';

$errors = [];

/*
|--------------------------------------------------------------------------
| Save Equipment
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /*
    |--------------------------------------------------------------------------
    | Clean Inputs
    |--------------------------------------------------------------------------
    */

    $reporting_marks = strtoupper(
        trim($reporting_marks)
    );

    $road_number = trim(
        $road_number
    );

    $road_name = trim(
        $road_name
    );

    $equipment_class = trim(
        $equipment_class
    );

    $equipment_type = trim(
        $equipment_type
    );

    $custom_type = trim(
        $_POST['custom_type']
        ?? ''
    );

    $prototype = trim(
        $prototype
    );

    $manufacturer = trim(
        $manufacturer
    );

    $custom_manufacturer = trim(
        $_POST['custom_manufacturer']
        ?? ''
    );

    $length_ft = trim(
        $length_ft
    );

    $color = trim(
        $color
    );

    $scale = trim(
        $scale
    );

    $service = trim(
        $service
    );

    $load_status = trim(
        $load_status
    );

    $operations_service = trim(
        $operations_service
    );

    $current_industry_id =

        !empty(
            $_POST['current_industry_id']
        )

        ?

        (int)

        $_POST['current_industry_id']

        :

        null;

    $current_track = trim(

        $_POST['current_track']

        ?? ''

    );

    $notes = trim(
        $notes
    );

    /*
    |--------------------------------------------------------------------------
    | Replace Other
    |--------------------------------------------------------------------------
    */

    if (

        $equipment_type === 'Other'

        &&

        $custom_type !== ''

    ) {

        $equipment_type =

            substr(

                $custom_type,

                0,

                30

            );

    }

    if (

        $manufacturer === 'Other'

        &&

        $custom_manufacturer !== ''

    ) {

        $manufacturer =

            substr(

                $custom_manufacturer,

                0,

                30

            );

    }

    /*
    |--------------------------------------------------------------------------
    | Character Limits
    |--------------------------------------------------------------------------
    */

    $reporting_marks =
        substr(
            $reporting_marks,
            0,
            20
        );

    $road_number =
        substr(
            $road_number,
            0,
            20
        );

    $road_name =
        substr(
            $road_name,
            0,
            100
        );

    $prototype =
        substr(
            $prototype,
            0,
            50
        );

    $manufacturer =
        substr(
            $manufacturer,
            0,
            30
        );

    $length_ft =
        substr(
            $length_ft,
            0,
            3
        );

    $color =
        substr(
            $color,
            0,
            20
        );

    $service =
        substr(
            $service,
            0,
            50
        );

    $current_track =
        substr(
            $current_track,
            0,
            50
        );

    $operations_service =
        substr(
            $operations_service,
            0,
            255
        );

    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

    if ($reporting_marks === '') {

        $errors[] =
            'Reporting marks required.';

    }

    if ($road_number === '') {

        $errors[] =
            'Road number required.';

    }

    if ($equipment_class === '') {

        $errors[] =
            'Equipment class required.';

    }

    if ($equipment_type === '') {

        $errors[] =
            'Equipment type required.';

    }

    /*
    |--------------------------------------------------------------------------
    | Save Equipment
    |--------------------------------------------------------------------------
    */

    if (empty($errors)) {

        $stmt = $pdo->prepare("

        INSERT INTO equipment (

            railroad_id,

            reporting_marks,

            road_number,

            road_name,

            equipment_class,

            equipment_type,

            prototype,

            manufacturer,

            length_ft,

            color,

            scale,

            service,

            operations_service,

            load_status,

            current_industry_id,

            current_track,

            notes

        )

        VALUES (

            ?,?,?,?,?,?,
            ?,?,?,?,?,?,
            ?,?,?,?,?

        )

        ");

        $stmt->execute([

            $railroad['id'],

            $reporting_marks,

            $road_number,

            $road_name,

            $equipment_class,

            $equipment_type,

            $prototype,

            $manufacturer,

            $length_ft,

            $color,

            $scale,

            $service,

            $operations_service,

            $load_status,

            $current_industry_id,

            $current_track,

            $notes

        ]);

        /*
        |--------------------------------------------------------------------------
        | Clear AI Sessions
        |--------------------------------------------------------------------------
        */

        unset(

            $_SESSION['ai_data']

        );

        unset(

            $_SESSION['ai_photo']

        );

        /*
        |--------------------------------------------------------------------------
        | Redirect
        |--------------------------------------------------------------------------
        */

        header(

            'Location: list.php'

        );

        exit;

    }

}

?>

<?php include '../includes/header.php'; ?>

<title>

Add Equipment

</title>

<style>

.section-card {

    border-radius: 12px;

    margin-bottom: 25px;

}

.section-card .card-header {

    font-weight: 600;

    font-size: 1.1rem;

}

</style>

</head>

<body>

<?php include '../includes/navbar.php'; ?>

<div class="container mt-4 mb-5">

<form method="post" enctype="multipart/form-data">

<h1 class="mb-4">

Add Equipment

</h1>

<?php if (!empty($errors)): ?>

<div class="alert alert-danger">

<ul class="mb-0">

<?php foreach ($errors as $error): ?>

<li>

<?php echo htmlspecialchars($error); ?>

</li>

<?php endforeach; ?>

</ul>

</div>

<?php endif; ?>

<!-- ====================================================== -->
<!-- PHOTO -->
<!-- ====================================================== -->

<div class="card section-card">

<div class="card-header bg-dark text-white">

Photo

</div>

<div class="card-body">

<?php if (!empty($aiPhoto)): ?>

<div class="text-center mb-4">

<img
src="../uploads/temp/<?php echo htmlspecialchars($aiPhoto); ?>"
class="img-fluid rounded border"
style="
max-height:300px;
max-width:100%;
">

</div>

<div class="text-center">

<a
href="../photo_editor/edit.php?type=temp"
class="btn btn-primary">

Edit Photo

</a>

<a
href="#"
class="btn btn-secondary">

Replace Photo

</a>

<a
href="#"
class="btn btn-danger">

Remove Photo

</a>

</div>

<?php else: ?>

<div class="row">

<div class="col-md-6">

<label class="form-label">

Photo

</label>

<input
type="file"
name="photo"
class="form-control"
accept=".jpg,.jpeg,.png,.webp,.gif,.bmp,.heic,.heif,.avif">

<div class="form-text">

JPG, JPEG, PNG, WEBP, GIF, BMP, HEIC, HEIF, AVIF

</div>

</div>

</div>

<?php endif; ?>

</div>

</div>

<!-- ====================================================== -->
<!-- GENERAL INFORMATION -->
<!-- ====================================================== -->

<div class="card section-card">

<div class="card-header bg-dark text-white">

Railroad Information

</div>

<div class="card-body">

<div class="row">

<div class="col-md-4 mb-3">

<label class="form-label">

Reporting Marks

</label>

<input
type="text"
name="reporting_marks"
maxlength="20"
class="form-control"
value="<?php echo htmlspecialchars($reporting_marks); ?>">

</div>

<div class="col-md-4 mb-3">

<label class="form-label">

Road Number

</label>

<input
type="text"
name="road_number"
maxlength="20"
class="form-control"
value="<?php echo htmlspecialchars($road_number); ?>">

</div>

<div class="col-md-4 mb-3">

<label class="form-label">

Road Name

</label>

<input
type="text"
name="road_name"
maxlength="100"
class="form-control"
value="<?php echo htmlspecialchars($road_name); ?>">

</div>

</div>

</div>

</div>

<!-- ====================================================== -->
<!-- CLASSIFICATION -->
<!-- ====================================================== -->

<div class="card section-card">

<div class="card-header bg-dark text-white">

Classification

</div>

<div class="card-body">

<div class="row">

<div class="col-md-2 mb-3">

<label class="form-label">

Equipment Class

</label>

<select
name="equipment_class"
id="equipment_class"
class="form-select">

<option value="">

Select Class

</option>

<?php foreach ($equipmentClasses as $class): ?>

<option
value="<?php echo $class; ?>"
<?php if ($equipment_class === $class) echo 'selected'; ?>>

<?php echo $class; ?>

</option>

<?php endforeach; ?>

</select>

</div>

<div class="col-md-2 mb-3">

<label class="form-label">

Equipment Type

</label>

<select
name="equipment_type"
id="equipment_type"
class="form-select">

<option value="">

Select Type

</option>

</select>

</div>

<div class="col-md-2 mb-3">

<label class="form-label">

Length

</label>

<input
type="text"
name="length_ft"
class="form-control"
value="<?php echo htmlspecialchars($length_ft); ?>">

</div>

<div class="col-md-4 mb-3">

<label class="form-label">

Model / Subtype

</label>

<input
type="text"
name="prototype"
maxlength="50"
class="form-control"
value="<?php echo htmlspecialchars($prototype); ?>">

</div>

</div>

<div
class="row"
id="customTypeDiv"
style="display:none;">

<div class="col-md-4 mb-3">

<label class="form-label">

Custom Type

</label>

<input
type="text"
name="custom_type"
maxlength="30"
class="form-control">

</div>

</div>

</div>

</div>

<!-- ====================================================== -->
<!-- PHYSICAL CHARACTERISTICS -->
<!-- ====================================================== -->

<div class="card section-card">

<div class="card-header bg-dark text-white">

Physical Characteristics

</div>

<div class="card-body">

<div class="row">

<div class="col-md-4 mb-3">

<label class="form-label">

Manufacturer

</label>

<select
name="manufacturer"
id="manufacturer"
class="form-select">

<option value="">

Select Manufacturer

</option>

<?php foreach ($manufacturers as $mfg): ?>

<option
value="<?php echo $mfg; ?>"
<?php if ($manufacturer === $mfg) echo 'selected'; ?>>

<?php echo $mfg; ?>

</option>

<?php endforeach; ?>

</select>

</div>

<div
class="col-md-2 mb-3"
id="customManufacturerDiv"
style="display:none;">

<label class="form-label">

Custom Manufacturer

</label>

<input
type="text"
name="custom_manufacturer"
maxlength="30"
class="form-control">

</div>



<div class="col-md-2 mb-3">

<label class="form-label">

Scale

</label>

<select
name="scale"
class="form-select">

<option value="HO" <?php if ($scale==='HO') echo 'selected'; ?>>HO</option>

<option value="N" <?php if ($scale==='N') echo 'selected'; ?>>N</option>

<option value="O" <?php if ($scale==='O') echo 'selected'; ?>>O</option>

<option value="S" <?php if ($scale==='S') echo 'selected'; ?>>S</option>

<option value="G" <?php if ($scale==='G') echo 'selected'; ?>>G</option>

<option value="Z" <?php if ($scale==='Z') echo 'selected'; ?>>Z</option>

</select>

</div>

<div class="col-md-2 mb-3">

<label class="form-label">

Color

</label>

<input
type="text"
name="color"
maxlength="20"
class="form-control"
value="<?php echo htmlspecialchars($color); ?>">

</div>

</div>

</div>

</div>

<!-- ====================================================== -->
<!-- OPERATIONS -->
<!-- ====================================================== -->

<div class="card section-card">

<div class="card-header bg-dark text-white">

Operations

</div>

<div class="card-body">

<div class="row">

<div class="col-md-2 mb-3">

<label class="form-label">

Load Status

</label>

<select
name="load_status"
class="form-select">

<option
value="Empty"
<?php if ($load_status === 'Empty') echo 'selected'; ?>>

Empty

</option>

<option
value="Loaded"
<?php if ($load_status === 'Loaded') echo 'selected'; ?>>

Loaded

</option>

</select>

</div>

<div class="col-md-4 mb-3">

<label class="form-label">

Current Location

</label>

<select
name="current_industry_id"
class="form-select">

<option value="">

Select Industry

</option>

<?php foreach ($industries as $industry): ?>

<option
value="<?php echo $industry['id']; ?>">

<?php echo htmlspecialchars(
    $industry['industry_name']
); ?>

</option>

<?php endforeach; ?>

</select>

</div>

<div class="col-md-3 mb-3">

<label class="form-label">

Track / Spot

</label>

<input
type="text"
name="current_track"
maxlength="50"
class="form-control"
placeholder="Track 1 Spot 2">

</div>

<div class="col-md-3 mb-3">

<label class="form-label">

Service

</label>

<input
type="text"
name="service"
maxlength="50"
class="form-control"
value="<?php echo htmlspecialchars($service); ?>">

</div>

<div class="col-md-6 mb-3">

<label class="form-label">

Operations Service

</label>

<input
type="text"
name="operations_service"
maxlength="255"
class="form-control"
placeholder="General Freight, Grain, Sand / Gravel, Cement, Scrap Metal, Vegetable Oil, Fuel, Propane, Lumber, Paper"
value="<?php echo htmlspecialchars($operations_service); ?>">

</div>

</div>

</div>

</div>

<!-- ====================================================== -->
<!-- NOTES -->
<!-- ====================================================== -->

<div class="card section-card">

<div class="card-header bg-dark text-white">

Notes

</div>

<div class="card-body">

<textarea
name="notes"
rows="5"
class="form-control"><?php echo htmlspecialchars($notes); ?></textarea>

</div>

</div>

<!-- ====================================================== -->
<!-- BUTTONS -->
<!-- ====================================================== -->

<div class="text-center mb-5">

<button
type="submit"
class="btn btn-success btn-lg">

Save Equipment

</button>

<a
href="list.php"
class="btn btn-secondary btn-lg ms-2">

Cancel

</a>

</div>

</form>

</div>

<?php include '../includes/footer.php'; ?>

<script>

/*
|--------------------------------------------------------------------------
| Type Lists
|--------------------------------------------------------------------------
*/

const freightTypes = [

"Boxcar",
"Covered Hopper",
"Open Hopper",
"Tank Car",
"Flatcar",
"Centerbeam Flatcar",
"Bulkhead Flatcar",
"Intermodal Flatcar",
"Well Car",
"Gondola",
"Refrigerator Car",
"Mechanical Refrigerator Car",
"Autorack",
"Coil Car",
"Stock Car",
"Depressed Center Flatcar",
"Spine Car",
"Other"

];

const locomotiveTypes = [

"Diesel",
"Steam",
"Electric",
"Gas Turbine",
"Slug",
"Other"

];

const passengerTypes = [

"Coach",
"Combine",
"Baggage",
"RPO",
"Diner",
"Sleeper",
"Observation",
"Business Car",
"Dome Car",
"Other"

];

const cabooseTypes = [

"Cupola",
"Bay Window",
"Transfer Caboose",
"Wide Vision",
"Other"

];

const mowTypes = [

"Crane",
"Ballast Hopper",
"Snow Plow",
"Jordan Spreader",
"Tool Car",
"Track Geometry Car",
"Scale Test Car",
"Water Car",
"Rail Train Car",
"Other"

];

/*
|--------------------------------------------------------------------------
| Elements
|--------------------------------------------------------------------------
*/

const equipmentClass =
document.getElementById(
'equipment_class'
);

const equipmentType =
document.getElementById(
'equipment_type'
);

const customTypeDiv =
document.getElementById(
'customTypeDiv'
);

const manufacturer =
document.getElementById(
'manufacturer'
);

const customManufacturerDiv =
document.getElementById(
'customManufacturerDiv'
);

/*
|--------------------------------------------------------------------------
| Selected Type
|--------------------------------------------------------------------------
*/

const selectedEquipmentType =
"<?php echo addslashes($equipment_type); ?>";

/*
|--------------------------------------------------------------------------
| Populate Types
|--------------------------------------------------------------------------
*/

function populateTypes() {

equipmentType.innerHTML =
'<option value="">Select Type</option>';

let types = [];

switch (equipmentClass.value) {

case 'Freight Car':

types = freightTypes;

break;

case 'Locomotive':

types = locomotiveTypes;

break;

case 'Passenger Car':

types = passengerTypes;

break;

case 'Caboose':

types = cabooseTypes;

break;

case 'MOW':

types = mowTypes;

break;

}

types.forEach(

function(type){

let option =
document.createElement(
'option'
);

option.value = type;

option.text = type;

if (

type ===
selectedEquipmentType

) {

option.selected =
true;

}

equipmentType.add(
option
);

}

);

}

/*
|--------------------------------------------------------------------------
| Equipment Class Changed
|--------------------------------------------------------------------------
*/

equipmentClass.addEventListener(

'change',

function(){

populateTypes();

customTypeDiv.style.display =
'none';

}

);

/*
|--------------------------------------------------------------------------
| Equipment Type Changed
|--------------------------------------------------------------------------
*/

equipmentType.addEventListener(

'change',

function(){

if (

equipmentType.value ===
'Other'

) {

customTypeDiv.style.display =
'';

}

else {

customTypeDiv.style.display =
'none';

}

}

);

/*
|--------------------------------------------------------------------------
| Manufacturer Changed
|--------------------------------------------------------------------------
*/

manufacturer.addEventListener(

'change',

function(){

if (

manufacturer.value ===
'Other'

) {

customManufacturerDiv.style.display =
'';

}

else {

customManufacturerDiv.style.display =
'none';

}

}

);

/*
|--------------------------------------------------------------------------
| Reporting Marks Uppercase
|--------------------------------------------------------------------------
*/

document.querySelector(

'[name="reporting_marks"]'

).addEventListener(

'input',

function(){

this.value =
this.value.toUpperCase();

}

);

/*
|--------------------------------------------------------------------------
| Character Counters
|--------------------------------------------------------------------------
*/

function setupCounter(

selector,

maxLength

) {

const field =
document.querySelector(
selector
);

if (!field) {

return;

}

const counter =
document.createElement(
'div'
);

counter.className =
'form-text text-end';

field.parentNode.appendChild(
counter
);

function updateCounter() {

counter.innerHTML =

field.value.length +

' / ' +

maxLength;

}

field.addEventListener(

'input',

updateCounter

);

updateCounter();

}

setupCounter(

'[name="prototype"]',

50

);

setupCounter(

'[name="custom_type"]',

30

);

setupCounter(

'[name="color"]',

20

);

setupCounter(

'[name="notes"]',

1000

);

/*
|--------------------------------------------------------------------------
| Length Numeric
|--------------------------------------------------------------------------
*/

document.querySelector(

'[name="length_ft"]'

).addEventListener(

'input',

function(){

this.value =

this.value.replace(

/[^0-9]/g,

''

);

}

);

/*
|--------------------------------------------------------------------------
| Initial Setup
|--------------------------------------------------------------------------
*/

populateTypes();

if (

equipmentType.value ===
'Other'

) {

customTypeDiv.style.display =
'';

}

else {

customTypeDiv.style.display =
'none';

}

if (

manufacturer.value ===
'Other'

) {

customManufacturerDiv.style.display =
'';

}

else {

customManufacturerDiv.style.display =
'none';

}

</script>