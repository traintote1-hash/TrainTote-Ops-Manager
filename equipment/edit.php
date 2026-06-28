<?php

session_start();

require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {

    header('Location: ../login.php');

    exit;

}

if (!isset($_GET['id'])) {

    die('Equipment ID missing.');

}

$id = (int)$_GET['id'];

/*
|--------------------------------------------------------------------------
| Load Equipment
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("

SELECT e.*

FROM equipment e

JOIN railroads r

ON e.railroad_id = r.id

WHERE e.id = ?

AND r.user_id = ?

LIMIT 1

");

$stmt->execute([

    $id,

    $_SESSION['user_id']

]);

$equipment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$equipment) {

    die('Equipment not found.');

}

/*
|--------------------------------------------------------------------------
| Load Railroad
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

$railroad = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$railroad) {

    die('No railroad found.');

}

/*
|--------------------------------------------------------------------------
| Load Industries
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

$industries = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Operations Service Options
|--------------------------------------------------------------------------
*/

$operationsServiceOptions = [
    'Covered Hopper' => [
        'Grain',
        'Cement',
        'Sand / Gravel',
        'Plastic Pellets',
        'Feed',
        'Flour',
        'Fertilizer'
    ],
    'Open Hopper' => [
        'Coal',
        'Gravel',
        'Sand',
        'Aggregate',
        'Ore',
        'Ballast'
    ],
    'Gondola' => [
        'Scrap Metal',
        'Steel / Pipe',
        'Rail',
        'Aggregate',
        'MOW / Riprap'
    ],
    'Tank Car' => [
        'Vegetable Oil',
        'Food Grade Liquid',
        'Fuel',
        'Propane',
        'Chemicals',
        'Asphalt',
        'Corn Syrup'
    ],
    'Boxcar' => [
        'General Freight',
        'Paper',
        'Food Products',
        'Beer',
        'Auto Parts',
        'Furniture',
        'Appliances'
    ],
    'Refrigerator Car' => [
        'General Freight',
        'Food Products'
    ],
    'Mechanical Refrigerator Car' => [
        'General Freight',
        'Food Products'
    ],
    'Flatcar' => [
        'Lumber',
        'Building Materials',
        'Pipe',
        'Machinery',
        'Steel'
    ],
    'Bulkhead Flatcar' => [
        'Lumber',
        'Building Materials',
        'Pipe',
        'Machinery',
        'Steel'
    ],
    'Centerbeam Flatcar' => [
        'Lumber',
        'Building Materials',
        'Pipe',
        'Machinery',
        'Steel'
    ]
];

function ttAddOperationsServiceOption(array &$options, string $equipmentType, string $serviceName): void
{
    $equipmentType = trim($equipmentType);
    $serviceName = trim($serviceName);

    if ($equipmentType === '' || $serviceName === '') {
        return;
    }

    if (!isset($options[$equipmentType])) {
        $options[$equipmentType] = [];
    }

    foreach ($options[$equipmentType] as $existingServiceName) {
        if (strcasecmp($existingServiceName, $serviceName) === 0) {
            return;
        }
    }

    $options[$equipmentType][] = $serviceName;
}

$stmt = $pdo->prepare("
    SELECT
        equipment_type,
        service_name
    FROM operations_service_options
    WHERE railroad_id = ?
    OR is_default = 1
    ORDER BY equipment_type, service_name
");

$stmt->execute([
    $railroad['id']
]);

foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $serviceOption) {
    ttAddOperationsServiceOption(
        $operationsServiceOptions,
        $serviceOption['equipment_type'] ?? '',
        $serviceOption['service_name'] ?? ''
    );
}

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

$reporting_marks = $_POST['reporting_marks']
    ?? $equipment['reporting_marks']
    ?? '';

$road_number = $_POST['road_number']
    ?? $equipment['road_number']
    ?? '';

$road_name = $_POST['road_name']
    ?? $equipment['road_name']
    ?? '';

$equipment_class = $_POST['equipment_class']
    ?? $equipment['equipment_class']
    ?? '';

$equipment_type = $_POST['equipment_type']
    ?? $equipment['equipment_type']
    ?? '';

$prototype = $_POST['prototype']
    ?? $equipment['prototype']
    ?? '';

$manufacturer = $_POST['manufacturer']
    ?? $equipment['manufacturer']
    ?? '';

$length_ft = $_POST['length_ft']
    ?? $equipment['length_ft']
    ?? '';

$color = $_POST['color']
    ?? $equipment['color']
    ?? '';

$scale = $_POST['scale']
    ?? $equipment['scale']
    ?? 'HO';

$service = $_POST['service']
    ?? $equipment['service']
    ?? '';

$load_status = $_POST['load_status']
    ?? $equipment['load_status']
    ?? 'Empty';

$operations_service = $_POST['operations_service']
    ?? $equipment['operations_service']
    ?? '';

$operations_service_custom = $_POST['operations_service_custom']
    ?? $operations_service;

$current_industry_id = $_POST['current_industry_id']
    ?? $equipment['current_industry_id']
    ?? '';

$current_track = $_POST['current_track']
    ?? $equipment['current_track']
    ?? '';

$notes = $_POST['notes']
    ?? $equipment['notes']
    ?? '';

$errors = [];

/*
|--------------------------------------------------------------------------
| Save Changes
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $reporting_marks = strtoupper(trim($reporting_marks));

    $road_number = trim($road_number);

    $road_name = trim($road_name);

    $equipment_class = trim($equipment_class);

    $equipment_type = trim($equipment_type);

    $prototype = trim($prototype);

    $manufacturer = trim($manufacturer);

    $length_ft = trim($length_ft);

    $color = trim($color);

    $scale = trim($scale);

    $service = trim($service);

    $load_status = trim($load_status);

    $operations_service = trim($operations_service);

    $operations_service_custom = trim($operations_service_custom);

    $operations_service_custom =
        substr(
            $operations_service_custom,
            0,
            100
        );

    $customOperationsServiceEntered =
        $operations_service === 'Other'
        && $operations_service_custom !== '';

    if ($operations_service === 'Other') {
        $operations_service = $operations_service_custom;
    }

    $operations_service =
        substr(
            $operations_service,
            0,
            100
        );

    $current_industry_id =
        !empty($current_industry_id)
        ? (int)$current_industry_id
        : null;

    $current_track = trim($current_track);

    $notes = trim($notes);

    if ($reporting_marks === '') {

        $errors[] = 'Reporting marks required.';

    }

    if ($road_number === '') {

        $errors[] = 'Road number required.';

    }

    if ($equipment_class === '') {

        $errors[] = 'Equipment class required.';

    }

    if ($equipment_type === '') {

        $errors[] = 'Equipment type required.';

    }

    if (empty($errors)) {

        $stmt = $pdo->prepare("

        UPDATE equipment

        SET

            reporting_marks = ?,

            road_number = ?,

            road_name = ?,

            equipment_class = ?,

            equipment_type = ?,

            prototype = ?,

            manufacturer = ?,

            length_ft = ?,

            color = ?,

            scale = ?,

            service = ?,

            operations_service = ?,

            load_status = ?,

            current_industry_id = ?,

            current_track = ?,

            notes = ?

        WHERE id = ?

        ");

        $stmt->execute([

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

            $notes,

            $id

        ]);

        if (
            $customOperationsServiceEntered
            && $operations_service !== ''
            && $equipment_type !== ''
        ) {
            $stmt = $pdo->prepare("
                SELECT id
                FROM operations_service_options
                WHERE railroad_id = ?
                AND LOWER(equipment_type) = LOWER(?)
                AND LOWER(service_name) = LOWER(?)
                LIMIT 1
            ");

            $stmt->execute([
                $railroad['id'],
                $equipment_type,
                $operations_service
            ]);

            if (!$stmt->fetchColumn()) {
                $stmt = $pdo->prepare("
                    INSERT INTO operations_service_options (
                        railroad_id,
                        equipment_type,
                        service_name,
                        is_default
                    )
                    VALUES (?, ?, ?, 0)
                ");

                $stmt->execute([
                    $railroad['id'],
                    $equipment_type,
                    $operations_service
                ]);
            }
        }

        header("Location: view.php?id=$id");

        exit;

    }

}

include '../includes/header.php';

?>

<title>Edit Equipment</title>

<style>

.section-card {

    border-radius: 12px;

    margin-bottom: 25px;

}

.section-card .card-header {

    font-weight: 600;

    font-size: 1.1rem;

}

.equipment-header-photo {

    width: 150px;

    max-height: 120px;

    object-fit: contain;

    background: #fff;

    border: 1px solid #ccc;

    border-radius: 8px;

    padding: 6px;

}

.bottom-buttons {

    margin-bottom: 75px;

}

</style>

</head>

<body>

<?php include '../includes/navbar.php'; ?>

<div class="container mt-4 mb-5">

<form method="post">

<h1 class="mb-4">

Edit Equipment

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
<!-- TOP BUTTONS -->
<!-- ====================================================== -->

<div class="mb-4">

<button
type="submit"
class="btn btn-success">

Save Changes

</button>

<a
href="view.php?id=<?php echo $id; ?>"
class="btn btn-secondary">

Cancel

</a>

</div>

<!-- ====================================================== -->
<!-- PHOTO -->
<!-- ====================================================== -->

<div class="card section-card">

<div class="card-header bg-dark text-white">

Photo

</div>

<div class="card-body">

<div class="d-flex align-items-center gap-4">

<?php if (!empty($equipment['photo_filename'])): ?>

<img

src="../uploads/<?php echo htmlspecialchars($equipment['photo_filename']); ?>"

class="equipment-header-photo"

alt="Equipment Photo">

<?php else: ?>

<img

src="../assets/img/no-photo.png"

class="equipment-header-photo"

alt="No Photo">

<?php endif; ?>

<div>

<a

href="../photo_editor/edit.php?type=equipment&id=<?php echo $equipment['id']; ?>"

class="btn btn-primary">

Edit Photo

</a>

</div>

</div>

</div>

</div>

<!-- ====================================================== -->
<!-- RAILROAD INFORMATION -->
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

<div class="col-md-3 mb-3">

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

<div class="col-md-3 mb-3">

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

<div
class="col-md-3 mb-3"
id="custom_type_div"
style="display:none;">

<label class="form-label">

Custom Type

</label>

<input
type="text"
name="custom_type"
id="custom_type"
maxlength="30"
class="form-control"
value="<?php echo htmlspecialchars($equipment['custom_type'] ?? ''); ?>">

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

<div class="col-md-3 mb-3">

<label class="form-label">

Manufacturer

</label>

<select
name="manufacturer"
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

<div class="col-md-3 mb-3">

<label class="form-label">

Color

</label>

<input
type="text"
name="color"
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

<option value="Empty"
<?php if ($load_status === 'Empty') echo 'selected'; ?>>

Empty

</option>

<option value="Loaded"
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
value="<?php echo $industry['id']; ?>"
<?php if ($current_industry_id == $industry['id']) echo 'selected'; ?>>

<?php echo htmlspecialchars($industry['industry_name']); ?>

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
value="<?php echo htmlspecialchars($current_track); ?>">

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

<select
name="operations_service"
id="operations_service"
class="form-select">

<option value="">

Select Service

</option>

</select>

<div
id="operations_service_custom_div"
class="mt-2"
style="display:none;">

<input
type="text"
name="operations_service_custom"
id="operations_service_custom"
maxlength="100"
class="form-control"
placeholder="Custom operations service"
value="<?php echo htmlspecialchars($operations_service); ?>">

</div>

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
<!-- BOTTOM BUTTONS -->
<!-- ====================================================== -->

<div class="bottom-buttons">

<button
type="submit"
class="btn btn-success btn-lg">

Save Changes

</button>

<a
href="view.php?id=<?php echo $id; ?>"
class="btn btn-secondary btn-lg">

Cancel

</a>

</div>

</form>

</div>

<?php include '../includes/footer.php'; ?>

<script>

const freightTypes = [
'Boxcar',
'Covered Hopper',
'Open Hopper',
'Tank Car',
'Flatcar',
'Centerbeam Flatcar',
'Bulkhead Flatcar',
'Well Car',
'Gondola',
'Autorack',
'Coil Car',
'Refrigerator Car',
'Mechanical Refrigerator Car',
'Stock Car',
'Spine Car',
'Other'
];

const locomotiveTypes = [
'Diesel',
'Steam',
'Electric',
'Gas Turbine',
'Slug',
'Other'
];

const passengerTypes = [
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

const cabooseTypes = [
'Cupola',
'Bay Window',
'Transfer Caboose',
'Wide Vision',
'Other'
];

const mowTypes = [
'Crane',
'Ballast Hopper',
'Snow Plow',
'Jordan Spreader',
'Tool Car',
'Water Car',
'Rail Train Car',
'Other'
];

const classField =
document.getElementById(
'equipment_class'
);

const typeField =
document.getElementById(
'equipment_type'
);

const customDiv =
document.getElementById(
'custom_type_div'
);

const operationsService =
document.getElementById(
'operations_service'
);

const operationsServiceCustomDiv =
document.getElementById(
'operations_service_custom_div'
);

const operationsServiceCustom =
document.getElementById(
'operations_service_custom'
);

const currentType =
"<?php echo addslashes($equipment_type); ?>";

const currentOperationsService =
"<?php echo addslashes($operations_service); ?>";

const operationsServiceOptions =
<?php echo json_encode($operationsServiceOptions, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;

function populateTypes(preserveSelectedService = false) {

typeField.innerHTML =
'<option value="">Select Type</option>';

let list = [];

switch (classField.value) {

case 'Freight Car':

list = freightTypes;

break;

case 'Locomotive':

list = locomotiveTypes;

break;

case 'Passenger Car':

list = passengerTypes;

break;

case 'Caboose':

list = cabooseTypes;

break;

case 'MOW':

list = mowTypes;

break;

}

list.forEach(function(item){

let option =
document.createElement(
'option'
);

option.value = item;

option.text = item;

if (item === currentType) {

option.selected = true;

}

typeField.add(option);

});

showCustom();
populateOperationsServices(preserveSelectedService);

}

function populateOperationsServices(preserveSelectedService = false) {

operationsService.innerHTML =
'<option value="">Select Service</option>';

const typeServices =
operationsServiceOptions[typeField.value] || [];

const serviceToPreserve =
preserveSelectedService ? currentOperationsService : '';

let matchedService = false;

typeServices.forEach(

function(serviceName){

let option =
document.createElement(
'option'
);

option.value = serviceName;
option.text = serviceName;

if (
serviceName ===
serviceToPreserve
) {

option.selected = true;
matchedService = true;

}

operationsService.add(
option
);

}

);

let otherOption =
document.createElement(
'option'
);

otherOption.value = 'Other';
otherOption.text = 'Other';

if (
serviceToPreserve !== ''
&& !matchedService
) {

otherOption.selected = true;
operationsServiceCustom.value =
serviceToPreserve;

}
else if (!preserveSelectedService) {

operationsServiceCustom.value = '';

}

operationsService.add(
otherOption
);

toggleOperationsServiceCustom();

}

function toggleOperationsServiceCustom() {

if (operationsService.value === 'Other') {

operationsServiceCustomDiv.style.display = '';

}

else {

operationsServiceCustomDiv.style.display = 'none';

}

}

function showCustom() {

if (typeField.value === 'Other') {

customDiv.style.display = '';

}

else {

customDiv.style.display = 'none';

}

}

classField.addEventListener(
'change',
function(){
    populateTypes();
}
);

typeField.addEventListener(
'change',
function(){
    showCustom();
    populateOperationsServices();
}
);

operationsService.addEventListener(
'change',
toggleOperationsServiceCustom
);

populateTypes(true);

</script>

</body>

</html>