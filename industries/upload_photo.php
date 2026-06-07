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

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (
        !isset($_FILES['photo']) ||
        $_FILES['photo']['error'] !== UPLOAD_ERR_OK
    ) {

        $message = 'Please select a valid image.';

    } else {

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

        if (!in_array($extension, $allowedExtensions)) {

            $message =
                'Only JPG, JPEG, PNG and WEBP files are allowed.';

        } else {

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

            if (!$image) {

                $message = 'Unable to process image.';

            } else {

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

                if (!empty($industry['photo_filename'])) {

                    $oldFile =
                        dirname(__DIR__) .
                        '/uploads/' .
                        $industry['photo_filename'];

                    if (file_exists($oldFile)) {
                        unlink($oldFile);
                    }
                }

                $newFilename =
                    'industry_' .
                    $industry['id'] .
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
                        'id' => $industry['id']
                    ]);

                    imagedestroy($image);

                    header(
                        'Location: view.php?id=' .
                        $industry['id']
                    );

                    exit;

                } else {

                    $message = 'Failed to save image.';
                }

                imagedestroy($image);
            }
        }
    }
}

include '../includes/header.php';
?>

<title>Upload Industry Photo</title>

</head>

<body>

<?php include '../includes/navbar.php'; ?>

<div class="container mt-5">

    <h1>Upload Industry Photo</h1>

    <h4 class="mb-4">
        <?php echo htmlspecialchars($industry['industry_name']); ?>
    </h4>

    <?php if (!empty($message)): ?>

        <div class="alert alert-danger">
            <?php echo htmlspecialchars($message); ?>
        </div>

    <?php endif; ?>

    <form
        method="post"
        enctype="multipart/form-data">

        <div class="mb-3">

            <label class="form-label">
                Select Photo
            </label>

            <input
                type="file"
                id="photo"
                name="photo"
                class="form-control"
                accept=".jpg,.jpeg,.png,.webp,image/*"
                required>

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

            Upload Photo

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