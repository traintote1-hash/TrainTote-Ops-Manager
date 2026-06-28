<?php

session_start();

require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

if (!isset($_GET['id'])) {
    die('Industry ID missing.');
}

$id = (int)$_GET['id'];

$stmt = $pdo->prepare("
    SELECT i.*
    FROM industries i
    JOIN railroads r
        ON i.railroad_id = r.id
    WHERE i.id = :id
    AND r.user_id = :user_id
    LIMIT 1
");

$stmt->execute([
    'id' => $id,
    'user_id' => $_SESSION['user_id']
]);

$industry = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$industry) {
    die('Industry not found.');
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
        UPDATE industries
        SET
            industry_name = :industry_name,
            industry_type = :industry_type,
            location = :location,
            track_capacity = :track_capacity,
            receives_services = :receives_services,
            ships_services = :ships_services,
            notes = :notes
        WHERE id = :id
    ");

    $stmt->execute([

        'industry_name' => $industry_name,
        'industry_type' => $industry_type,
        'location' => $location,
        'track_capacity' => $track_capacity,
        'receives_services' => $receives_services,
        'ships_services' => $ships_services,
        'notes' => $notes,
        'id' => $id

    ]);


    // -----------------------------------
    // Replace Photo
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
                        ($height / $width) *
                        $newWidth
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

                // Delete old photo

                if (!empty($industry['photo_filename'])) {

                    $oldFile =
                        dirname(__DIR__) .
                        '/uploads/' .
                        $industry['photo_filename'];

                    if (file_exists($oldFile)) {

                        unlink($oldFile);

                    }

                }

                // Save new photo

                $newFilename =
                    'industry_' .
                    $id .
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

                        'id' => $id

                    ]);

                }

                imagedestroy($image);

            }

        }

    }

    header("Location: view.php?id=$id");

    exit;

}

?>

<?php include '../includes/header.php'; ?>

<title>Edit Industry</title>

</head>

<body>

<?php include '../includes/navbar.php'; ?>

<div class="container mt-5">

<h1>Edit Industry</h1>

<form
method="post"
enctype="multipart/form-data">
<?php if (!empty($industry['photo_filename'])): ?>

<div class="mb-4">

<label class="form-label">
Current Photo
</label>

<br>

<img
src="../uploads/<?php echo htmlspecialchars($industry['photo_filename']); ?>?v=<?php echo time(); ?>"
class="img-fluid rounded border"
style="max-height:250px; max-width:400px;">

</div>

<?php endif; ?>

<div class="mb-3">

<label>Upload New Photo</label>

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

<div class="mb-3">

<label>Industry Name</label>

<input
type="text"
name="industry_name"
class="form-control"
value="<?php echo htmlspecialchars($industry['industry_name']); ?>"
required>

</div>

<div class="mb-3">

<label>Industry Type</label>

<input
type="text"
name="industry_type"
class="form-control"
value="<?php echo htmlspecialchars($industry['industry_type']); ?>">

</div>

<div class="mb-3">

<label>Location</label>

<input
type="text"
name="location"
class="form-control"
value="<?php echo htmlspecialchars($industry['location']); ?>">

</div>

<div class="mb-3">

<label>Track Capacity</label>

<input
type="number"
name="track_capacity"
class="form-control"
value="<?php echo htmlspecialchars($industry['track_capacity']); ?>">

</div>

<div class="mb-3">

<label>Receives Services</label>

<textarea
name="receives_services"
class="form-control"
rows="3"
placeholder="Sand / Gravel, Cement, General Freight"><?php echo htmlspecialchars($industry['receives_services'] ?? ''); ?></textarea>

</div>

<div class="mb-3">

<label>Ships Services</label>

<textarea
name="ships_services"
class="form-control"
rows="3"
placeholder="Grain, Scrap Metal, Vegetable Oil"><?php echo htmlspecialchars($industry['ships_services'] ?? ''); ?></textarea>

</div>

<div class="mb-3">

<label>Notes</label>

<textarea
name="notes"
class="form-control"
rows="4"><?php echo htmlspecialchars($industry['notes']); ?></textarea>

</div>

<button
type="submit"
class="btn btn-success me-2">

Save Changes

</button>

<a
href="view.php?id=<?php echo $industry['id']; ?>"
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