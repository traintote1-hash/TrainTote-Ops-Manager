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
        trim(
            $record['reporting_marks'] .
            ' ' .
            $record['road_number']
        );

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

<title>Crop Image</title>

<script src="https://cdnjs.cloudflare.com/ajax/libs/fabric.js/5.3.1/fabric.min.js"></script>

<style>

#cropCanvasWrapper {
    width: 100%;
    height: 750px;
    border: 1px solid #ccc;
    border-radius: 8px;
    background: #222;
    overflow: hidden;
}

.crop-toolbar button {
    margin-right: 8px;
    margin-bottom: 8px;
}

</style>

</head>

<body>

<?php include '../includes/navbar.php'; ?>

<div class="container-fluid mt-4">

<h1>Crop Image</h1>

<h4 class="text-muted mb-4">

<?php echo htmlspecialchars($title); ?>

</h4>
<div class="card">

<div class="card-body">

<div class="crop-toolbar mb-3">

<button
type="button"
class="btn btn-secondary"
onclick="resetCrop();">

Reset Crop

</button>

<button
type="button"
class="btn btn-success"
id="cropSaveButton">

Done Cropping

</button>

<a
href="edit.php?type=<?php echo urlencode($type); ?>&id=<?php echo $id; ?>"
class="btn btn-secondary">

Cancel

</a>

</div>

<div id="cropCanvasWrapper">

<canvas
id="cropCanvas"
width="1400"
height="750">
</canvas>

</div>

<div class="mt-3 text-muted">

Drag the crop box. Resize it using the large corner handles.

</div>

</div>

</div>

<form
id="cropForm"
method="post"
action="crop_save.php"
style="display:none;">

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

</form>
<script>

const canvas = new fabric.Canvas(
    'cropCanvas',
    {
        selection: false
    }
);

let photo = null;
let cropBox = null;

fabric.Image.fromURL(
    '../uploads/<?php echo htmlspecialchars($photoFile); ?>',
    function(img) {

        photo = img;

        const scale = Math.min(
            1200 / img.width,
            650 / img.height
        );

        photo.scale(scale);

        photo.set({
            left: (canvas.width - (img.width * scale)) / 2,
            top: (canvas.height - (img.height * scale)) / 2,
            selectable: false,
            evented: false
        });

        canvas.add(photo);

        cropBox = new fabric.Rect({

            left: 250,
            top: 150,

            width: 600,
            height: 300,

            fill: 'rgba(255,255,255,0)',

            stroke: '#ffffff',
            strokeWidth: 3,

            borderColor: '#ffffff',

            cornerColor: '#ffffff',

            cornerStyle: 'rect',

            transparentCorners: false,

            cornerSize: 32

        });

        canvas.add(cropBox);

        canvas.setActiveObject(cropBox);

        canvas.renderAll();
    }
);

function resetCrop() {

    if (!cropBox) return;

    cropBox.set({
        left: 250,
        top: 150,
        width: 600,
        height: 300,
        scaleX: 1,
        scaleY: 1
    });

    canvas.setActiveObject(cropBox);

    canvas.renderAll();
}

document
.getElementById('cropSaveButton')
.addEventListener(
    'click',
    function() {

        if (!photo || !cropBox) {
            return;
        }

        const cropData = {

            left: cropBox.left,
            top: cropBox.top,

            width:
                cropBox.width *
                cropBox.scaleX,

            height:
                cropBox.height *
                cropBox.scaleY

        };

        const exportCanvas =
            document.createElement(
                'canvas'
            );

        exportCanvas.width =
            cropData.width;

        exportCanvas.height =
            cropData.height;

        const ctx =
            exportCanvas.getContext(
                '2d'
            );

        ctx.drawImage(

            canvas.lowerCanvasEl,

            cropData.left,
            cropData.top,

            cropData.width,
            cropData.height,

            0,
            0,

            cropData.width,
            cropData.height

        );

        document
            .getElementById(
                'cropped_image'
            )
            .value =
            exportCanvas.toDataURL(
                'image/png'
            );

        document
            .getElementById(
                'cropForm'
            )
            .submit();

    }
);

</script>

<?php include '../includes/footer.php'; ?>