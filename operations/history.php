<?php
session_start();
require_once '../config/database.php';
require_once 'lib.php';
require_once 'history_service.php';
if (!isset($_SESSION['user_id'])) { header('Location: ../login.php'); exit; }
$railroad = ttOperationsRailroad($pdo, (int)$_SESSION['user_id']);
$sessions = ttLoadOperationsHistory($pdo, (int)$railroad['id']);
?>
<?php include '../includes/header.php'; ?><title>Operations Session History</title><link rel="stylesheet" href="../assets/css/operations.css"></head><body><?php include '../includes/navbar.php'; ?><div class="tt-operations-shell"><?php include '../assets/components/sidebar.php'; ?><section class="tt-ops-page">
<div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4"><div><p class="tt-eyebrow">Operations</p><h1>Session History</h1><p class="text-muted mb-0">Completed and closed operating sessions, preserved as read-only records.</p></div><a class="btn btn-outline-primary" href="sessions.php">Active Sessions</a></div>
<div class="card"><div class="table-responsive tt-mobile-cards"><table class="table table-hover align-middle mb-0 tt-history-table"><thead><tr><th>Session</th><th>Operating Date</th><th>Final Status</th><th>Crew / Operators</th><th>Work Orders</th><th>Completed Moves</th><th>Exceptions</th><th>Completion Time</th><th><span class="visually-hidden">Actions</span></th></tr></thead><tbody>
<?php foreach ($sessions as $session): ?><?php $closedAt=$session['status']==='completed'?$session['completed_at']:$session['cancelled_at']; ?><tr><td data-label="Session"><strong><?=ttHtml($session['session_number'])?></strong><?php if(trim((string)$session['session_name'])!==''):?><div class="small text-muted"><?=ttHtml($session['session_name'])?></div><?php endif;?></td><td data-label="Operating Date"><?=ttHtml(ttHistoryRecordedValue($session['operating_date']))?></td><td data-label="Final Status"><span class="badge tt-status-<?=ttHtml($session['status'])?>"><?=ttHtml(ttOperationsStatusLabel($session['status'],'session'))?></span></td><td data-label="Crew / Operators"><?=ttHtml(ttHistoryRecordedValue($session['crews']))?></td><td data-label="Work Orders"><?=(int)$session['work_order_count']?></td><td data-label="Completed Moves"><?=(int)$session['completed_move_count']?></td><td data-label="Exceptions"><?=(int)$session['exception_count']?></td><td data-label="Completion Time"><?=ttHtml(ttHistoryRecordedValue($closedAt))?></td><td data-label="Action"><a class="btn btn-sm btn-primary tt-mobile-full" href="history_view.php?id=<?=(int)$session['id']?>">View</a></td></tr><?php endforeach; ?>
<?php if (!$sessions): ?><tr><td colspan="9" class="text-center text-muted py-5 tt-empty-row"><strong>No session history yet.</strong><div class="mt-1">Completed or cancelled operating sessions will appear here.</div></td></tr><?php endif; ?>
</tbody></table></div></div>
</section></div><?php include '../includes/footer.php'; ?>
