<?php
session_start();
require_once '../config/database.php';
require_once 'lib.php';
require_once 'repair_service.php';
if (!isset($_SESSION['user_id'])) { header('Location: ../login.php'); exit; }

$railroad = ttOperationsRailroad($pdo, (int)$_SESSION['user_id']);
$railroadId = (int)$railroad['id'];
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        ttOperationsRequireCsrf();
        ttUpdateRepair($pdo, $id, $railroadId, (int)$_SESSION['user_id'], (string)($_POST['status'] ?? ''), (string)($_POST['repair_note'] ?? ''));
        $message = 'Repair record updated.';
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$stmt = $pdo->prepare("SELECT r.*,e.reporting_marks,e.road_number,e.equipment_type,e.photo_filename,e.current_track,e.active,
    i.industry_name,m.exception_notes,sl.id switch_list_id,sl.switch_list_number,a.assignment_number,s.id session_id,s.session_number,s.operating_date
    FROM operation_repairs r
    JOIN equipment e ON e.id=r.equipment_id AND e.railroad_id=r.railroad_id
    LEFT JOIN industries i ON i.id=e.current_industry_id AND i.railroad_id=e.railroad_id
    LEFT JOIN operation_switch_list_moves m ON m.id=r.source_move_id AND m.railroad_id=r.railroad_id
    LEFT JOIN operation_switch_lists sl ON sl.id=m.switch_list_id AND sl.railroad_id=m.railroad_id
    LEFT JOIN operation_assignments a ON a.id=sl.assignment_id AND a.railroad_id=sl.railroad_id
    LEFT JOIN operating_sessions s ON s.id=sl.session_id AND s.railroad_id=sl.railroad_id
    WHERE r.id=? AND r.railroad_id=?");
$stmt->execute([$id, $railroadId]);
$repair = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$repair) { http_response_code(404); die('Repair record not found.'); }

$historyStmt = $pdo->prepare('SELECT * FROM operation_repair_history WHERE repair_id=? AND railroad_id=? ORDER BY created_at DESC,id DESC');
$historyStmt->execute([$id, $railroadId]);
$history = $historyStmt->fetchAll(PDO::FETCH_ASSOC);
$identity = trim($repair['reporting_marks'].' '.$repair['road_number']);
$isClosed = $repair['status'] === 'closed';
?>
<?php include '../includes/header.php'; ?><title><?=ttHtml($identity)?> Repair</title><link rel="stylesheet" href="../assets/css/operations.css"></head><body><?php include '../includes/navbar.php'; ?>
<div class="tt-operations-shell"><?php include '../assets/components/sidebar.php'; ?><section class="tt-ops-page">
<p><a href="repairs.php<?=$isClosed?'?view=closed':''?>">&larr; Back to Repair Queue</a></p>
<div class="d-flex flex-wrap justify-content-between gap-3 mb-3"><div><p class="tt-eyebrow">Repair Record</p><h1><?=ttHtml($identity)?></h1><p class="lead mb-0"><?=ttHtml($repair['equipment_type']?:'Equipment type not recorded')?></p></div><span class="badge tt-repair-status-<?=ttHtml($repair['status'])?> align-self-start"><?=ttHtml(ttRepairStatusLabel($repair['status']))?></span></div>
<?php if ($message): ?><div class="alert alert-success"><?=ttHtml($message)?></div><?php endif; ?><?php if ($error): ?><div class="alert alert-danger"><?=ttHtml($error)?></div><?php endif; ?>
<div class="tt-repair-detail-grid">
<section class="card p-3"><h2 class="h4">Bad Order report</h2><div class="d-flex flex-wrap gap-3"><?php if ($url=ttPhotoUrl($repair['photo_filename'])): ?><img class="tt-repair-photo" src="<?=ttHtml($url)?>" alt="<?=ttHtml($identity)?>"><?php else: ?><span class="tt-no-photo tt-repair-photo">No Photo</span><?php endif; ?><dl class="tt-compact-dl flex-grow-1 mb-0"><dt>Reported</dt><dd><?=ttHtml($repair['reported_at']?:'Not recorded')?></dd><dt>Reason</dt><dd>Bad Order</dd><dt>Original notes</dt><dd><?=ttHtml($repair['original_notes']?:'Not recorded')?></dd><dt>Location</dt><dd><?=ttHtml(($repair['industry_name']?:'Not recorded').($repair['current_track']!==''?' / '.$repair['current_track']:''))?></dd><dt>Session</dt><dd><?=ttHtml(($repair['session_number']?:'Not recorded').(!empty($repair['operating_date'])?' / '.$repair['operating_date']:''))?></dd><dt>Work order</dt><dd><?php if ($repair['switch_list_id']): ?><a href="work_order.php?id=<?=(int)$repair['switch_list_id']?>"><?=ttHtml(trim(($repair['assignment_number']?:'').' '.($repair['switch_list_number']?:'')))?></a><?php else: ?>Not recorded<?php endif; ?></dd></dl></div><?php if (!(int)$repair['service_state_applied']): ?><div class="alert alert-info mt-3 mb-0">This record was imported from historical Operations data. Its equipment service state was not changed automatically.</div><?php endif; ?></section>
<?php if (!$isClosed): ?><section class="card p-3"><h2 class="h4">Update repair</h2><form method="post"><input type="hidden" name="csrf_token" value="<?=ttHtml(ttOperationsCsrfToken())?>"><input type="hidden" name="id" value="<?=$id?>"><label class="form-label" for="repairStatus">Status</label><select class="form-select mb-3" id="repairStatus" name="status"><?php foreach (ttRepairStatuses() as $value=>$label): ?><option value="<?=ttHtml($value)?>" <?=$repair['status']===$value?'selected':''?>><?=ttHtml($label)?></option><?php endforeach; ?></select><label class="form-label" for="repairNote">Repair note</label><textarea class="form-control mb-3" id="repairNote" name="repair_note" rows="5" maxlength="5000" placeholder="Describe inspection, work performed, or next steps"></textarea><p class="small text-muted">Closing returns equipment to its pre-repair active state. Its industry and track will not change.</p><button class="btn btn-primary">Save Update</button></form></section><?php endif; ?>
</div>
<section class="card p-3 mt-3"><h2 class="h4">Repair history</h2><?php if (!$history): ?><p class="text-muted mb-0">No history recorded.</p><?php else: ?><ol class="tt-repair-history mb-0"><?php foreach ($history as $event): ?><li><div><strong><?=ttHtml($event['created_at'])?></strong> / <?=ttHtml($event['event_type']==='incident'?'Additional Bad Order reported':ttRepairStatusLabel($event['new_status']))?></div><?php if ($event['note']): ?><p class="mb-0"><?=nl2br(ttHtml($event['note']))?></p><?php endif; ?></li><?php endforeach; ?></ol><?php endif; ?></section>
</section></div><?php include '../includes/footer.php'; ?>
