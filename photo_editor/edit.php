<?php

session_start();

require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$type = $_GET['type'] ?? '';
$id = (int)($_GET['id'] ?? 0);

if (
    !in_array($type, ['equipment', 'industry']) ||
    $id <= 0
) {
    die('Invalid request.');
}

if ($type === 'equipment') {

    $stmt = $pdo->prepare("
        SELECT e.*
        FROM equipment e
        JOIN railroads r
            ON e.railroad_id = r.id
        WHERE e.id = :id
        AND r.user_id = :user_id
        LIMIT 1
    ");

    $stmt->execute([
        'id' => $id,
        'user_id' => $_SESSION['user_id']
    ]);

    $record = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$record) {
        die('Equipment not found.');
    }

    $photoFile = $record['photo_filename'];

    $title =
        $record['reporting_marks'] .
        ' ' .
        $record['road_number'];

} else {

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

    $record = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$record) {
        die('Industry not found.');
    }

    $photoFile = $record['photo_filename'];

    $title = $record['industry_name'];
}

if (empty($photoFile)) {
    die('No photo found.');
}

?>

<?php include '../includes/header.php'; ?>

<title>Edit Photo</title>

<link
href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.css"
rel="stylesheet">

<style>

.image-container {
    max-width: 100%;
    max-height: 70vh;
}

#image {
    display: block;
    max-width: 100%;
}

.toolbar button {
    margin-right: 8px;
    margin-bottom: 8px;
}

#processingOverlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(255,255,255,.95);
    z-index: 99999;
}

#processingMessage {
    font-size: 1.25rem;
    font-weight: 600;
    margin-top: 20px;
}

</style><?php include '../includes/footer.php'; ?>

</head>

<body>

<?php include '../includes/navbar.php'; ?>

<div class="container mt-5">

<h1>Edit Photo</h1>

<h4 class="text-muted mb-4">

<?php echo htmlspecialchars($title); ?>

</h4>

<div class="card">

<div class="card-body">

<div class="image-container">

<img
id="image"
src="../uploads/<?php echo htmlspecialchars($photoFile); ?>">

</div>

<hr>

<div class="toolbar">

<button
type="button"
class="btn btn-secondary"
onclick="cropper.rotate(-90)">

Rotate Left

</button>

<button
type="button"
class="btn btn-secondary"
onclick="cropper.rotate(90)">

Rotate Right

</button>

<button
type="button"
class="btn btn-secondary"
onclick="cropper.zoom(0.1)">

Zoom In

</button>

<button
type="button"
class="btn btn-secondary"
onclick="cropper.zoom(-0.1)">

Zoom Out

</button>

<button

type="button"
class="btn btn-warning"
onclick="cropper.reset()">

Reset

</button>
<button
type="button"
class="btn btn-info"
id="removeBgBtn">

Remove Background

</button>

</div>

<form
method="post"
action="save.php">

<input
type="hidden"
name="type"
value="<?php echo htmlspecialchars($type); ?>">

<input
type="hidden"
name="id"
value="<?php echo $id; ?>">

<input
type="hidden"
name="cropped_image"
id="cropped_image">

<button
type="submit"
class="btn btn-success">

Save Changes

</button>

<a
href="../<?php echo $type; ?>/view.php?id=<?php echo $id; ?>"
class="btn btn-secondary">

Cancel

</a>

</form>

</div>

</div>

</div>

<script
src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.js">
</script>


<script>

const image =
    document.getElementById('image');

let cropper =
    new Cropper(
        image,
        {
            viewMode: 1,
            autoCropArea: 1,
            responsive: true,
            background: false
        }
    );

document.querySelector('form')
.addEventListener(
    'submit',
    function(e) {

        const canvas =
            cropper.getCroppedCanvas({
                maxWidth: 600,
                maxHeight: 600
            });

        document
            .getElementById('cropped_image')
            .value =
            canvas.toDataURL(
                'image/png',
                0.85
            );
    }
);

document
.getElementById('removeBgBtn')
.addEventListener(
    'click',
    async function() {

        const overlay =
            document.getElementById(
                'processingOverlay'
            );

        const message =
            document.getElementById(
                'processingMessage'
            );

        const messages = [

            "🎨 Detecting train...",
            "🖼️ Analyzing image...",
            "✂️ Separating foreground...",
            "🌲 Removing scenery...",
            "🔍 Refining edges...",
            "✨ Creating transparency...",
            "🚂 Finalizing cutout..."

        ];

        overlay.style.display = 'block';

        this.disabled = true;

        let index = 0;

        const interval = setInterval(
            function() {

                index++;

                if (
                    index >= messages.length
                ) {
                    index =
                        messages.length - 1;
                }

                message.innerText =
                    messages[index];

            },
            1000
        );

        try {

            const canvas =
                cropper.getCroppedCanvas({
                    maxWidth: 800,
                    maxHeight: 800
                });

            const formData =
                new URLSearchParams();

            formData.append(
                'image',
                canvas.toDataURL('image/png')
            );

            const response =
                await fetch(
                    'remove_background.php',
                    {
                        method: 'POST',
                        credentials: 'same-origin',
                        body: formData
                    }
                );

            const result =
                await response.json();

            clearInterval(interval);

            overlay.style.display = 'none';

            this.disabled = false;

            if (!result.success) {

                alert(
                    result.error ||
                    'Background removal failed.'
                );

                return;
            }

            cropper.destroy();

            image.src =
                result.image;

            image.onload =
                function() {

                    cropper =
                        new Cropper(
                            image,
                            {
                                viewMode: 1,
                                autoCropArea: 1,
                                responsive: true,
                                background: false
                            }
                        );

                };

        } catch (err) {

            clearInterval(interval);

            overlay.style.display = 'none';

            this.disabled = false;

            alert(
                'Background removal failed.'
            );

        }

    }
);
</script>
<div id="processingOverlay">

    <div class="d-flex justify-content-center align-items-center h-100">

        <div class="text-center">

            <div
                class="spinner-border text-primary"
                role="status"
                style="width:5rem;height:5rem;">
            </div>

            <div id="processingMessage">

                🎨 Detecting train...

            </div>

            <div class="mt-2 text-muted">

                This may take 5–20 seconds depending on image size.

            </div>

        </div>

    </div>

</div>
<?php include '../includes/footer.php'; ?>