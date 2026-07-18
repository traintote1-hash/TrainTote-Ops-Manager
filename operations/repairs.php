<?php
session_start();
require_once '../config/database.php';
require_once 'lib.php';
require_once 'repair_service.php';
if (!isset($_SESSION['user_id'])) { header('Location: ../login.php'); exit; }

$railroad = ttOperationsRailroad($pdo, (int)$_SESSION['user_id']);
$railroadId = (int)$railroad['id'];
$view = ($_GET['view'] ?? '') === 'closed' ? 'closed' : 'open';
$statusWhere = $view === 'closed' ? "r.status='closed'" : "r.status<>'closed'";
$orderBy = $view === 'closed'
    ? 'r.closed_at DESC,r.id DESC'
    : "FIELD(r.status,'awaiting_repair','in_repair','ready_for_service'),r.reported_at";
$stmt = $pdo->prepare("SELECT r.*,e.reporting_marks,e.road_number,e.equipment_type,e.current_track,
    (SELECT h.note FROM operation_repair_history h WHERE h.repair_id=r.id AND h.railroad_id=r.railroad_id AND h.note IS NOT NULL AND TRIM(h.note)<>'' ORDER BY h.created_at DESC,h.id DESC LIMIT 1) repair_notes_summary,
    i.industry_name,m.exception_notes,sl.switch_list_number,a.assignment_number,s.session_number
    FROM operation_repairs r
    JOIN equipment e ON e.id=r.equipment_id AND e.railroad_id=r.railroad_id
    LEFT JOIN industries i ON i.id=e.current_industry_id AND i.railroad_id=e.railroad_id
    LEFT JOIN operation_switch_list_moves m ON m.id=r.source_move_id AND m.railroad_id=r.railroad_id
    LEFT JOIN operation_switch_lists sl ON sl.id=m.switch_list_id AND sl.railroad_id=m.railroad_id
    LEFT JOIN operation_assignments a ON a.id=sl.assignment_id AND a.railroad_id=sl.railroad_id
    LEFT JOIN operating_sessions s ON s.id=sl.session_id AND s.railroad_id=sl.railroad_id
    WHERE r.railroad_id=? AND $statusWhere
    ORDER BY $orderBy");
$stmt->execute([$railroadId]);
$repairs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<?php include '../includes/header.php'; ?><title>Repair Queue</title><link rel="stylesheet" href="../assets/css/operations.css"></head><body><?php include '../includes/navbar.php'; ?>
<div class="tt-operations-shell"><?php include '../assets/components/sidebar.php'; ?><section class="tt-ops-page">
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3"><div><p class="tt-eyebrow">Operations</p><h1>Repair Queue</h1><p class="text-muted mb-0">Track Bad Order equipment from report through return to service.</p></div><div class="btn-group" role="group" aria-label="Repair queue view"><a class="btn <?=$view==='open'?'btn-primary':'btn-outline-primary'?>" href="repairs.php">Open Repairs</a><a class="btn <?=$view==='closed'?'btn-primary':'btn-outline-primary'?>" href="repairs.php?view=closed">Closed History</a></div></div>
<?php if (!$repairs): ?>
<div class="tt-repair-empty"><h2 class="h4"><?=$view==='closed'?'No closed repairs yet':'No equipment is awaiting repair'?></h2><p class="text-muted mb-0"><?=$view==='closed'?'Completed repair records will remain available here.':'Bad Order exceptions recorded during work-order completion will appear here.'?></p></div>
<?php else: ?>
<div class="card"><div class="table-responsive"><table class="table align-middle mb-0 tt-repair-table"><thead><tr><th>Equipment</th><th>Reported</th><th>Status</th><th>Bad-order details</th><th>Current location</th><th>Origin</th><th></th></tr></thead><tbody>
<?php foreach ($repairs as $repair): $identity=trim($repair['reporting_marks'].' '.$repair['road_number']); $origin=array_filter([$repair['session_number'],$repair['assignment_number'],$repair['switch_list_number']]); ?>
<tr><td><strong><?=ttHtml($identity)?></strong><br><small><?=ttHtml($repair['equipment_type']?:'Not recorded')?></small></td><td><?=ttHtml($repair['reported_at']?:'Not recorded')?></td><td><span class="badge tt-repair-status-<?=ttHtml($repair['status'])?>"><?=ttHtml(ttRepairStatusLabel($repair['status']))?></span></td><td><strong>Bad Order</strong><br><small><?=ttHtml($repair['repair_notes_summary']?:($repair['original_notes']?:'Not recorded'))?></small></td><td><?=ttHtml(($repair['industry_name']?:'Not recorded').($repair['current_track']!==''?' / '.$repair['current_track']:''))?></td><td><?=ttHtml($origin?implode(' / ',$origin):'Not recorded')?></td><td><a class="btn btn-sm btn-outline-primary" href="repair.php?id=<?=(int)$repair['id']?>"><?=$view==='closed'?'View':'View / Edit'?></a></td></tr>
<?php endforeach; ?>
</tbody></table></div></div>
<?php endif; ?>
</section></div><?php include '../includes/footer.php'; ?>
