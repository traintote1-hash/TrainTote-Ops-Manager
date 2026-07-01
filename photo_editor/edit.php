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

    $title =
        trim(
            $record['reporting_marks'] .
            ' ' .
            $record['road_number']
        );

}
else {

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

    $title =
        $record['industry_name'];

}

if (empty($record['photo_filename'])) {
    die('No photo found.');
}

$imageUrl =
    '../uploads/' .
    str_replace('%2F', '/', rawurlencode($record['photo_filename'])) .
    '?v=' .
    time();

?>

<?php include '../includes/header.php'; ?>

<title>Edit Photo</title>

<style>

.photo-editor-page{
    display:grid;
    gap:16px;
}

.photo-editor-header{
    display:flex;
    align-items:flex-end;
    justify-content:space-between;
    gap:16px;
    flex-wrap:wrap;
}

.photo-editor-header h1{
    margin:0;
}

.photo-editor-shell{
    display:grid;
    grid-template-columns:minmax(0,1fr) 320px;
    gap:16px;
    align-items:start;
}

.photo-editor-stage{
    display:grid;
    place-items:center;
    min-height:62vh;
    padding:16px;
    background:#f3f6fa;
    border:1px solid #d8dee6;
    border-radius:8px;
    overflow:auto;
}

#photoCanvas{
    max-width:100%;
    height:auto;
    background:#fff;
    border:1px solid #cbd5e1;
    border-radius:6px;
    box-shadow:0 8px 20px rgba(15,23,42,.12);
    cursor:crosshair;
}

#photoCanvas.is-crop-locked{
    cursor:not-allowed;
}

.photo-editor-controls{
    display:grid;
    gap:12px;
}

.photo-control-card{
    background:#fff;
    border:1px solid #d8dee6;
    border-radius:8px;
    padding:14px;
    box-shadow:0 1px 3px rgba(15,23,42,.06);
}

.photo-control-card h2{
    margin:0 0 10px;
    color:#1f2937;
    font-size:1rem;
}

.photo-button-row{
    display:flex;
    gap:8px;
    flex-wrap:wrap;
}

.photo-button-row .btn{
    flex:1 1 auto;
    min-width:86px;
}

.photo-range-row{
    display:grid;
    grid-template-columns:1fr auto;
    gap:10px;
    align-items:center;
}

.photo-range-row output{
    min-width:54px;
    color:#8B1E24;
    font-weight:700;
    text-align:right;
}

.photo-editor-note{
    margin:8px 0 0;
    color:#64748b;
    font-size:.9rem;
}

.photo-editor-status{
    min-height:22px;
    color:#64748b;
    font-size:.9rem;
}

#processingOverlay{
    display:none;
    position:fixed;
    inset:0;
    background:rgba(255,255,255,.92);
    z-index:99999;
}

@media(max-width:1000px){
    .photo-editor-shell{
        grid-template-columns:1fr;
    }
}

</style>

</head>

<body>

<?php include '../includes/navbar.php'; ?>

<div class="container-fluid mt-4 photo-editor-page">

<div class="photo-editor-header">
    <div>
        <h1>Edit Photo</h1>
        <h4 class="text-muted mb-0"><?php echo htmlspecialchars($title); ?></h4>
    </div>

    <a
    href="../<?php echo $type; ?>/view.php?id=<?php echo $id; ?>"
    class="btn btn-outline-secondary">
        Back to Record
    </a>
</div>

<div class="alert alert-info mb-0">
    Rotate first. If you need to crop a rotated image, click <strong>Lock Rotation for Crop</strong>, then draw the crop box.
</div>

<div class="photo-editor-shell">
    <div class="photo-editor-stage" id="photoStage">
        <canvas id="photoCanvas"></canvas>
    </div>

    <aside class="photo-editor-controls" aria-label="Photo editor controls">
        <section class="photo-control-card">
            <h2>Rotate</h2>

            <div class="photo-range-row">
                <input
                type="range"
                class="form-range"
                id="rotationSlider"
                min="-180"
                max="180"
                step="1"
                value="0">

                <output id="rotationValue">0&deg;</output>
            </div>

            <div class="photo-button-row mt-2">
                <button type="button" class="btn btn-outline-secondary" data-rotate="-90">-90&deg;</button>
                <button type="button" class="btn btn-outline-secondary" data-rotate="-1">-1&deg;</button>
                <button type="button" class="btn btn-outline-secondary" data-rotate="1">+1&deg;</button>
                <button type="button" class="btn btn-outline-secondary" data-rotate="90">+90&deg;</button>
            </div>

            <button
            type="button"
            class="btn btn-primary w-100 mt-2"
            id="lockRotationBtn">
                Lock Rotation for Crop
            </button>

            <p class="photo-editor-note">Rotation is redrawn from the full source image until it is locked.</p>
        </section>

        <section class="photo-control-card">
            <h2>Crop</h2>

            <div class="photo-button-row">
                <button type="button" class="btn btn-outline-secondary" id="resetCropBtn">Full Image</button>
                <button type="button" class="btn btn-outline-secondary" id="gridToggleBtn">Grid On</button>
            </div>

            <p class="photo-editor-note" id="cropHelpText">Drag on the image to set the crop area.</p>
        </section>

        <section class="photo-control-card">
            <h2>Photo</h2>

            <div class="photo-button-row">
                <button type="button" class="btn btn-info" id="removeBgBtn">Remove Background</button>
                <button type="button" class="btn btn-outline-danger" id="restoreOriginalBtn">Restore Original Photo</button>
            </div>

            <p class="photo-editor-note">Restore reloads the original uploaded photo for this record.</p>
        </section>

        <section class="photo-control-card">
            <h2>Save</h2>

            <form
            id="saveForm"
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
            class="btn btn-success w-100">
                Save Changes
            </button>

            <a
            href="../<?php echo $type; ?>/view.php?id=<?php echo $id; ?>"
            class="btn btn-secondary w-100 mt-2">
                Cancel
            </a>

            </form>
        </section>

        <div class="photo-editor-status" id="editorStatus"></div>
    </aside>
</div>

</div>

<div id="processingOverlay">

<div class="d-flex justify-content-center align-items-center h-100">

<div class="text-center">

<div
class="spinner-border text-primary"
style="width:5rem;height:5rem;">
</div>

<div class="mt-3 fw-bold">

Processing Image...

</div>

<div class="mt-2 text-muted">

Please wait...

</div>

</div>

</div>

</div>

<script>

const originalImage = <?php echo json_encode($imageUrl); ?>;
const canvas = document.getElementById('photoCanvas');
const context = canvas.getContext('2d');
const stage = document.getElementById('photoStage');
const rotationSlider = document.getElementById('rotationSlider');
const rotationValue = document.getElementById('rotationValue');
const cropHelpText = document.getElementById('cropHelpText');
const gridToggleBtn = document.getElementById('gridToggleBtn');
const statusBox = document.getElementById('editorStatus');
const overlay = document.getElementById('processingOverlay');

let currentImage = originalImage;
let sourceImage = null;
let rotationAngle = 0;
let cropRect = null;
let isDraggingCrop = false;
let dragStart = null;
let gridEnabled = true;
let currentDisplayScale = 1;

function setStatus(message) {
    statusBox.textContent = message || '';
}

function showOverlay() {
    overlay.style.display = 'block';
}

function hideOverlay() {
    overlay.style.display = 'none';
}

function normalizeAngle(angle) {
    let normalized = Math.round(angle);

    while (normalized > 180) {
        normalized -= 360;
    }

    while (normalized < -180) {
        normalized += 360;
    }

    return normalized;
}

function loadImage(src) {
    return new Promise(function(resolve, reject) {
        const image = new Image();

        image.onload = function() {
            resolve(image);
        };

        image.onerror = function() {
            reject(new Error('Unable to load image.'));
        };

        image.src = src;
    });
}

function buildRotatedCanvas() {
    const radians = rotationAngle * Math.PI / 180;
    const sin = Math.abs(Math.sin(radians));
    const cos = Math.abs(Math.cos(radians));
    const width = sourceImage.naturalWidth || sourceImage.width;
    const height = sourceImage.naturalHeight || sourceImage.height;
    const rotatedWidth = Math.max(1, Math.ceil(width * cos + height * sin));
    const rotatedHeight = Math.max(1, Math.ceil(width * sin + height * cos));
    const rotatedCanvas = document.createElement('canvas');
    const rotatedContext = rotatedCanvas.getContext('2d');

    rotatedCanvas.width = rotatedWidth;
    rotatedCanvas.height = rotatedHeight;

    rotatedContext.save();
    rotatedContext.translate(rotatedWidth / 2, rotatedHeight / 2);
    rotatedContext.rotate(radians);
    rotatedContext.drawImage(sourceImage, -width / 2, -height / 2);
    rotatedContext.restore();

    return rotatedCanvas;
}

function fitCanvasToStage(rotatedCanvas) {
    const stageWidth = Math.max(320, stage.clientWidth - 34);
    const maxDisplayHeight = Math.max(360, Math.floor(window.innerHeight * 0.68));

    return Math.min(
        stageWidth / rotatedCanvas.width,
        maxDisplayHeight / rotatedCanvas.height,
        1
    );
}

function resetCropToFullImage() {
    cropRect = {
        x: 0,
        y: 0,
        width: canvas.width,
        height: canvas.height
    };
}

function drawGrid() {
    if (!gridEnabled) {
        return;
    }

    context.save();
    context.strokeStyle = 'rgba(255,255,255,.72)';
    context.lineWidth = 1;

    for (let i = 1; i < 3; i++) {
        const x = canvas.width * i / 3;
        const y = canvas.height * i / 3;

        context.beginPath();
        context.moveTo(x, 0);
        context.lineTo(x, canvas.height);
        context.stroke();

        context.beginPath();
        context.moveTo(0, y);
        context.lineTo(canvas.width, y);
        context.stroke();
    }

    context.restore();
}

function drawCropOverlay() {
    if (!cropRect) {
        return;
    }

    context.save();
    context.fillStyle = 'rgba(15, 23, 42, .32)';
    context.fillRect(0, 0, canvas.width, canvas.height);
    context.clearRect(cropRect.x, cropRect.y, cropRect.width, cropRect.height);
    context.strokeStyle = '#2E8B57';
    context.lineWidth = 2;
    context.strokeRect(cropRect.x + 1, cropRect.y + 1, cropRect.width - 2, cropRect.height - 2);
    context.restore();

    drawGrid();
}

function updateCropHelp() {
    if (rotationAngle !== 0) {
        canvas.classList.add('is-crop-locked');
        cropHelpText.textContent = 'Lock rotation before cropping a rotated image.';
    }
    else {
        canvas.classList.remove('is-crop-locked');
        cropHelpText.textContent = 'Drag on the image to set the crop area.';
    }
}

function renderEditor(resetCrop) {
    if (!sourceImage) {
        return;
    }

    const rotatedCanvas = buildRotatedCanvas();
    currentDisplayScale = fitCanvasToStage(rotatedCanvas);
    canvas.width = Math.max(1, Math.round(rotatedCanvas.width * currentDisplayScale));
    canvas.height = Math.max(1, Math.round(rotatedCanvas.height * currentDisplayScale));

    context.clearRect(0, 0, canvas.width, canvas.height);
    context.drawImage(rotatedCanvas, 0, 0, canvas.width, canvas.height);

    if (resetCrop || !cropRect) {
        resetCropToFullImage();
    }

    drawCropOverlay();
    updateCropHelp();
}

function setRotation(angle) {
    rotationAngle = normalizeAngle(angle);
    rotationSlider.value = rotationAngle;
    rotationValue.innerHTML = rotationAngle + '&deg;';
    renderEditor(true);
}

function getCanvasPoint(event) {
    const bounds = canvas.getBoundingClientRect();
    const scaleX = canvas.width / bounds.width;
    const scaleY = canvas.height / bounds.height;

    return {
        x: Math.max(0, Math.min(canvas.width, (event.clientX - bounds.left) * scaleX)),
        y: Math.max(0, Math.min(canvas.height, (event.clientY - bounds.top) * scaleY))
    };
}

function updateCropFromDrag(point) {
    const x = Math.min(dragStart.x, point.x);
    const y = Math.min(dragStart.y, point.y);
    const width = Math.abs(point.x - dragStart.x);
    const height = Math.abs(point.y - dragStart.y);

    cropRect = {
        x: x,
        y: y,
        width: Math.max(12, width),
        height: Math.max(12, height)
    };

    renderEditor(false);
}

async function canvasToDataUrl(outputCanvas) {
    return new Promise(function(resolve, reject) {
        outputCanvas.toBlob(
            function(blob) {
                if (!blob) {
                    reject(new Error('Unable to export image.'));
                    return;
                }

                const reader = new FileReader();
                reader.onloadend = function() {
                    resolve(reader.result);
                };
                reader.onerror = function() {
                    reject(new Error('Unable to read exported image.'));
                };
                reader.readAsDataURL(blob);
            },
            'image/png'
        );
    });
}

function limitOutputSize(outputCanvas) {
    const maxDimension = 2200;
    const largestSide = Math.max(outputCanvas.width, outputCanvas.height);

    if (largestSide <= maxDimension) {
        return outputCanvas;
    }

    const scale = maxDimension / largestSide;
    const resizedCanvas = document.createElement('canvas');
    const resizedContext = resizedCanvas.getContext('2d');

    resizedCanvas.width = Math.max(1, Math.round(outputCanvas.width * scale));
    resizedCanvas.height = Math.max(1, Math.round(outputCanvas.height * scale));
    resizedContext.drawImage(outputCanvas, 0, 0, resizedCanvas.width, resizedCanvas.height);

    return resizedCanvas;
}

async function exportEditedImage(useCrop) {
    const rotatedCanvas = buildRotatedCanvas();
    const outputCanvas = document.createElement('canvas');
    const outputContext = outputCanvas.getContext('2d');

    if (useCrop && cropRect) {
        const sourceScaleX = rotatedCanvas.width / canvas.width;
        const sourceScaleY = rotatedCanvas.height / canvas.height;
        const sx = Math.max(0, Math.round(cropRect.x * sourceScaleX));
        const sy = Math.max(0, Math.round(cropRect.y * sourceScaleY));
        const sw = Math.min(rotatedCanvas.width - sx, Math.round(cropRect.width * sourceScaleX));
        const sh = Math.min(rotatedCanvas.height - sy, Math.round(cropRect.height * sourceScaleY));

        outputCanvas.width = Math.max(1, sw);
        outputCanvas.height = Math.max(1, sh);
        outputContext.drawImage(rotatedCanvas, sx, sy, sw, sh, 0, 0, outputCanvas.width, outputCanvas.height);
    }
    else {
        outputCanvas.width = rotatedCanvas.width;
        outputCanvas.height = rotatedCanvas.height;
        outputContext.drawImage(rotatedCanvas, 0, 0);
    }

    return canvasToDataUrl(limitOutputSize(outputCanvas));
}

async function loadSource(src, message) {
    showOverlay();

    try {
        sourceImage = await loadImage(src);
        currentImage = src;
        setRotation(0);
        resetCropToFullImage();
        renderEditor(true);
        setStatus(message || 'Photo loaded.');
    }
    catch (err) {
        console.error(err);
        alert('Unable to load photo.');
    }
    finally {
        hideOverlay();
    }
}

async function lockRotationForCrop() {
    if (!sourceImage) {
        return;
    }

    showOverlay();

    try {
        const rotatedCanvas = buildRotatedCanvas();
        const bakedImage = await canvasToDataUrl(rotatedCanvas);
        sourceImage = await loadImage(bakedImage);
        currentImage = bakedImage;
        rotationAngle = 0;
        rotationSlider.value = 0;
        rotationValue.innerHTML = '0&deg;';
        renderEditor(true);
        setStatus('Rotation locked. Draw the crop box now.');
    }
    catch (err) {
        console.error(err);
        alert('Unable to lock rotation for crop.');
    }
    finally {
        hideOverlay();
    }
}

rotationSlider.addEventListener('input', function() {
    setRotation(parseInt(this.value, 10) || 0);
});

document.querySelectorAll('[data-rotate]').forEach(function(button) {
    button.addEventListener('click', function() {
        setRotation(rotationAngle + (parseInt(this.dataset.rotate, 10) || 0));
    });
});

document.getElementById('lockRotationBtn').addEventListener('click', lockRotationForCrop);

document.getElementById('resetCropBtn').addEventListener('click', function() {
    resetCropToFullImage();
    renderEditor(false);
    setStatus('Crop reset to full image.');
});

gridToggleBtn.addEventListener('click', function() {
    gridEnabled = !gridEnabled;
    this.textContent = gridEnabled ? 'Grid On' : 'Grid Off';
    renderEditor(false);
});

document.getElementById('restoreOriginalBtn').addEventListener('click', function() {
    loadSource(originalImage, 'Original photo restored in the editor. Save to make it permanent.');
});

canvas.addEventListener('mousedown', function(event) {
    if (rotationAngle !== 0) {
        setStatus('Lock rotation before cropping a rotated image.');
        return;
    }

    isDraggingCrop = true;
    dragStart = getCanvasPoint(event);
    cropRect = {
        x: dragStart.x,
        y: dragStart.y,
        width: 12,
        height: 12
    };
    renderEditor(false);
});

canvas.addEventListener('mousemove', function(event) {
    if (!isDraggingCrop) {
        return;
    }

    updateCropFromDrag(getCanvasPoint(event));
});

window.addEventListener('mouseup', function() {
    if (!isDraggingCrop) {
        return;
    }

    isDraggingCrop = false;
    setStatus('Crop area updated.');
});

window.addEventListener('resize', function() {
    renderEditor(true);
});

document
.getElementById('saveForm')
.addEventListener('submit', async function(e) {
    e.preventDefault();
    showOverlay();

    try {
        document.getElementById('cropped_image').value = await exportEditedImage(true);
        HTMLFormElement.prototype.submit.call(this);
    }
    catch (err) {
        console.error(err);
        hideOverlay();
        alert('Unable to save image.');
    }
});

document
.getElementById('removeBgBtn')
.addEventListener('click', async function() {
    showOverlay();
    this.disabled = true;

    try {
        const imageData = await exportEditedImage(true);
        const formData = new URLSearchParams();

        formData.append('image', imageData);

        const response = await fetch(
            'remove_background.php',
            {
                method: 'POST',
                credentials: 'same-origin',
                body: formData
            }
        );

        const result = await response.json();

        if (!result.success) {
            alert(result.error || 'Background removal failed.');
            return;
        }

        await loadSource(result.image, 'Background removed. Review and save when ready.');
    }
    catch (err) {
        console.error(err);
        alert('Background removal failed.');
    }
    finally {
        hideOverlay();
        this.disabled = false;
    }
});

loadSource(currentImage, 'Photo loaded.');

</script>

<?php include '../includes/footer.php'; ?>
