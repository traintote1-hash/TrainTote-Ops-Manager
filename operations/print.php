<?php

session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$sessionWaybills =
    $_SESSION['generated_session'] ?? [];

$difficulty =
    $_SESSION['generated_difficulty'] ?? '';

$carCount =
    $_SESSION['generated_car_count'] ?? 0;

$operatingBaseName =
    $_SESSION['generated_operating_base_name'] ?? '';

$locomotiveLabel =
    $_SESSION['generated_locomotive_label'] ?? '';

function getPrintedMoveActionLabel(array $move): string
{
    $moveType = strtoupper(trim((string)($move['move_type'] ?? '')));

    if ($moveType === 'PULL') {
        return 'PULL';
    }

    if ($moveType === 'SETOUT') {
        return 'SPOT';
    }

    return $moveType ?: 'WORK';
}

function getPrintedMoveWorkLocation(array $move): string
{
    $moveType = strtoupper(trim((string)($move['move_type'] ?? '')));

    if ($moveType === 'PULL') {
        $location = $move['origin_industry_name'] ?? ($move['origin_name'] ?? '');
    }
    else {
        $location = $move['destination_industry_name'] ?? ($move['destination_name'] ?? '');
    }

    $location = trim((string)$location);

    return $location !== '' ? $location : 'Unassigned Work Location';
}

function groupPrintedMovesByLocation(array $moves): array
{
    $groups = [];

    foreach ($moves as $move) {
        $location = getPrintedMoveWorkLocation($move);
        $key = strtolower($location);

        if (!isset($groups[$key])) {
            $groups[$key] = [
                'location' => $location,
                'moves' => []
            ];
        }

        $groups[$key]['moves'][] = $move;
    }

    return array_values($groups);
}

$setoutMoveCount = count(array_filter(
    $sessionWaybills,
    fn($move) => ($move['move_type'] ?? '') === 'SETOUT'
));

$pullMoveCount = count(array_filter(
    $sessionWaybills,
    fn($move) => ($move['move_type'] ?? '') === 'PULL'
));

$workLocationGroups = groupPrintedMovesByLocation($sessionWaybills);

?>

<?php include '../includes/header.php'; ?>

<title>Print Switch List</title>

<style>

body {
    padding: 20px;
    color: #111;
}

.work-order-header {
    margin-bottom: 18px;
}

.work-order-meta {
    display: grid;
    grid-template-columns: repeat(4, minmax(130px, 1fr));
    gap: 8px 16px;
    margin: 16px 0;
}

.work-order-meta div {
    border: 1px solid #bbb;
    padding: 6px 8px;
}

.work-order-meta span {
    display: block;
    font-size: 11px;
    text-transform: uppercase;
    color: #555;
}

.location-work {
    margin: 18px 0;
    page-break-inside: avoid;
}

.location-work h4 {
    border-bottom: 2px solid #111;
    padding-bottom: 4px;
    margin-bottom: 8px;
}

table.work-order-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 12px;
}

.work-order-table th,
.work-order-table td {
    border: 1px solid #777;
    padding: 4px 5px;
    vertical-align: top;
}

.work-order-table th {
    background: #eee;
}

@media print {

    .no-print {
        display: none;
    }

    body {
        padding: 0;
    }

    .container {
        max-width: none;
        width: 100%;
    }

    .location-work {
        break-inside: avoid;
    }
}

</style>

</head>

<body>

<div class="container mt-4">

<div class="no-print mb-4">

<button
onclick="window.print();"
class="btn btn-primary me-2">

Print

</button>

<button
onclick="window.close();"
class="btn btn-secondary">

Close

</button>

</div>

<div class="work-order-header">

<h1>TrainTote Ops Manager</h1>

<h3>Local Switcher Work Order</h3>

<div class="work-order-meta">

<div><span>Operating Base</span><strong><?php echo htmlspecialchars($operatingBaseName ?: '-'); ?></strong></div>
<div><span>Assigned Locomotive</span><strong><?php echo htmlspecialchars($locomotiveLabel ?: '-'); ?></strong></div>
<div><span>Difficulty</span><strong><?php echo htmlspecialchars(ucfirst($difficulty)); ?></strong></div>
<div><span>Cars Requested</span><strong><?php echo (int)$carCount; ?></strong></div>
<div><span>Setouts</span><strong><?php echo (int)$setoutMoveCount; ?></strong></div>
<div><span>Pulls</span><strong><?php echo (int)$pullMoveCount; ?></strong></div>

</div>

</div>

<hr>

<?php if (count($sessionWaybills) == 0): ?>

<p>No generated session found.</p>

<?php else: ?>

<?php foreach ($workLocationGroups as $group): ?>

<section class="location-work">

<h4><?php echo htmlspecialchars($group['location']); ?></h4>

<table class="work-order-table">
<thead>
<tr>
<th>Action</th>
<th>Car</th>
<th>Type</th>
<th>Load</th>
<th>Service</th>
<th>Track</th>
<th>From</th>
<th>To</th>
</tr>
</thead>
<tbody>
<?php foreach ($group['moves'] as $waybill): ?>
<tr>
<td><strong><?php echo htmlspecialchars(getPrintedMoveActionLabel($waybill)); ?></strong></td>
<td><?php echo htmlspecialchars(trim(($waybill['reporting_marks'] ?? '') . ' ' . ($waybill['road_number'] ?? '')) ?: '-'); ?></td>
<td><?php echo htmlspecialchars($waybill['equipment_type'] ?: '-'); ?></td>
<td><?php echo htmlspecialchars($waybill['load_status'] ?: '-'); ?></td>
<td><?php echo htmlspecialchars($waybill['operations_service'] ?: '-'); ?></td>
<td><?php echo htmlspecialchars(($waybill['destination_track'] ?: ($waybill['current_track'] ?? '')) ?: '-'); ?></td>
<td><?php echo htmlspecialchars($waybill['origin_industry_name'] ?? ($waybill['origin_name'] ?? '-')); ?></td>
<td><?php echo htmlspecialchars($waybill['destination_industry_name'] ?? ($waybill['destination_name'] ?? '-')); ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>

</section>

<?php endforeach; ?>

<?php endif; ?>

</div>

<?php include '../includes/footer.php'; ?>