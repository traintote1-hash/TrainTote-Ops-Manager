<?php

session_start();

require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

if (!isset($_GET['id'])) {
    die('Waybill ID missing.');
}

$id = (int)$_GET['id'];

$stmt = $pdo->prepare("
    SELECT *
    FROM waybills
    WHERE id = :id
    LIMIT 1
");

$stmt->execute([
    'id' => $id
]);

$waybill = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$waybill) {
    die('Waybill not found.');
}

$cycleStmt = $pdo->prepare("
    SELECT

        wc.*,

        oi.industry_name AS origin_name,

        di.industry_name AS destination_name

    FROM waybill_cycles wc

    LEFT JOIN industries oi
        ON wc.origin_industry_id = oi.id

    LEFT JOIN industries di
        ON wc.destination_industry_id = di.id

    WHERE wc.waybill_id = :waybill_id

    ORDER BY wc.cycle_number
");

$cycleStmt->execute([
    'waybill_id' => $id
]);

$cycles = [];

foreach ($cycleStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $cycles[$row['cycle_number']] = $row;
}

?>

<?php include '../includes/header.php'; ?>

<title>Print Waybill V2</title>

<style>

body{
    background:#f5f5f5;
}

.print-controls{
    text-align:center;
    margin:20px;
}

.waybill-2{

    width:2.25in;
    height:2.75in;

    margin:20px auto;

    border:1px solid #000;

    background:#fff;

    position:relative;

    font-family:Arial, Helvetica, sans-serif;
}

.waybill-4{

    width:4.50in;
    height:2.75in;

    margin:20px auto;

    border:1px solid #000;

    background:#fff;

    position:relative;

    font-family:Arial, Helvetica, sans-serif;
}

.panel{

    position:absolute;

    width:100%;
    height:50%;
    box-sizing:border-box;

    padding:6px;
}

.top-half{

    top:0;

    border-bottom:1px solid #999;
}

.bottom-half{

    bottom:0;

    border-top:1px solid #999;

    transform:rotate(180deg);
}

.left-side{

    position:absolute;

    left:0;

    top:0;

    width:50%;

    height:100%;
}

.right-side{

    position:absolute;

    right:0;

    top:0;

    width:50%;

    height:100%;

    border-left:3px solid #000;
}

.wb-title{

    font-size:24px;

    font-weight:900;

    text-transform:uppercase;

    margin-bottom:8px;

    line-height:1;
}

.wb-number{

    float:right;

    font-size:26px;

    font-weight:900;

    line-height:1;
}

.wb-body{

    font-size:9px;

    line-height:1.2;
}

.field{

    margin-bottom:2px;
}

@media print {

    .print-controls,
    .navbar,
    footer{
        display:none !important;
    }

    body{
        background:#fff;
    }

    .waybill-2,
    .waybill-4{
        margin:0;
    }
}

</style>

</head>

<body>

<?php include '../includes/navbar.php'; ?>

<div class="print-controls">

<a
href="view.php?id=<?php echo $waybill['id']; ?>"
class="btn btn-secondary me-2">

Back to Waybill

</a>

<button
onclick="window.print();"
class="btn btn-primary">

Print

</button>

</div>

<?php if ((int)$waybill['cycle_count'] <= 2): ?>

<div class="waybill-2">


<div class="panel top-half">

    <div class="wb-title">

        Waybill

        <span class="wb-number">1</span>

    </div>

    <div class="wb-body">

        <div class="field">
            Origin:
            <?php echo htmlspecialchars($cycles[1]['origin_name'] ?? ''); ?>
        </div>

        <div class="field">
            Via:
            <?php echo htmlspecialchars($cycles[1]['route'] ?? ''); ?>
        </div>

        <div class="field">
            Destination:
            <?php echo htmlspecialchars($cycles[1]['destination_name'] ?? ''); ?>
        </div>

        <div class="field">
            Contents:
            <?php echo htmlspecialchars($cycles[1]['commodity'] ?? ''); ?>
        </div>

        <div class="field">
            Notes:
            <?php echo htmlspecialchars($cycles[1]['status'] ?? ''); ?>
        </div>

    </div>

</div>

<div class="panel bottom-half">

    <div class="wb-title">

        Waybill

        <span class="wb-number">2</span>

    </div>

    <div class="wb-body">

        <div class="field">
            Origin:
            <?php echo htmlspecialchars($cycles[2]['origin_name'] ?? ''); ?>
        </div>

        <div class="field">
            Via:
            <?php echo htmlspecialchars($cycles[2]['route'] ?? ''); ?>
        </div>

        <div class="field">
            Destination:
            <?php echo htmlspecialchars($cycles[2]['destination_name'] ?? ''); ?>
        </div>

        <div class="field">
            Contents:
            <?php echo htmlspecialchars($cycles[2]['commodity'] ?? ''); ?>
        </div>

        <div class="field">
            Notes:
            <?php echo htmlspecialchars($cycles[2]['status'] ?? ''); ?>
        </div>

    </div>

</div>


</div>

<?php else: ?>

<div class="waybill-4">


<div class="left-side">

    <div class="panel top-half">

        <div class="wb-title">
            Waybill <span class="wb-number">1</span>
        </div>

        <div class="wb-body">

            Origin: <?php echo htmlspecialchars($cycles[1]['origin_name'] ?? ''); ?><br>
            Via: <?php echo htmlspecialchars($cycles[1]['route'] ?? ''); ?><br>
            Destination: <?php echo htmlspecialchars($cycles[1]['destination_name'] ?? ''); ?><br>
            Contents: <?php echo htmlspecialchars($cycles[1]['commodity'] ?? ''); ?><br>
            Notes: <?php echo htmlspecialchars($cycles[1]['status'] ?? ''); ?>

        </div>

    </div>

    <div class="panel bottom-half">

        <div class="wb-title">
            Waybill <span class="wb-number">2</span>
        </div>

        <div class="wb-body">

            Origin: <?php echo htmlspecialchars($cycles[2]['origin_name'] ?? ''); ?><br>
            Via: <?php echo htmlspecialchars($cycles[2]['route'] ?? ''); ?><br>
            Destination: <?php echo htmlspecialchars($cycles[2]['destination_name'] ?? ''); ?><br>
            Contents: <?php echo htmlspecialchars($cycles[2]['commodity'] ?? ''); ?><br>
            Notes: <?php echo htmlspecialchars($cycles[2]['status'] ?? ''); ?>

        </div>

    </div>

</div>

<div class="right-side">

    <div class="panel top-half">

        <div class="wb-title">
            Waybill <span class="wb-number">3</span>
        </div>

        <div class="wb-body">

            Origin: <?php echo htmlspecialchars($cycles[3]['origin_name'] ?? ''); ?><br>
            Via: <?php echo htmlspecialchars($cycles[3]['route'] ?? ''); ?><br>
            Destination: <?php echo htmlspecialchars($cycles[3]['destination_name'] ?? ''); ?><br>
            Contents: <?php echo htmlspecialchars($cycles[3]['commodity'] ?? ''); ?><br>
            Notes: <?php echo htmlspecialchars($cycles[3]['status'] ?? ''); ?>

        </div>

    </div>

    <div class="panel bottom-half">

        <div class="wb-title">
            Waybill <span class="wb-number">4</span>
        </div>

        <div class="wb-body">

            Origin: <?php echo htmlspecialchars($cycles[4]['origin_name'] ?? ''); ?><br>
            Via: <?php echo htmlspecialchars($cycles[4]['route'] ?? ''); ?><br>
            Destination: <?php echo htmlspecialchars($cycles[4]['destination_name'] ?? ''); ?><br>
            Contents: <?php echo htmlspecialchars($cycles[4]['commodity'] ?? ''); ?><br>
            Notes: <?php echo htmlspecialchars($cycles[4]['status'] ?? ''); ?>

        </div>

    </div>

</div>

</div>

<?php endif; ?>

<?php include '../includes/footer.php'; ?>
