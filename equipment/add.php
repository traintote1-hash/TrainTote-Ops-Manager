<?php

session_start();

require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$stmt = $pdo->prepare("
    SELECT *
    FROM railroads
    WHERE user_id = :user_id
    LIMIT 1
");

$stmt->execute([
    'user_id' => $_SESSION['user_id']
]);

$railroad = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$railroad) {
    die('No railroad found.');
}

/*
|--------------------------------------------------------------------------
| Load Industries for Current Location Dropdown
|--------------------------------------------------------------------------
*/

$industryStmt = $pdo->prepare("
    SELECT id, industry_name
    FROM industries
    WHERE railroad_id = :railroad_id
    ORDER BY industry_name
");

$industryStmt->execute([
    'railroad_id' => $railroad['id']
]);

$industries = $industryStmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $reporting_marks = trim($_POST['reporting_marks']);
    $road_number = trim($_POST['road_number']);
    $road_name = trim($_POST['road_name']);
    $equipment_class = trim($_POST['equipment_class']);
    $equipment_type = trim($_POST['equipment_type']);
    $prototype = trim($_POST['prototype']);
    $service = trim($_POST['service']);
    $manufacturer = trim($_POST['manufacturer']);
    $color = trim($_POST['color']);
    $length_ft = trim($_POST['length_ft']);
    $scale = trim($_POST['scale']);
    $load_status = trim($_POST['load_status']);

    $current_industry_id =
        !empty($_POST['current_industry_id'])
        ? (int)$_POST['current_industry_id']
        : null;

    $notes = trim($_POST['notes']);

    $stmt = $pdo->prepare("
        INSERT INTO equipment
        (
            railroad_id,
            reporting_marks,
            road_number,
            road_name,
            equipment_class,
            equipment_type,
            prototype,
            service,
            manufacturer,
            color,
            length_ft,
            scale,
            load_status,
            current_industry_id,
            notes
        )
        VALUES
        (
            :railroad_id,
            :reporting_marks,
            :road_number,
            :road_name,
            :equipment_class,
            :equipment_type,
            :prototype,
            :service,
            :manufacturer,
            :color,
            :length_ft,
            :scale,
            :load_status,
            :current_industry_id,
            :notes
        )
    ");

    $stmt->execute([
        'railroad_id' => $railroad['id'],
        'reporting_marks' => $reporting_marks,
        'road_number' => $road_number,
        'road_name' => $road_name,
        'equipment_class' => $equipment_class,
        'equipment_type' => $equipment_type,
        'prototype' => $prototype,
        'service' => $service,
        'manufacturer' => $manufacturer,
        'color' => $color,
        'length_ft' => $length_ft,
        'scale' => $scale,
        'load_status' => $load_status,
        'current_industry_id' => $current_industry_id,
        'notes' => $notes
    ]);

    $equipmentId = $pdo->lastInsertId();

    if (
        isset($_FILES['photo']) &&
        $_FILES['photo']['error'] === UPLOAD_ERR_OK
    ) {

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

        if (in_array($extension, $allowedExtensions)) {

            $filename =
                'equipment_' .
                $equipmentId .
                '_' .
                time() .
                '.' .
                $extension;

            $uploadPath =
                dirname(__DIR__) .
                '/uploads/' .
                $filename;

            if (
                move_uploaded_file(
                    $_FILES['photo']['tmp_name'],
                    $uploadPath
                )
            ) {

                $stmt = $pdo->prepare("
                    UPDATE equipment
                    SET photo_filename = :photo_filename
                    WHERE id = :id
                ");

                $stmt->execute([
                    'photo_filename' => $filename,
                    'id' => $equipmentId
                ]);
            }
        }
    }

    if (!empty($_SESSION['ai_photo'])) {

        $tempFile =
            dirname(__DIR__) .
            '/uploads/temp/' .
            $_SESSION['ai_photo'];

        $finalFile =
            dirname(__DIR__) .
            '/uploads/' .
            $_SESSION['ai_photo'];

        if (file_exists($tempFile)) {

            rename($tempFile, $finalFile);

            $stmt = $pdo->prepare("
                UPDATE equipment
                SET photo_filename = :photo_filename
                WHERE id = :id
            ");

            $stmt->execute([
                'photo_filename' => $_SESSION['ai_photo'],
                'id' => $equipmentId
            ]);
        }

        unset($_SESSION['ai_photo']);
    }

    header("Location: saved.php?id=$equipmentId");
    exit;
}

$duplicate = null;

$checkRoadName =
    trim($_GET['road_name'] ?? '');

$checkRoadNumber =
    trim($_GET['road_number'] ?? '');

if (
    !empty($checkRoadName) &&
    !empty($checkRoadNumber)
) {

    $stmt = $pdo->prepare("
        SELECT id
        FROM equipment
        WHERE railroad_id = :railroad_id
        AND road_name = :road_name
        AND road_number = :road_number
        LIMIT 1
    ");

    $stmt->execute([
        'railroad_id' => $railroad['id'],
        'road_name' => $checkRoadName,
        'road_number' => $checkRoadNumber
    ]);

    $duplicate = $stmt->fetch(PDO::FETCH_ASSOC);
}

$equipmentClass =
    $_GET['equipment_class']
    ?? 'Freight Car';
?>

<?php include '../includes/header.php'; ?>

<title>Add Equipment</title>

</head>

<body>

<?php include '../includes/navbar.php'; ?>

<div class="container mt-5">

<h1>Add Equipment</h1>

<?php if ($duplicate): ?>

<div class="alert alert-danger">

    <strong>⚠ Duplicate Equipment Found</strong>

    <br><br>

    <?php echo htmlspecialchars($checkRoadName); ?>
    <?php echo htmlspecialchars($checkRoadNumber); ?>

    already exists in your roster.

    <br><br>

    <a
        href="view.php?id=<?php echo $duplicate['id']; ?>"
        class="btn btn-danger btn-sm">

        View Existing Record

    </a>

</div>

<?php endif; ?>

<?php if (!empty($_SESSION['ai_photo'])): ?>

<div class="card mb-4">

    <div class="card-body text-center">

        <img
            src="../uploads/temp/<?php echo htmlspecialchars($_SESSION['ai_photo']); ?>"
            style="max-width:800px; width:100%; max-height:400px; object-fit:contain;">

    </div>

</div>

<?php endif; ?>

<form
method="post"
enctype="multipart/form-data">

<div class="mb-3">

<label>Reporting Marks</label>

<input
type="text"
name="reporting_marks"
class="form-control"
value="<?php echo htmlspecialchars($_GET['reporting_marks'] ?? ''); ?>"
required>

</div>

<div class="mb-3">

<label>Road Number</label>

<input
type="text"
name="road_number"
class="form-control"
value="<?php echo htmlspecialchars($_GET['road_number'] ?? ''); ?>"
required>

</div>

<div class="mb-3">

<label>Road Name</label>

<input
type="text"
name="road_name"
class="form-control"
value="<?php echo htmlspecialchars($_GET['road_name'] ?? ''); ?>">

</div>

<div class="mb-3">

<label>Equipment Class</label>

<select name="equipment_class" class="form-select">

<option <?php if($equipmentClass=='Freight Car') echo 'selected'; ?>>Freight Car</option>

<option <?php if($equipmentClass=='Locomotive') echo 'selected'; ?>>Locomotive</option>

<option <?php if($equipmentClass=='Passenger Car') echo 'selected'; ?>>Passenger Car</option>

<option <?php if($equipmentClass=='Caboose') echo 'selected'; ?>>Caboose</option>

<option <?php if($equipmentClass=='MOW') echo 'selected'; ?>>MOW</option>

</select>

</div>

<div class="mb-3">

<label>Equipment Type</label>

<input
type="text"
name="equipment_type"
class="form-control"
value="<?php echo htmlspecialchars($_GET['equipment_type'] ?? ''); ?>">

</div>

<div class="mb-3">

<label>Prototype</label>

<input
type="text"
name="prototype"
class="form-control"
value="<?php echo htmlspecialchars($_GET['prototype'] ?? ''); ?>">

</div>

<div class="mb-3">

<label>Service</label>

<input
type="text"
name="service"
class="form-control"
value="<?php echo htmlspecialchars($_GET['service'] ?? ''); ?>">

</div>

<div class="mb-3">

<label>Manufacturer</label>

<input
type="text"
name="manufacturer"
class="form-control"
value="<?php echo htmlspecialchars($_GET['manufacturer'] ?? ''); ?>">

</div>

<div class="mb-3">

<label>Color</label>

<input
type="text"
name="color"
class="form-control"
value="<?php echo htmlspecialchars($_GET['color'] ?? ''); ?>">

</div>

<div class="mb-3">

<label>Length (Feet)</label>

<input
type="text"
name="length_ft"
class="form-control"
value="<?php echo htmlspecialchars($_GET['length_ft'] ?? ''); ?>">

</div>

<div class="mb-3">

<label>Scale</label>

<select name="scale" class="form-select">

<option value="N"
<?php if(($_GET['scale'] ?? 'HO')=='N') echo 'selected'; ?>>
N
</option>

<option value="HO"
<?php if(($_GET['scale'] ?? 'HO')=='HO') echo 'selected'; ?>>
HO
</option>

<option value="S"
<?php if(($_GET['scale'] ?? '')=='S') echo 'selected'; ?>>
S
</option>

<option value="O"
<?php if(($_GET['scale'] ?? '')=='O') echo 'selected'; ?>>
O
</option>

<option value="G"
<?php if(($_GET['scale'] ?? '')=='G') echo 'selected'; ?>>
G
</option>

</select>

</div>
<div class="mb-3">

<label>Load Status</label>

<select name="load_status" class="form-select">

<option selected>Empty</option>

<option>Loaded</option>

</select>

</div>

<div class="mb-3">

<label>Current Location</label>

<select
name="current_industry_id"
class="form-select">

<option value="">
Select Location
</option>

<?php foreach ($industries as $industry): ?>

<option value="<?php echo $industry['id']; ?>">

<?php echo htmlspecialchars($industry['industry_name']); ?>

</option>

<?php endforeach; ?>

</select>

</div>

<div class="mb-3">

<label>Photo</label>

<input
type="file"
name="photo"
class="form-control"
accept=".jpg,.jpeg,.png,.webp">

</div>

<div class="mb-3">

<label>Notes</label>

<textarea
name="notes"
class="form-control"
rows="4"><?php echo htmlspecialchars($_GET['notes'] ?? ''); ?></textarea>

</div>

<button
type="submit"
class="btn btn-success">

Save Equipment

</button>

<a
href="list.php"
class="btn btn-secondary">

Cancel

</a>

</form>

</div>

<?php include '../includes/footer.php'; ?>	