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
    'https://ops.traintote.com/uploads/' .
    $record['photo_filename'] .
    '?v=' .
    time();

?>

<?php include '../includes/header.php'; ?>

<title>Edit Photo</title>

<link
rel="stylesheet"
href="https://scaleflex.cloudimg.io/v7/plugins/filerobot-image-editor/latest/filerobot-image-editor.min.css">

<style>

#editor_container{
    width:100%;
    height:80vh;
    border:1px solid #ddd;
    border-radius:8px;
}

#processingOverlay{
    display:none;
    position:fixed;
    inset:0;
    background:rgba(255,255,255,.92);
    z-index:99999;
}

.FIE_topbar-save-button{
    display:none !important;
}

</style>

</head>

<body>

<?php include '../includes/navbar.php'; ?>

<div class="container-fluid mt-4">

<h1>Edit Photo</h1>

<h4 class="text-muted mb-4">

<?php echo htmlspecialchars($title); ?>

</h4>

<div id="editor_container"></div>

<hr>

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
class="btn btn-success">

Save Changes

</button>

<a
href="../<?php echo $type; ?>/view.php?id=<?php echo $id; ?>"
class="btn btn-secondary">

Cancel

</a>

<button
type="button"
class="btn btn-info"
id="removeBgBtn">

Remove Background

</button>

</form>

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

<script src="https://scaleflex.cloudimg.io/v7/plugins/filerobot-image-editor/latest/filerobot-image-editor.min.js"></script>

<script>

const FilerobotImageEditor =
    window.FilerobotImageEditor;

let currentImage =
    '<?php echo htmlspecialchars($imageUrl); ?>';

let editor = null;

function openEditor() {

    const container =
        document.getElementById(
            'editor_container'
        );

    container.innerHTML = '';

    editor =
        new FilerobotImageEditor(

            container,

            {

                source:
                    currentImage,

                showBackButton:
                    false,

                closeAfterSave:
                    false,

                onSave:
                    () => {},

                tabsIds: [

                    'adjust',
                    'finetune',
                    'filters',
                    'crop'

                ]

            }

        );

    editor.render();

}

openEditor();

document
.getElementById(
    'saveForm'
)
.addEventListener(
    'submit',
    async function(e){

        e.preventDefault();

        const overlay =
            document.getElementById(
                'processingOverlay'
            );

        overlay.style.display = 'block';

        try {

            const imageObject =
                await editor.getCurrentImgData();

            if (
                !imageObject ||
                !imageObject.imageData ||
                !imageObject.imageData.imageBase64
            ) {

                overlay.style.display =
                    'none';

                alert(
                    'Unable to retrieve edited image.'
                );

                return;

            }

            document
                .getElementById(
                    'cropped_image'
                )
                .value =
                imageObject
                    .imageData
                    .imageBase64;

            HTMLFormElement
                .prototype
                .submit
                .call(
                    this
                );

        }
        catch(err){

            console.error(
                err
            );

            overlay.style.display =
                'none';

            alert(
                'Unable to save image.'
            );

        }

    }
);

document
.getElementById(
    'removeBgBtn'
)
.addEventListener(
    'click',
    async function(){

        const overlay =
            document.getElementById(
                'processingOverlay'
            );

        overlay.style.display =
            'block';

        this.disabled = true;

        try {

            const imageObject =
                await editor.getCurrentImgData();

            if (
                !imageObject ||
                !imageObject.imageData ||
                !imageObject.imageData.imageBase64
            ) {

                overlay.style.display =
                    'none';

                this.disabled =
                    false;

                alert(
                    'Unable to retrieve image.'
                );

                return;

            }

            const formData =
                new URLSearchParams();

            formData.append(

                'image',

                imageObject
                    .imageData
                    .imageBase64

            );

            const response =
                await fetch(

                    'remove_background.php',

                    {

                        method:
                            'POST',

                        credentials:
                            'same-origin',

                        body:
                            formData

                    }

                );

            const result =
                await response.json();

            overlay.style.display =
                'none';

            this.disabled =
                false;

            if (
                !result.success
            ) {

                alert(

                    result.error ||

                    'Background removal failed.'

                );

                return;

            }

            currentImage =
                result.image;

            openEditor();

        }
        catch(err){

            console.error(
                err
            );

            overlay.style.display =
                'none';

            this.disabled =
                false;

            alert(
                'Background removal failed.'
            );

        }

    }
);

</script>

<?php include '../includes/footer.php'; ?>
