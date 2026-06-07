<?php

session_start();

require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$stmt = $pdo->prepare("
    SELECT id
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

$stmt = $pdo->prepare("
    SELECT
        id,
        reporting_marks,
        road_number
    FROM equipment
    WHERE railroad_id = :railroad_id
    ORDER BY reporting_marks, road_number
");

$stmt->execute([
    'railroad_id' => $railroad['id']
]);

$equipment = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("
    SELECT
        id,
        industry_name
    FROM industries
    WHERE railroad_id = :railroad_id
    ORDER BY industry_name
");

$stmt->execute([
    'railroad_id' => $railroad['id']
]);

$industries = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $pdo->beginTransaction();

    try {

        $stmt = $pdo->prepare("
            INSERT INTO waybills
(
    railroad_id,
    equipment_id,
    current_cycle,
    cycle_count
)
VALUES
(
    :railroad_id,
    :equipment_id,
    1,
    :cycle_count
)
        ");

       $stmt->execute([
    'railroad_id' => $railroad['id'],
    'equipment_id' => $_POST['equipment_id'],
    'cycle_count' => (int)$_POST['cycle_count']
]);

        $waybillId = $pdo->lastInsertId();

        $cycleStmt = $pdo->prepare("
            INSERT INTO waybill_cycles
            (
                waybill_id,
                cycle_number,
                origin_industry_id,
                destination_industry_id,
                commodity,
                route,
                status
            )
            VALUES
            (
                :waybill_id,
                :cycle_number,
                :origin_industry_id,
                :destination_industry_id,
                :commodity,
                :route,
                :status
            )
        ");

$cycleCount = (int)$_POST['cycle_count'];

for ($cycle = 1; $cycle <= $cycleCount; $cycle++) {

            $cycleStmt->execute([

                'waybill_id' => $waybillId,

                'cycle_number' => $cycle,

                'origin_industry_id' =>
                    !empty($_POST["cycle{$cycle}_origin"])
                    ? $_POST["cycle{$cycle}_origin"]
                    : null,

                'destination_industry_id' =>
                    !empty($_POST["cycle{$cycle}_destination"])
                    ? $_POST["cycle{$cycle}_destination"]
                    : null,

                'commodity' =>
                    trim($_POST["cycle{$cycle}_commodity"]),

                'route' =>
                    trim($_POST["cycle{$cycle}_route"]),

                'status' =>
                    $_POST["cycle{$cycle}_status"]
            ]);
        }

        $pdo->commit();

        header("Location: saved.php?id=" . $waybillId);
        exit;

    } catch (Exception $e) {

        $pdo->rollBack();

        die($e->getMessage());
    }
}

?>

<?php include '../includes/header.php'; ?>

<title>Add Waybill</title>

<style>

.cycle-card {

    border:1px solid #ddd;

    border-radius:8px;

    padding:20px;

    margin-bottom:25px;

    background:#fafafa;
}

.cycle-title {

    font-size:1.25rem;

    font-weight:bold;

    margin-bottom:15px;

    color:#0d6efd;
}

</style>

</head>

<body>

<?php include '../includes/navbar.php'; ?>

<div class="container mt-5">

<h1>Add Waybill</h1>

<form method="post">

<div class="card mb-4">

<div class="card-body">

<label class="form-label">

Equipment

</label>

<select
name="equipment_id"
class="form-select"
required>

<option value="">

Select Equipment

</option>

<?php foreach ($equipment as $item): ?>

<option value="<?php echo $item['id']; ?>">

<?php
echo htmlspecialchars(
    $item['reporting_marks']
    . ' '
    . $item['road_number']
);
?>

</option>

<?php endforeach; ?>

</select>

<div class="mt-3">

<label class="form-label">

Waybill Type

</label>

<select
name="cycle_count"
id="cycle_count"
class="form-select">

<option value="2">

2-Cycle Waybill

</option>

<option value="4" selected>

4-Cycle Waybill

</option>

</select>

</div>

</div>

</div>

<?php for ($cycle = 1; $cycle <= 4; $cycle++): ?>

<div
class="cycle-card"
data-cycle="<?php echo $cycle; ?>">

<div class="cycle-title">

Cycle <?php echo $cycle; ?>

</div>

<div class="row">

<div class="col-md-6 mb-3">

<label class="form-label">

Origin

</label>

<select
name="cycle<?php echo $cycle; ?>_origin"
class="form-select">

<option value="">

Select Origin

</option>

<?php foreach ($industries as $industry): ?>

<option value="<?php echo $industry['id']; ?>">

<?php echo htmlspecialchars($industry['industry_name']); ?>

</option>

<?php endforeach; ?>

</select>

</div>

<div class="col-md-6 mb-3">

<label class="form-label">

Destination

</label>

<select
name="cycle<?php echo $cycle; ?>_destination"
class="form-select">

<option value="">

Select Destination

</option>

<?php foreach ($industries as $industry): ?>

<option value="<?php echo $industry['id']; ?>">

<?php echo htmlspecialchars($industry['industry_name']); ?>

</option>

<?php endforeach; ?>

</select>

</div>

<div class="col-md-6 mb-3">

<label class="form-label">

Commodity

</label>

<input
type="text"
name="cycle<?php echo $cycle; ?>_commodity"
class="form-control">

</div>

<div class="col-md-6 mb-3">

<label class="form-label">

Route

</label>

<input
type="text"
name="cycle<?php echo $cycle; ?>_route"
class="form-control">

</div>

<div class="col-md-6 mb-3">

<label class="form-label">

Status

</label>

<select
name="cycle<?php echo $cycle; ?>_status"
class="form-select">

<option value="Loaded">

Loaded

</option>

<option value="Empty">

Empty

</option>

</select>

</div>

</div>

</div>

<?php endfor; ?>

<button
type="submit"
class="btn btn-success">

Save Waybill

</button>

<a
href="list.php"
class="btn btn-secondary">

Cancel

</a>

</form>

</div>
<script>

function updateCycleDisplay() {

    const cycleCount =
        parseInt(
            document.getElementById('cycle_count').value
        );

    document
        .querySelectorAll('.cycle-card')
        .forEach(card => {

            const cycle =
                parseInt(
                    card.dataset.cycle
                );

            if (cycle <= cycleCount) {

                card.style.display = 'block';

            } else {

                card.style.display = 'none';

            }

        });
}

document
    .getElementById('cycle_count')
    .addEventListener(
        'change',
        updateCycleDisplay
    );

updateCycleDisplay();

</script>
<?php include '../includes/footer.php'; ?>