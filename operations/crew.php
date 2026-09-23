<?php
require_once __DIR__ . '/invite_bootstrap.php';
$error = '';
$member = null;
$assignments = [];
$lists = [];
$moves = [];
try {
    $member = ttSessionParticipant($pdo, (array)($_SESSION['ops_crew_access'] ?? []), true);
    if ($member['status'] === 'approved') {
        $stmt = $pdo->prepare('SELECT id,assignment_number,title_snapshot,crew_name,status
            FROM operation_assignments WHERE session_id=? AND railroad_id=?
            ORDER BY sequence_number');
        $stmt->execute([(int)$member['session_id'], (int)$member['railroad_id']]);
        $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt = $pdo->prepare("SELECT sl.id,sl.assignment_id,sl.switch_list_number,sl.status,a.title_snapshot
            FROM operation_switch_lists sl JOIN operation_assignments a
            ON a.id=sl.assignment_id AND a.session_id=sl.session_id AND a.railroad_id=sl.railroad_id
            WHERE sl.session_id=? AND sl.railroad_id=? AND sl.status IN ('approved','in_progress','needs_review','completed')
            ORDER BY sl.id DESC");
        $stmt->execute([(int)$member['session_id'], (int)$member['railroad_id']]);
        $lists = $stmt->fetchAll(PDO::FETCH_ASSOC);
        // Crew sees only the session's published work and car details.
        $stmt = $pdo->prepare("SELECT m.id,m.switch_list_id,m.sequence_number,m.action,m.instruction,
            m.reporting_marks_snapshot,m.road_number_snapshot,m.origin_name_snapshot,m.destination_name_snapshot,
            m.origin_track,m.destination_track,m.progress_complete,m.equipment_id
            FROM operation_switch_list_moves m JOIN operation_switch_lists sl
            ON sl.id=m.switch_list_id AND sl.railroad_id=m.railroad_id
            WHERE sl.session_id=? AND sl.railroad_id=? AND sl.status IN ('approved','in_progress','needs_review','completed')
            ORDER BY m.switch_list_id,m.sequence_number");
        $stmt->execute([(int)$member['session_id'], (int)$member['railroad_id']]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $move) $moves[(int)$move['switch_list_id']][] = $move;
    }
} catch (Throwable $e) { $error = ttInvitePageError($e); http_response_code(403); }
?>
<?php include '../includes/header.php'; ?><title>Crew Session - TrainTote</title></head><body>
<main class="container my-4" style="max-width:70rem">
<div class="d-flex justify-content-between gap-3"><h1 class="h3">TrainTote Crew Session</h1>
<form method="post" action="crew_logout.php"><input type="hidden" name="csrf_token" value="<?=ttHtml(ttOperationsCsrfToken())?>"><button class="btn btn-outline-secondary btn-sm">Leave session</button></form></div>
<?php if ($error): ?><div class="alert alert-danger" role="alert"><?=ttHtml($error)?></div><p>Ask the owner for a new invitation if your access has ended.</p>
<?php elseif ($member): ?>
<p><strong><?=ttHtml($member['railroad_name'])?></strong> · <?=ttHtml($member['session_number'].' '.($member['session_name'] ?? ''))?></p>
<p class="text-muted">Signed in as <?=ttHtml($member['display_name'])?>. Access lasts only for this operating session.</p>
<?php if ($member['status'] === 'pending'): ?><div class="alert alert-info">Waiting for the owner to approve your QR join request. Refresh this page after they approve you.</div>
<?php else: ?>
<?php if ($member['session_status'] !== 'in_progress'): ?><div class="alert alert-secondary">The session has not started yet. Published work will appear below.</div><?php endif; ?>
<h2 class="h4">Assignments</h2>
<?php foreach ($assignments as $assignment): ?><div class="card card-body mb-2"><strong><?=ttHtml($assignment['assignment_number'].' — '.$assignment['title_snapshot'])?></strong><span><?=ttHtml($assignment['status'])?></span></div><?php endforeach; ?>
<?php if (!$assignments): ?><p>No assignments are available yet.</p><?php endif; ?>
<h2 class="h4 mt-4">Published switch lists</h2>
<?php foreach ($lists as $list): ?><section class="card mb-3"><div class="card-header"><h3 class="h5 mb-0"><?=ttHtml($list['switch_list_number'].' — '.$list['title_snapshot'])?></h3></div><div class="card-body">
<?php foreach ($moves[(int)$list['id']] ?? [] as $move): ?><div class="border-bottom py-2 d-flex align-items-start gap-3">
<?php $canProgress = $member['session_status'] === 'in_progress'
    && (int)$member['assignment_id'] === (int)$list['assignment_id']
    && $member['assignment_id'] !== null && $move['equipment_id'] !== null
    && in_array($list['status'], ['approved','in_progress'], true); ?>
<?php if ($canProgress): ?><form method="post" action="crew_progress.php">
<input type="hidden" name="csrf_token" value="<?=ttHtml(ttOperationsCsrfToken())?>"><input type="hidden" name="move_id" value="<?=(int)$move['id']?>">
<input type="hidden" name="complete" value="<?=$move['progress_complete']?'0':'1'?>">
<button class="btn btn-sm <?=$move['progress_complete']?'btn-outline-secondary':'btn-outline-primary'?>"><?=$move['progress_complete']?'Mark unfinished':'Mark done'?></button></form>
<?php elseif ($move['equipment_id']): ?><span class="badge text-bg-light"><?=$move['progress_complete']?'Done':'Open'?></span><?php endif; ?>
<div><strong><?=ttHtml(trim(($move['reporting_marks_snapshot'] ?? '').' '.($move['road_number_snapshot'] ?? '')))?></strong>
<?=ttHtml($move['action'])?> · <?=ttHtml($move['instruction'])?><br>
<small><?=ttHtml(trim(($move['origin_name_snapshot'] ?? '').' / '.($move['origin_track'] ?? ''),' /'))?> → <?=ttHtml(trim(($move['destination_name_snapshot'] ?? '').' / '.($move['destination_track'] ?? ''),' /'))?></small></div></div>
<?php endforeach; ?></div></section><?php endforeach; ?>
<?php if (!$lists): ?><p>No approved switch lists are available yet.</p><?php endif; ?>
<?php endif; ?><?php endif; ?>
</main><?php include '../includes/footer.php'; ?>
