<?php



session_start();



require_once '../config/database.php';



if (!isset($_SESSION['user_id'])) {

    header('Location: ../login.php');

    exit;

}



if (!isset($_GET['id'])) {

    die('Equipment ID missing.');

}



$id = (int)$_GET['id'];



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



$equipment = $stmt->fetch(PDO::FETCH_ASSOC);



if (!$equipment) {

    die('Equipment not found.');

}



?>



<?php include '../includes/header.php'; ?>



<title>Car Card V2</title>



<style>



body {

    background: #f5f5f5;

}



.card-template {



    width: 2.5in;

    height: 5in;



    margin: 20px auto;



    background: #fff;



    border: 2px solid #000;



    box-shadow: 0 0 10px rgba(0,0,0,.15);



    overflow: hidden;

}



.card-photo {



    height: .80in;



    border-bottom: 2px solid #000;



    background: #fafafa;



    overflow: hidden;

}



.card-photo img {



    width: 100%;

    height: 100%;



    object-fit: contain;

}



.no-photo {



    height: 100%;



    display: flex;



    align-items: center;

    justify-content: center;



    color: #777;

}



.card-info {



    height: 1.00in;



    padding: 8px;



    border-bottom: 2px solid #000;



    font-size: 11px;



    line-height: 1.2;

}



.info-row {



    display: flex;



    margin-bottom: 4px;

}



.info-label {



    width: 75px


    font-weight: bold;



    flex-shrink: 0;

}



.info-value {



    flex-grow: 1;



    overflow: hidden;



    white-space: nowrap;



    text-overflow: ellipsis;

}



.waybill-window {



    height: 2.20in;



    background:

    repeating-linear-gradient(

        45deg,

        #fafafa,

        #fafafa 10px,

        #f2f2f2 10px,

        #f2f2f2 20px

    );

}



.fold-line {



    height: 22px;



    border-top: 3px solid #d33;



    display: flex;



    align-items: center;



    justify-content: center;



    font-size: 10px;



    font-weight: bold;



    color: #555;



    background: #fff;

}



.glue-flap {



    height: 1.00in;



    position: relative;



    background: #fff;

}



.tape-left {



    position: absolute;



    left: 8px;



    top: 50%;



    transform: translateY(-50%);



    writing-mode: vertical-rl;



    text-orientation: mixed;



    font-size: 12px;



    font-weight: bold;



    color: #666;

}



.tape-right {



    position: absolute;



    right: 8px;



    top: 50%;



    transform: translateY(-50%);



    writing-mode: vertical-rl;



    text-orientation: mixed;



    font-size: 12px;



    font-weight: bold;



    color: #666;

}



@media print {



    .no-print {

        display: none;

    }



    body {

        background: #fff;

    }



    .card-template {



        margin: 0;



        box-shadow: none;

    }

}



</style>



</head>



<body>



<?php include '../includes/navbar.php'; ?>



<div class="container mt-4">



<div class="no-print mb-3">



<h1>Car Card V2 Preview</h1>



<a

href="view.php?id=<?php echo $equipment['id']; ?>"

class="btn btn-secondary me-2">



Back to Equipment



</a>



<button

onclick="window.print();"

class="btn btn-primary">



Print Card



</button>



</div>



<div class="card-template">



<div class="card-photo">



<?php if (!empty($equipment['photo_filename'])): ?>



<img

src="../uploads/<?php echo htmlspecialchars($equipment['photo_filename']); ?>?v=<?php echo time(); ?>"

alt="Equipment Photo">



<?php else: ?>



<div class="no-photo">



NO PHOTO AVAILABLE



</div>



<?php endif; ?>



</div>



<div class="card-info">



<div class="info-row">

    <div class="info-label">Road Name:</div>

    <div class="info-value"> 

        <?php echo htmlspecialchars($equipment['reporting_marks']); ?>

    </div>

</div>



<div class="info-row">

    <div class="info-label">Car Number:</div>

    <div class="info-value">

        <?php echo htmlspecialchars($equipment['road_number']); ?>

    </div>

</div>



<div class="info-row">

    <div class="info-label">Type:</div>

    <div class="info-value">

        <?php echo htmlspecialchars($equipment['equipment_type']); ?>

    </div>

</div>



<div class="info-row">

    <div class="info-label">Length:</div>

    <div class="info-value">

        <?php

        if (!empty($equipment['length_ft'])) {

            echo htmlspecialchars($equipment['length_ft']) . "'";

        }

        ?>

    </div>

</div>



<div class="info-row">

    <div class="info-label">Color:</div>

    <div class="info-value">

        <?php echo htmlspecialchars($equipment['color']); ?>

    </div>

</div>







</div>



<div class="waybill-window">



</div>



<div class="fold-line">



FOLD HERE



</div>



<div class="glue-flap">



<div class="tape-left">



TAPE



</div>



<div class="tape-right">



TAPE



</div>



</div>



</div>



</div>



<?php include '../includes/footer.php'; ?>