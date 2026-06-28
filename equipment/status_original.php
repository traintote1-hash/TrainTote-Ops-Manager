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

$cars = [];

if ($railroad) {

$stmt = $pdo->prepare("

SELECT

e.id,

CONCAT(e.reporting_marks,' ',e.road_number) AS car,

e.reporting_marks,
e.road_number,

e.current_industry_id,

e.current_track,

e.load_status,

i.industry_name,

w.commodity,
w.current_cycle,
w.cycle_count,
w.destination_industry_id,

d.industry_name AS destination

FROM equipment e

LEFT JOIN industries i
ON e.current_industry_id=i.id

LEFT JOIN waybills w
ON e.id=w.equipment_id
AND w.active=1

LEFT JOIN industries d
ON w.destination_industry_id=d.id

WHERE e.railroad_id=:railroad_id

ORDER BY
COALESCE(i.industry_name,'ZZZZ'),
e.current_track,
e.reporting_marks,
e.road_number

");

$stmt->execute([
'railroad_id'=>$railroad['id']
]);

$cars = $stmt->fetchAll(PDO::FETCH_ASSOC);

}

$totalCars = count($cars);

$activeWaybills = 0;
$loadedCars = 0;
$locatedCars = 0;

foreach($cars as $car){

if(!empty($car['commodity'])){
$activeWaybills++;
}

if(strtolower($car['load_status'])=='loaded'){
$loadedCars++;
}

if(!empty($car['industry_name'])){
$locatedCars++;
}

}

?>

<?php include '../includes/header.php'; ?>

<title>Car Status</title>

<style>

#carTable th{
cursor:pointer;
user-select:none;
}

#carTable th:hover{
background:#2f353b;
}

.sort-arrow{
    color:#aaa;
    font-size:.8em;
    margin-left:3px;
}

</style>

</head>

<body>

<?php include '../includes/navbar.php'; ?>

<div class="container mt-5">

<h1>Car Status</h1>

<p class="text-muted">
Current location and waybill status for all equipment.
</p>
<div class="row mb-4">

<div class="col-md-3">
<div class="card text-center">
<div class="card-body">
<h2><?=$totalCars?></h2>
Total Cars
</div>
</div>
</div>

<div class="col-md-3">
<div class="card text-center">
<div class="card-body">
<h2><?=$activeWaybills?></h2>
Active Waybills
</div>
</div>
</div>

<div class="col-md-3">
<div class="card text-center">
<div class="card-body">
<h2><?=$loadedCars?></h2>
Loaded Cars
</div>
</div>
</div>

<div class="col-md-3">
<div class="card text-center">
<div class="card-body">
<h2><?=$locatedCars?></h2>
Located Cars
</div>
</div>
</div>

</div>


<div class="card mb-4">

<div class="card-body">

<input
type="text"
id="searchBox"
class="form-control"
placeholder="Search car, location, commodity, destination...">

</div>

</div>
<div class="table-responsive">

<table
class="table table-striped table-hover align-middle"
id="carTable">

<thead class="table-dark">

<tr>

<th onclick="sortTable(0)">Car &#8597;</th>
<th onclick="sortTable(1)">Location &#8597;</th>
<th onclick="sortTable(2)">Track &#8597;</th>
<th onclick="sortTable(3)">Status &#8597;</th>
<th onclick="sortTable(4)">Commodity &#8597;</th>
<th onclick="sortTable(5)">Destination &#8597;</th>
<th onclick="sortTable(6)">Cycle &#8597;</th>
<th onclick="sortTable(7)">Move? &#8597;</th></th>

</tr>

</thead>
<tbody>
<?php foreach($cars as $car): ?>

<tr
class="clickable-row"
data-href="view.php?id=<?=$car['id']?>"
style="cursor:pointer;">

<td>
<strong>
<?=htmlspecialchars($car['car'])?>
</strong>
</td>

<td>
<?=htmlspecialchars($car['industry_name'] ?: '-')?>
</td>

<td>
<?=htmlspecialchars($car['current_track'] ?: '-')?>
</td>

<td>

<?php if(strtolower($car['load_status'])=='loaded'): ?>

<span class="badge bg-success">
Loaded
</span>

<?php else: ?>

<span class="badge bg-secondary">
Empty
</span>

<?php endif; ?>

</td>

<td>

<?php if(!empty($car['commodity'])): ?>

<?=htmlspecialchars($car['commodity'])?>

<?php else: ?>

<span class="badge bg-warning text-dark">
No Waybill
</span>

<?php endif; ?>

</td>

<td>

<?=htmlspecialchars($car['destination'] ?: '-')?>

</td>

<td>

<?php

if(!empty($car['current_cycle'])){

echo $car['current_cycle'].' / '.$car['cycle_count'];

}else{

echo '-';

}

?>

</td>

<td>

<?php

if(
!empty($car['destination_industry_id'])
&&
$car['destination_industry_id'] != $car['current_industry_id']
){

?>

<span class="badge bg-primary">
Move Needed
</span>

<?php

}else{

echo '-';

}

?>

</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

</div>
<script>

document.querySelectorAll('.clickable-row').forEach(function(row){

row.addEventListener('click',function(){

window.location=this.dataset.href;

});

});


document.getElementById('searchBox').addEventListener('keyup',function(){

let filter=this.value.toUpperCase();

let rows=document.querySelectorAll("#carTable tbody tr");

rows.forEach(function(row){

let text=row.innerText.toUpperCase();

row.style.display=text.indexOf(filter)>-1 ? "" : "none";

});

});


let directions=[];

function sortTable(col){

let table=document.getElementById("carTable");

let tbody=table.tBodies[0];

let rows=Array.from(tbody.rows);

directions[col]=!directions[col];

rows.sort(function(a,b){

let x=a.cells[col].innerText.trim();

let y=b.cells[col].innerText.trim();

if(directions[col]){

return x.localeCompare(y,undefined,{numeric:true});

}else{

return y.localeCompare(x,undefined,{numeric:true});

}

});

rows.forEach(row=>tbody.appendChild(row));

}

</script>
</div>

<?php include '../includes/footer.php'; ?>