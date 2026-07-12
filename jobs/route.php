<?php
session_start();
require_once '../config/database.php';
require_once '../operations/lib.php';

if (!isset($_SESSION['user_id'])) { header('Location: ../login.php'); exit; }
$railroad = ttOperationsRailroad($pdo, (int)$_SESSION['user_id']);
$railroadId = (int)$railroad['id'];
$jobId = (int)($_GET['id'] ?? $_POST['job_id'] ?? 0);
$error = '';
$message = '';

$jobStmt = $pdo->prepare("SELECT j.*,COALESCE(jop.work_scope,'entire_railroad') work_scope FROM jobs j LEFT JOIN job_operation_profiles jop ON jop.job_id=j.id AND jop.railroad_id=j.railroad_id WHERE j.id=? AND j.railroad_id=?");
$jobStmt->execute([$jobId, $railroadId]);
$job = $jobStmt->fetch(PDO::FETCH_ASSOC);
if (!$job) { http_response_code(404); die('Job Title not found.'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        ttOperationsRequireCsrf();
        $action = (string)($_POST['action'] ?? '');
        $pdo->beginTransaction();
        if ($action === 'add') {
            $industryId = (int)($_POST['industry_id'] ?? 0);
            $check = $pdo->prepare('SELECT id FROM industries WHERE id=? AND railroad_id=? AND active=1');
            $check->execute([$industryId, $railroadId]);
            if (!$check->fetchColumn()) throw new RuntimeException('Select a valid active route stop.');
            $seq = $pdo->prepare('SELECT COALESCE(MAX(sequence_number),0)+1 FROM job_route_stops WHERE job_id=? AND railroad_id=? FOR UPDATE');
            $seq->execute([$jobId, $railroadId]);
            $pdo->prepare('INSERT INTO job_route_stops (railroad_id,job_id,industry_id,sequence_number) VALUES (?,?,?,?)')->execute([$railroadId, $jobId, $industryId, (int)$seq->fetchColumn()]);
            $pdo->prepare("INSERT INTO job_operation_profiles (job_id,railroad_id,work_scope) VALUES (?,?,'selected_route') ON DUPLICATE KEY UPDATE work_scope='selected_route'")->execute([$jobId, $railroadId]);
            $message = 'Route stop added.';
        } elseif ($action === 'remove') {
            $stopId = (int)($_POST['stop_id'] ?? 0);
            $pdo->prepare('DELETE FROM job_route_stops WHERE id=? AND job_id=? AND railroad_id=?')->execute([$stopId, $jobId, $railroadId]);
            $rows = $pdo->prepare('SELECT id FROM job_route_stops WHERE job_id=? AND railroad_id=? ORDER BY sequence_number,id');
            $rows->execute([$jobId, $railroadId]);
            $renumber = $pdo->prepare('UPDATE job_route_stops SET sequence_number=? WHERE id=?');
            foreach ($rows->fetchAll(PDO::FETCH_COLUMN) as $index => $id) $renumber->execute([$index + 1, (int)$id]);
            $message = 'Route stop removed.';
        } elseif (in_array($action, ['up','down'], true)) {
            $stopId = (int)($_POST['stop_id'] ?? 0);
            $rows = $pdo->prepare('SELECT id,sequence_number FROM job_route_stops WHERE job_id=? AND railroad_id=? ORDER BY sequence_number FOR UPDATE');
            $rows->execute([$jobId, $railroadId]);
            $stops = $rows->fetchAll(PDO::FETCH_ASSOC);
            $index = null;
            foreach ($stops as $i => $stop) if ((int)$stop['id'] === $stopId) $index = $i;
            $other = $action === 'up' ? $index - 1 : $index + 1;
            if ($index !== null && isset($stops[$other])) {
                $pdo->prepare('UPDATE job_route_stops SET sequence_number=0 WHERE id=?')->execute([$stopId]);
                $pdo->prepare('UPDATE job_route_stops SET sequence_number=? WHERE id=?')->execute([(int)$stops[$index]['sequence_number'], (int)$stops[$other]['id']]);
                $pdo->prepare('UPDATE job_route_stops SET sequence_number=? WHERE id=?')->execute([(int)$stops[$other]['sequence_number'], $stopId]);
            }
        } elseif ($action === 'rules') {
            $stopId = (int)($_POST['stop_id'] ?? 0);
            $statuses = ['Any','Loaded','Empty'];
            $pullModes = ['operating_base','yard','staging_interchange','selected_location','next_compatible'];
            $sourceModes = ['operating_base','starting_cars','prepared_cut','staged_group','selected_location'];
            $outbound = (string)($_POST['outbound_load_status'] ?? 'Any');
            $inbound = (string)($_POST['inbound_load_status'] ?? 'Any');
            $exchangeEnabled = ($_POST['exchange_enabled'] ?? '') === '1' ? 1 : 0;
            $pullMode = (string)($_POST['pull_destination_mode'] ?? 'yard');
            $sourceMode = (string)($_POST['replacement_source_mode'] ?? 'starting_cars');
            if (!in_array($outbound, $statuses, true) || !in_array($inbound, $statuses, true) || !in_array($pullMode, $pullModes, true) || !in_array($sourceMode, $sourceModes, true)) throw new RuntimeException('Invalid exchange rule.');
            $pullId = (int)($_POST['pull_destination_industry_id'] ?? 0);
            $sourceId = (int)($_POST['replacement_source_industry_id'] ?? 0);
            foreach ([$pullId,$sourceId] as $locationId) if ($locationId > 0) { $check=$pdo->prepare('SELECT id FROM industries WHERE id=? AND railroad_id=?');$check->execute([$locationId,$railroadId]);if(!$check->fetchColumn())throw new RuntimeException('Exchange locations must belong to this railroad.'); }
            $stmt = $pdo->prepare('UPDATE job_route_stops SET exchange_enabled=?,outbound_load_status=?,inbound_load_status=?,pull_destination_mode=?,pull_destination_industry_id=?,replacement_source_mode=?,replacement_source_industry_id=? WHERE id=? AND job_id=? AND railroad_id=?');
            $stmt->execute([$exchangeEnabled,$outbound,$inbound,$pullMode,$pullId?:null,$sourceMode,$sourceId?:null,$stopId,$jobId,$railroadId]);
            $message = 'Exchange rules saved.';
        } else throw new RuntimeException('Invalid route action.');
        $pdo->commit();
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); $error = $e->getMessage(); }
}

$industryStmt = $pdo->prepare('SELECT id,industry_name,industry_type FROM industries WHERE railroad_id=? AND active=1 ORDER BY industry_name');
$industryStmt->execute([$railroadId]);
$industries = $industryStmt->fetchAll(PDO::FETCH_ASSOC);
$stopStmt = $pdo->prepare('SELECT jrs.*,i.industry_name,i.industry_type FROM job_route_stops jrs JOIN industries i ON i.id=jrs.industry_id AND i.railroad_id=jrs.railroad_id WHERE jrs.job_id=? AND jrs.railroad_id=? ORDER BY jrs.sequence_number');
$stopStmt->execute([$jobId,$railroadId]);
$stops = $stopStmt->fetchAll(PDO::FETCH_ASSOC);
$pullLabels=['operating_base'=>'Operating base','yard'=>'Yard','staging_interchange'=>'Staging or interchange','selected_location'=>'Another selected location','next_compatible'=>'Next compatible industry on route'];
$sourceLabels=['operating_base'=>'Operating base','starting_cars'=>'Starting cars','prepared_cut'=>'Prepared cut','staged_group'=>'Manifest or staged group','selected_location'=>'Another selected location'];
?>
<?php include '../includes/header.php'; ?><title>Edit Route — <?= ttHtml($job['job_name']) ?></title></head><body><?php include '../includes/navbar.php'; ?>
<div class="tt-operations-shell"><?php include '../assets/components/sidebar.php'; ?><section class="tt-ops-page">
<p class="tt-eyebrow">Job Title Route / Territory</p><h1><?= ttHtml($job['job_name']) ?></h1><p class="text-muted">Stops are visited in numbered order. Pull and replacement rules are evaluated at each stop without changing legacy Job associations.</p>
<?php if($message):?><div class="alert alert-success"><?=ttHtml($message)?></div><?php endif;?><?php if($error):?><div class="alert alert-danger"><?=ttHtml($error)?></div><?php endif;?>
<div class="card mb-4"><div class="card-body"><form method="post" class="row g-2 align-items-end"><input type="hidden" name="csrf_token" value="<?=ttHtml(ttOperationsCsrfToken())?>"><input type="hidden" name="job_id" value="<?=$jobId?>"><input type="hidden" name="action" value="add"><div class="col-md-8"><label class="form-label">Add Stop</label><select class="form-select" name="industry_id" required><option value="">Choose active location…</option><?php foreach($industries as $industry):?><option value="<?=(int)$industry['id']?>"><?=ttHtml($industry['industry_name'].' · '.$industry['industry_type'])?></option><?php endforeach;?></select></div><div class="col-md-4"><button class="btn btn-primary">Add Stop</button> <a class="btn btn-secondary" href="list.php">Back to Job Titles</a></div></form></div></div>
<?php foreach($stops as $index=>$stop):?><article class="card mb-3"><div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2"><h2 class="h5 mb-0"><span class="badge bg-primary me-2"><?=$index+1?></span><?=ttHtml($stop['industry_name'])?></h2><div class="d-flex gap-1"><form method="post"><input type="hidden" name="csrf_token" value="<?=ttHtml(ttOperationsCsrfToken())?>"><input type="hidden" name="job_id" value="<?=$jobId?>"><input type="hidden" name="stop_id" value="<?=(int)$stop['id']?>"><button class="btn btn-sm btn-outline-secondary" name="action" value="up" <?=$index===0?'disabled':''?>>Move Up</button> <button class="btn btn-sm btn-outline-secondary" name="action" value="down" <?=$index===count($stops)-1?'disabled':''?>>Move Down</button> <button class="btn btn-sm btn-outline-danger" name="action" value="remove" onclick="return confirm('Remove <?=ttHtml($stop['industry_name'])?> from this route?')">Remove Stop</button></form></div></div><div class="card-body"><form method="post" class="row g-3"><input type="hidden" name="csrf_token" value="<?=ttHtml(ttOperationsCsrfToken())?>"><input type="hidden" name="job_id" value="<?=$jobId?>"><input type="hidden" name="stop_id" value="<?=(int)$stop['id']?>"><input type="hidden" name="action" value="rules"><div class="col-12"><label class="form-check"><input class="form-check-input exchange-toggle" type="checkbox" name="exchange_enabled" value="1" <?=(int)$stop['exchange_enabled']===1?'checked':''?>><span class="form-check-label"><strong>Use paired car exchange at this stop</strong></span></label><div class="form-text">Off by default. Ordinary route-scoped Pull-only and Spot-only work remains available.</div></div><div class="col-md-3 exchange-rule" style="<?=(int)$stop['exchange_enabled']===1?'':'opacity:.55'?>"><label class="form-label">Paired cars to Pull</label><select class="form-select" name="outbound_load_status"><?php foreach(['Any','Loaded','Empty'] as $v):?><option <?=$stop['outbound_load_status']===$v?'selected':''?>><?=$v?></option><?php endforeach;?></select></div><div class="col-md-3 exchange-rule" style="<?=(int)$stop['exchange_enabled']===1?'':'opacity:.55'?>"><label class="form-label">Paired cars to Spot</label><select class="form-select" name="inbound_load_status"><?php foreach(['Any','Loaded','Empty'] as $v):?><option <?=$stop['inbound_load_status']===$v?'selected':''?>><?=$v?></option><?php endforeach;?></select></div><div class="col-md-3"><label class="form-label">Pulled Cars Go To</label><select class="form-select" name="pull_destination_mode"><?php foreach($pullLabels as $v=>$label):?><option value="<?=$v?>" <?=$stop['pull_destination_mode']===$v?'selected':''?>><?=ttHtml($label)?></option><?php endforeach;?></select></div><div class="col-md-3"><label class="form-label">Specific Pull Destination</label><select class="form-select" name="pull_destination_industry_id"><option value="">As selected above</option><?php foreach($industries as $industry):?><option value="<?=(int)$industry['id']?>" <?=(int)$stop['pull_destination_industry_id']===(int)$industry['id']?'selected':''?>><?=ttHtml($industry['industry_name'])?></option><?php endforeach;?></select></div><div class="col-md-4"><label class="form-label">Replacement / Spot Source</label><select class="form-select" name="replacement_source_mode"><?php foreach($sourceLabels as $v=>$label):?><option value="<?=$v?>" <?=$stop['replacement_source_mode']===$v?'selected':''?>><?=ttHtml($label)?></option><?php endforeach;?></select></div><div class="col-md-4"><label class="form-label">Specific Source Location</label><select class="form-select" name="replacement_source_industry_id"><option value="">As selected above</option><?php foreach($industries as $industry):?><option value="<?=(int)$industry['id']?>" <?=(int)$stop['replacement_source_industry_id']===(int)$industry['id']?'selected':''?>><?=ttHtml($industry['industry_name'])?></option><?php endforeach;?></select></div><div class="col-md-4 d-flex align-items-end"><button class="btn btn-success">Save Stop Rules</button></div></form></div></article><?php endforeach;?>
<?php if(!$stops):?><div class="alert alert-info">No route stops configured. Until stops are added, this Job Title retains Entire Railroad behavior.</div><?php endif;?>
</section></div><?php include '../includes/footer.php'; ?>
