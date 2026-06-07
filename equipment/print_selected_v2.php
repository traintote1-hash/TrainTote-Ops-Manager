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

$ids = array_map('intval', $_POST['equipment_ids']);

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

<title>Car Card Print</title>

<style>

@page {
    size: letter portrait;
    margin: 0;
}

body{
    margin:0;
    padding:0;
    background:transparent;
    font-family:Arial, Helvetica, sans-serif;
}

.sheet{

    width:8.5in;
    height:11in;

    position:relative;

    page-break-after:always;

    overflow:hidden;

    background:#fff;
}

.card-slot{

    position:absolute;

    width:5.875in;
    height:4.375in;
}

.top-slot{

    left:1.60in;
    top:.25in;
}

.bottom-slot{

    left:1.10in;
    top:4.45in;
}

.card-blank{

    width:4.375in;
    height:5.875in;

    position:absolute;

    background:transparent;
	
}

.top-slot .card-blank{

    transform:rotate(90deg);
    transform-origin:top left;

    left:4.375in;
    top:0;
}

.bottom-slot .card-blank{

    transform:rotate(270deg);
    transform-origin:top left;

    left:0;
    top:5.875in;
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

    padding:6px;

    box-sizing:border-box;
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
	display:block;
}

.no-photo{

    height:100%;

    display:flex;

    align-items:center;
    justify-content:center;

    color:#666;

    font-size:11px;
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
}.fold-note{

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

@media print{

    html,
    body{

        width:8.5in;
        height:11in;

        margin:0 !important;
        padding:0 !important;

        background:transparent;
    }

    .no-print,
    .navbar,
    footer{

        display:none !important;
    }

    .sheet{

        width:8.5in;
        height:11in;

        margin:0 !important;
        padding:0 !important;

        page-break-after:always;
        
    }

    .card-slot,
    .card-blank,
    .center-card{

        box-sizing:border-box;
    }
}

</style>

</head>

<body>

<?php include '../includes/navbar.php'; ?>

<div class="container mt-4 no-print">

    <h1>Car Card Print</h1>

    <a
    href="list.php"
    class="btn btn-secondary me-2">

    Back to Equipment

    </a>

    <button
    onclick="window.print();"
    class="btn btn-primary">

    Print

    </button>

</div>

<?php

$totalCars = count($equipmentList);

for ($i = 0; $i < $totalCars; $i += 2):

?>

<div class="sheet">

    <?php
    $topCar = $equipmentList[$i];
    $bottomCar = ($i + 1 < $totalCars)
        ? $equipmentList[$i + 1]
        : null;
    ?>

    <!-- TOP CARD -->

    <div class="card-slot top-slot">

        <div class="card-blank">

            <div class="center-card">

                <div class="top-panel">

                    <div class="photo">

                    <?php if (!empty($topCar['photo_filename'])): ?>

                        <img
                        src="../uploads/<?php echo htmlspecialchars($topCar['photo_filename']); ?>?v=<?php echo time(); ?>"
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
                            <?php echo htmlspecialchars($topCar['road_name']); ?>
                        </div>

                        <div>
                            <strong>Car Number:</strong>
                            <?php echo htmlspecialchars($topCar['road_number']); ?>
                        </div>

                        <div>
                            <strong>Type:</strong>
                            <?php echo htmlspecialchars($topCar['equipment_type']); ?>
                        </div>

                        <div>
                            <strong>Length:</strong>
                            <?php echo htmlspecialchars($topCar['length_ft']); ?>'
                        </div>

                        <div>
                            <strong>Color:</strong>
                            <?php echo htmlspecialchars($topCar['color']); ?>
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

        </div>

    </div>
	    <?php if ($bottomCar): ?>

    <!-- BOTTOM CARD -->

    <div class="card-slot bottom-slot">

        <div class="card-blank">

            <div class="center-card">

                <div class="top-panel">

                    <div class="photo">

                    <?php if (!empty($bottomCar['photo_filename'])): ?>

                        <img
                        src="../uploads/<?php echo htmlspecialchars($bottomCar['photo_filename']); ?>?v=<?php echo time(); ?>"
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
                            <?php echo htmlspecialchars($bottomCar['road_name']); ?>
                        </div>

                        <div>
                            <strong>Car Number:</strong>
                            <?php echo htmlspecialchars($bottomCar['road_number']); ?>
                        </div>

                        <div>
                            <strong>Type:</strong>
                            <?php echo htmlspecialchars($bottomCar['equipment_type']); ?>
                        </div>

                        <div>
                            <strong>Length:</strong>
                            <?php echo htmlspecialchars($bottomCar['length_ft']); ?>'
                        </div>

                        <div>
                            <strong>Color:</strong>
                            <?php echo htmlspecialchars($bottomCar['color']); ?>
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

        </div>

    </div>

    <?php endif; ?>

</div>

<?php endfor; ?>

<?php include '../includes/footer.php'; ?>

</body>
</html>