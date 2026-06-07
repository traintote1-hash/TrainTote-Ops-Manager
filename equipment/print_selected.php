<?php

session_start();

require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

if (empty($_POST['equipment_ids'])) {
    die('No equipment selected.');
}

$ids = array_map(
    'intval',
    $_POST['equipment_ids']
);

$idList = implode(',', $ids);

$stmt = $pdo->query("
    SELECT e.*
    FROM equipment e
    WHERE e.id IN ($idList)
    ORDER BY reporting_marks, road_number
");

$equipmentList = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$equipmentList) {
    die('No equipment found.');
}

?>

<?php include '../includes/header.php'; ?>

<title>Car Card V3</title>

<style>

@page {
    size: 11in 8.5in;
    margin: 0;
}

body{
    background:#f5f5f5;
}

.card-blank{

    width:4.375in;
    height:5.875in;

    margin:0;
    position:relative;

    background:transparent;
}
.center-card{

    position:absolute;

    left:1in;

    top:0;

    width:2.375in;

    height:5.875in;

    border:2px solid #000;

    box-sizing:border-box;
}

.left-tab{

    position:absolute;

    left:0;

    bottom:0;

    width:1in;

    height:1.375in;

    border:1px dashed #999;

    box-sizing:border-box;
}

.right-tab{

    position:absolute;

    right:0;

    bottom:0;

    width:1in;

    height:1.375in;

    border:1px dashed #999;

    box-sizing:border-box;
}

.tab-text{

    writing-mode:vertical-rl;

    transform:rotate(180deg);

    text-align:center;

    height:100%;

    display:flex;

    align-items:center;

    justify-content:center;

    color:#666;

    font-size:10px;
}

.top-panel{

    height:1.75in;

    border-bottom:1px solid #999;

    box-sizing:border-box;

    padding:6px;
}

.photo{

    height:.85in;

    margin-bottom:6px;

    text-align:center;
}

.photo img{

    max-width:100%;

    max-height:100%;

    object-fit:contain;
}

.no-photo{

    height:100%;

    display:flex;

    align-items:center;

    justify-content:center;

    font-size:11px;

    color:#666;
}

.info{

    font-size:10px;

    line-height:1.25;
}

.info strong{

    display:inline-block;

    width:72px;
}

.window-panel{

    height:1.375in;

    border-bottom:1px solid #999;

    background:
    repeating-linear-gradient(
        45deg,
        #fafafa,
        #fafafa 10px,
        #f2f2f2 10px,
        #f2f2f2 20px
    );

    display:flex;

    align-items:center;

    justify-content:center;

    color:#999;

    font-size:12px;
}

.back-panel{

    height:1.375in;

    border-bottom:2px dashed #c44;

    display:flex;

    align-items:center;

    justify-content:center;

    color:#777;

    font-size:12px;
}

.flap{

    height:1.375in;

    display:flex;

    align-items:center;

    justify-content:center;

    color:#777;

    font-size:12px;
}

.fold-note{

    position:absolute;

    width:100%;

    text-align:center;

    font-size:10px;

    color:#999;
}

.fold1{

    top:4.48in;
}

.fold2{

    top:3.10in;
}

.instructions{

    position:absolute;

    top:10px;

    left:10px;

    font-size:10px;

    color:#999;
}
.sheet {

    width: 11in;
    height: 8.5in;

    margin: 0;

    position: relative;
}

.card-wrapper {

    width:4.375in;
    height:5.875in;

    position:relative;
}
.sheet {

    width: 11in;
    height: 8.5in;

    display:flex;

    justify-content:center;

    gap:.50in;

    align-items:flex-start;

    margin:0 auto;
}

.card-wrapper {

    width:4.375in;
    height:5.875in;

    position:relative;
}
@media print{

    .no-print,
    .navbar,
    footer{

        display:none !important;
    }

    body{

        background:transparent;
    }

    .card-blank{

        margin:0;
    }
}

</style>

</head>

<body>

<?php include '../includes/navbar.php'; ?>

<div class="container mt-4 no-print">

    <h1>Car Card V3 Prototype</h1>

    <a
    href="list.php"
    class="btn btn-secondary me-2">

    Back

    </a>

    <button
    onclick="window.print();"
    class="btn btn-primary">

    Print

    </button>

</div>
<div class="sheet">

<div class="sheet">

<?php foreach ($equipmentList as $equipment): ?>

<div class="card-wrapper">



<div class="card-blank">



    <div class="center-card">

        <div class="top-panel">

            <div class="photo">

            <?php if (!empty($equipment['photo_filename'])): ?>

                <img
                src="../uploads/<?php echo htmlspecialchars($equipment['photo_filename']); ?>?v=<?php echo time(); ?>"
                alt="Photo">

            <?php else: ?>

                <div class="no-photo">

                    NO PHOTO

                </div>

            <?php endif; ?>

            </div>

            <div class="info">

                <div>
                    <strong>Road Name:</strong>
                    <?php echo htmlspecialchars($equipment['road_name']); ?>
                </div>

                <div>
                    <strong>Car Number:</strong>
                    <?php echo htmlspecialchars($equipment['road_number']); ?>
                </div>

                <div>
                    <strong>Type:</strong>
                    <?php echo htmlspecialchars($equipment['equipment_type']); ?>
                </div>

                <div>
                    <strong>Length:</strong>
                    <?php echo htmlspecialchars($equipment['length_ft']); ?>'
                </div>

                <div>
                    <strong>Color:</strong>
                    <?php echo htmlspecialchars($equipment['color']); ?>
                </div>

            </div>

        </div>

        <div class="window-panel">

            WAYBILL WINDOW

        </div>

        <div class="back-panel">

            FOLD TO HERE

        </div>

        <div class="flap">

            1ST FOLD

        </div>

    </div>

    <div class="left-tab">

        <div class="tab-text">

            TAPE DOWN ON BACKSIDE

        </div>

    </div>

    <div class="right-tab">

        <div class="tab-text">

            TAPE DOWN ON BACKSIDE

        </div>

    </div>



</div>   <!-- card-blank -->

</div>   <!-- card-wrapper -->

<?php endforeach; ?>

</div>   <!-- sheet -->

<?php include '../includes/footer.php'; ?>

</body>
</html>