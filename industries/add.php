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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $industry_name = trim($_POST['industry_name']);
    $industry_type = trim($_POST['industry_type']);
    $location = trim($_POST['location']);
    $track_capacity = (int)$_POST['track_capacity'];
    $receives_services = trim($_POST['receives_services'] ?? '');
    $ships_services = trim($_POST['ships_services'] ?? '');
    $notes = trim($_POST['notes']);

    $stmt = $pdo->prepare("
        INSERT INTO industries
        (
            railroad_id,
            industry_name,
            industry_type,
            location,
            track_capacity,
            receives_services,
            ships_services,
            notes
        )
        VALUES
        (
            :railroad_id,
            :industry_name,
            :industry_type,
            :location,
            :track_capacity,
            :receives_services,
            :ships_services,
            :notes
        )
    ");

    $stmt->execute([

        'railroad_id' => $railroad['id'],
        'industry_name' => $industry_name,
        'industry_type' => $industry_type,
        'location' => $location,
        'track_capacity' => $track_capacity,
        'receives_services' => $receives_services,
        'ships_services' => $ships_services,
        'notes' => $notes

    ]);

    $industryId = $pdo->lastInsertId();


    // -----------------------------------
    // Photo Upload
    // -----------------------------------

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

        $originalName = $_FILES['photo']['name'];

        $tempFile = $_FILES['photo']['tmp_name'];

        $extension = strtolower(
            pathinfo(
                $originalName,
                PATHINFO_EXTENSION
            )
        );

        if (in_array($extension, $allowedExtensions)) {

            switch ($extension) {

                case 'jpg':
                case 'jpeg':
                    $image = imagecreatefromjpeg($tempFile);
                    break;

                case 'png':
                    $image = imagecreatefrompng($tempFile);
                    break;

                case 'webp':
                    $image = imagecreatefromwebp($tempFile);
                    break;

                default:
                    $image = false;

            }

            if ($image) {

                $width = imagesx($image);
                $height = imagesy($image);

                $maxWidth = 1200;
                if ($width > $maxWidth) {

                    $newWidth = $maxWidth;

                    $newHeight = intval(
                        ($height / $width) * $newWidth
                    );

                    $resized = imagecreatetruecolor(
                        $newWidth,
                        $newHeight
                    );

                    imagecopyresampled(
                        $resized,
                        $image,
                        0,
                        0,
                        0,
                        0,
                        $newWidth,
                        $newHeight,
                        $width,
                        $height
                    );

                    imagedestroy($image);

                    $image = $resized;

                }

                $newFilename =
                    'industry_' .
                    $industryId .
                    '_' .
                    time() .
                    '.jpg';

                $uploadPath =
                    dirname(__DIR__) .
                    '/uploads/' .
                    $newFilename;

                if (
                    imagejpeg(
                        $image,
                        $uploadPath,
                        80
                    )
                ) {

                    $stmt = $pdo->prepare("
                        UPDATE industries
                        SET photo_filename = :photo_filename
                        WHERE id = :id
                    ");

                    $stmt->execute([

                        'photo_filename' => $newFilename,

                        'id' => $industryId

                    ]);

                }

                imagedestroy($image);

            }

        }

    }

    header("Location: saved.php?id=$industryId");

    exit;

}

?>

<?php include '../includes/header.php'; ?>

<title>Add Industry</title>

</head>

<body>

<?php include '../includes/navbar.php'; ?>

<div class="container mt-5">

<h1>Add Industry</h1>

<form
method="post"
enctype="multipart/form-data">

<div class="mb-3">

<label>Industry Name</label>

<input
type="text"
name="industry_name"
class="form-control"
required>

</div>

<div class="mb-3">

<label>Industry Type</label>

<input
type="text"
name="industry_type"
class="form-control"
placeholder="Lumber, Team Track, Fuel Dealer, Intermodal, etc.">

</div>

<div class="mb-3">

<label>Location</label>

<input
type="text"
name="location"
class="form-control">

</div>

<div class="mb-3">

<label>Track Capacity (Cars)</label>

<input
type="number"
name="track_capacity"
class="form-control"
value="0">

</div>

<div class="mb-3">

<label>Receives Services</label>

<textarea
name="receives_services"
class="form-control"
rows="3"
placeholder="Sand / Gravel, Cement, General Freight"></textarea>

</div>

<div class="mb-3">

<label>Ships Services</label>

<textarea
name="ships_services"
class="form-control"
rows="3"
placeholder="Grain, Scrap Metal, Vegetable Oil"></textarea>

</div>

<div class="mb-3">

<label>Notes</label>

<textarea
name="notes"
class="form-control"
rows="4"></textarea>

</div>

<div class="mb-3">

<label>Photo</label>

<input
type="file"
id="photo"
name="photo"
class="form-control"
accept=".jpg,.jpeg,.png,.webp,image/*">

<div class="mt-3">

<img
id="previewImage"
style="
display:none;
max-width:300px;
max-height:300px;
border:1px solid #ccc;
border-radius:8px;
padding:8px;">

</div>

</div>
<button
type="submit"
class="btn btn-success me-2">

Save Industry

</button>

<a
href="list.php"
class="btn btn-secondary">

Cancel

</a>

</form>

</div>

<script>

document.getElementById('photo').addEventListener('change', function () {

    const file = this.files[0];

    if (!file) {
        return;
    }

    const reader = new FileReader();

    reader.onload = function (e) {

        const preview =
            document.getElementById('previewImage');

        preview.src = e.target.result;

        preview.style.display = 'block';

    };

    reader.readAsDataURL(file);

});

</script>

<?php include '../includes/footer.php'; ?>