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

$defaultIndustryServiceOptions = [
    'All' => 'All / Any Service',
    'Grain' => 'Grain',
    'Cement' => 'Cement',
    'Sand' => 'Sand',
    'Sand / Gravel' => 'Sand / Gravel',
    'Gravel' => 'Gravel',
    'Coal' => 'Coal',
    'Aggregate' => 'Aggregate',
    'Scrap Metal' => 'Scrap Metal',
    'Steel / Pipe' => 'Steel / Pipe',
    'Rail' => 'Rail',
    'MOW / Riprap' => 'MOW / Riprap',
    'Vegetable Oil' => 'Vegetable Oil',
    'Frozen Food' => 'Frozen Food',
    'Food Products' => 'Food Products',
    'Food Grade Liquid' => 'Food Grade Liquid',
    'Fuel' => 'Fuel',
    'Propane' => 'Propane',
    'Chemicals' => 'Chemicals',
    'Asphalt' => 'Asphalt',
    'Corn Syrup' => 'Corn Syrup',
    'General Freight' => 'General Freight',
    'Paper' => 'Paper',
    'Paper Rolls' => 'Paper Rolls',
    'Beer' => 'Beer',
    'Auto Parts' => 'Auto Parts',
    'Furniture' => 'Furniture',
    'Appliances' => 'Appliances',
    'Lumber' => 'Lumber',
    'Building Materials' => 'Building Materials',
    'Pipe' => 'Pipe',
    'Machinery' => 'Machinery',
    'Steel' => 'Steel',
    'Oil' => 'Oil'
];

function normalizeIndustryServiceKey($value): string
{
    return strtolower(trim(preg_replace('/\s+/', ' ', (string)$value)));
}

function splitIndustryServiceValues($value): array
{
    $parts = preg_split('/[\r\n,]+/', (string)$value);
    $services = [];

    foreach ($parts as $part) {
        $service = trim($part);

        if ($service !== '') {
            $services[] = $service;
        }
    }

    return $services;
}

function addIndustryServiceOption(array &$options, string $value, ?string $label = null): void
{
    $value = trim($value);

    if ($value === '') {
        return;
    }

    $normalized = normalizeIndustryServiceKey($value);

    if (in_array($normalized, ['all / any service', 'any', '*'], true)) {
        $value = 'All';
        $label = 'All / Any Service';
        $normalized = 'all';
    }

    foreach ($options as $existingValue => $existingLabel) {
        if (normalizeIndustryServiceKey($existingValue) === $normalized) {
            return;
        }
    }

    $options[$value] = $label ?? $value;
}

function buildIndustryServiceOptions(PDO $pdo, int $railroadId, array $defaultOptions, array $selectedValues = []): array
{
    $options = $defaultOptions;

    $stmt = $pdo->prepare("
        SELECT operations_service AS service_value
        FROM equipment
        WHERE railroad_id = :railroad_id
            AND operations_service IS NOT NULL
            AND operations_service <> ''
    
        UNION ALL
    
        SELECT receives_services AS service_value
        FROM industries
        WHERE railroad_id = :railroad_id
            AND receives_services IS NOT NULL
            AND receives_services <> ''
    
        UNION ALL
    
        SELECT ships_services AS service_value
        FROM industries
        WHERE railroad_id = :railroad_id
            AND ships_services IS NOT NULL
            AND ships_services <> ''
    
    " );

    $stmt->execute([
        'railroad_id' => $railroadId
    ]);

    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $serviceList) {
        foreach (splitIndustryServiceValues($serviceList) as $service) {
            addIndustryServiceOption($options, $service);
        }
    }

    foreach ($selectedValues as $serviceList) {
        foreach (splitIndustryServiceValues($serviceList) as $service) {
            addIndustryServiceOption($options, $service);
        }
    }

    uasort($options, function ($a, $b) {
        if ($a === 'All / Any Service') {
            return -1;
        }

        if ($b === 'All / Any Service') {
            return 1;
        }

        return strcasecmp($a, $b);
    });

    return $options;
}

function buildIndustryServicePostValue(string $fieldName): string
{
    $services = $_POST[$fieldName] ?? [];

    if (!is_array($services)) {
        $services = [$services];
    }

    $customValue = $_POST[$fieldName . '_custom'] ?? '';

    foreach (splitIndustryServiceValues($customValue) as $service) {
        $services[] = $service;
    }

    $cleanServices = [];

    foreach ($services as $service) {
        $service = trim((string)$service);

        if ($service === '') {
            continue;
        }

        if (in_array(normalizeIndustryServiceKey($service), ['all / any service', 'any', '*'], true)) {
            $service = 'All';
        }

        $normalized = normalizeIndustryServiceKey($service);

        if (!isset($cleanServices[$normalized])) {
            $cleanServices[$normalized] = $service;
        }
    }

    return implode(', ', array_values($cleanServices));
}

function renderIndustryServiceCheckboxes(string $fieldName, string $label, string $helperText, array $options, string $selectedValue, string $customPlaceholder): void
{
    $selectedServices = [];

    foreach (splitIndustryServiceValues($selectedValue) as $service) {
        $normalized = normalizeIndustryServiceKey($service);

        if (in_array($normalized, ['all / any service', 'any', '*'], true)) {
            $normalized = 'all';
        }

        $selectedServices[$normalized] = true;
    }
    ?>

<div class="mb-3">

<label class="form-label"><?php echo htmlspecialchars($label); ?></label>

<div class="form-text mb-2"><?php echo htmlspecialchars($helperText); ?></div>

<div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-2">

<?php foreach ($options as $value => $displayLabel): ?>
<?php
$optionKey = normalizeIndustryServiceKey($value);
if (in_array($optionKey, ['all / any service', 'any', '*'], true)) {
    $optionKey = 'all';
}
?>

<div class="col">

<div class="form-check">

<input
type="checkbox"
name="<?php echo htmlspecialchars($fieldName); ?>[]"
value="<?php echo htmlspecialchars($value); ?>"
class="form-check-input"
id="<?php echo htmlspecialchars($fieldName . '_' . md5($value)); ?>"
<?php if (isset($selectedServices[$optionKey])) echo 'checked'; ?>>

<label
class="form-check-label"
for="<?php echo htmlspecialchars($fieldName . '_' . md5($value)); ?>">
<?php echo htmlspecialchars($displayLabel); ?>
</label>

</div>

</div>

<?php endforeach; ?>

</div>

<input
type="text"
name="<?php echo htmlspecialchars($fieldName . '_custom'); ?>"
class="form-control mt-2"
placeholder="<?php echo htmlspecialchars($customPlaceholder); ?>">

</div>

<?php
}

$industryServiceOptions = buildIndustryServiceOptions(
    $pdo,
    (int)$industry['railroad_id'],
    $defaultIndustryServiceOptions,
    [
        $industry['receives_services'] ?? '',
        $industry['ships_services'] ?? ''
    ]
);

$previousIndustryId = null;
$nextIndustryId = null;

$stmt = $pdo->prepare("
    SELECT id
    FROM industries
    WHERE railroad_id = :railroad_id
    ORDER BY industry_name ASC, id ASC
");

$stmt->execute([
    'railroad_id' => $industry['railroad_id']
]);

$industryIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
$currentIndustryIndex = array_search($industry['id'], $industryIds);

if ($currentIndustryIndex !== false) {
    if ($currentIndustryIndex > 0) {
        $previousIndustryId = $industryIds[$currentIndustryIndex - 1];
    }

    if ($currentIndustryIndex < count($industryIds) - 1) {
        $nextIndustryId = $industryIds[$currentIndustryIndex + 1];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $industry_name = trim($_POST['industry_name']);
    $industry_type = trim($_POST['industry_type']);
    $location = trim($_POST['location']);
    $track_capacity = (int)$_POST['track_capacity'];
    $receives_services = buildIndustryServicePostValue('receives_services');
    $ships_services = buildIndustryServicePostValue('ships_services');
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

<div class="mb-4">

<?php if ($previousIndustryId): ?>

<a
href="edit.php?id=<?php echo (int)$previousIndustryId; ?>"
class="btn btn-outline-secondary me-2">

Previous Industry

</a>

<?php else: ?>

<span class="btn btn-outline-secondary me-2 disabled">

Previous Industry

</span>

<?php endif; ?>

<?php if ($nextIndustryId): ?>

<a
href="edit.php?id=<?php echo (int)$nextIndustryId; ?>"
class="btn btn-outline-secondary me-2">

Next Industry

</a>

<?php else: ?>

<span class="btn btn-outline-secondary me-2 disabled">

Next Industry

</span>

<?php endif; ?>

</div>

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

<?php
renderIndustryServiceCheckboxes(
    'receives_services',
    'Unloads Inbound Cars Carrying',
    'Loaded cars delivered here become empty after this industry works them.',
    $industryServiceOptions,
    $industry['receives_services'] ?? '',
    'Add another inbound service, such as Frozen Food or Paper Rolls'
);

renderIndustryServiceCheckboxes(
    'ships_services',
    'Loads Outbound Cars With',
    'Empty cars delivered here become loaded after this industry works them.',
    $industryServiceOptions,
    $industry['ships_services'] ?? '',
    'Add another outbound service, such as Grain or Scrap Metal'
);
?>

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
class="btn btn-secondary me-2">

Cancel

</a>

<?php if ($previousIndustryId): ?>

<a
href="edit.php?id=<?php echo (int)$previousIndustryId; ?>"
class="btn btn-outline-secondary me-2">

Previous Industry

</a>

<?php else: ?>

<span class="btn btn-outline-secondary me-2 disabled">

Previous Industry

</span>

<?php endif; ?>

<?php if ($nextIndustryId): ?>

<a
href="edit.php?id=<?php echo (int)$nextIndustryId; ?>"
class="btn btn-outline-secondary me-2">

Next Industry

</a>

<?php else: ?>

<span class="btn btn-outline-secondary me-2 disabled">

Next Industry

</span>

<?php endif; ?>

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