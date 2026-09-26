<?php
require_once __DIR__ . '/invite_bootstrap.php';
if (empty($_SESSION['user_id'])) { header('Location: ../login.php'); exit; }
$ownerId = (int)$_SESSION['user_id'];
$sessionId = (int)($_GET['id'] ?? $_POST['session_id'] ?? 0);
$error = '';
$notice = '';
$flash = null;
try {
    $session = ttInviteOwnerSession($pdo, $sessionId, $ownerId);
    if (!ttPlanCan($pdo, $ownerId, 'crew_access')) {
        throw new TtInvitationError('Crew invitations and multi-person operations require Pro. Plus can still run one-person operating sessions.');
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        ttInviteRequireCsrf();
        $action = ttInviteInput($_POST, 'action');
        if ($action === 'create') {
            $type = ttInviteInput($_POST, 'invite_type');
            $email = trim(ttInviteInput($_POST, 'email'));
            $invite = ttCreateSessionInvite($pdo, $sessionId, $ownerId, $type, $email,
                (int)($_POST['assignment_id'] ?? 0), (int)($_POST['days'] ?? 7));
            $url = ttSessionInviteUrl($invite['token']);
            $notice = 'Invitation created. Save the link now; it is shown only once.';
            if ($type === 'email') {
                $sent = ttSendSessionInvite($email, $url, $invite['session']);
                $notice = $sent
                    ? 'Invitation accepted by the mail server. Delivery is not guaranteed; you can also copy the link below.'
                    : 'Email could not be sent. Copy the invitation link below and email it yourself. See the mail setup instructions.';
            }
            $_SESSION['ops_invite_flash'] = ['session_id' => $sessionId, 'url' => $url, 'type' => $type, 'notice' => $notice];
        } elseif ($action === 'revoke') {
            ttRevokeSessionInvite($pdo, $sessionId, $ownerId, (int)($_POST['invite_id'] ?? 0));
        } elseif ($action === 'participant') {
            ttManageSessionParticipant($pdo, $sessionId, $ownerId, (int)($_POST['participant_id'] ?? 0),
                ttInviteInput($_POST, 'status'), (int)($_POST['assignment_id'] ?? 0));
        } else {
            throw new TtInvitationError('Choose a valid action.');
        }
        header('Location: invitations.php?id=' . $sessionId);
        exit;
    }
} catch (Throwable $e) { $error = ttInvitePageError($e); }
if (!isset($session)) { http_response_code(403); die(ttHtml($error)); }
if (isset($_SESSION['ops_invite_flash']) && (int)$_SESSION['ops_invite_flash']['session_id'] === $sessionId) {
    $flash = $_SESSION['ops_invite_flash'];
    unset($_SESSION['ops_invite_flash']);
}
$assignments = $invites = $participants = [];
try {
    $stmt = $pdo->prepare('SELECT id,title_snapshot,assignment_number FROM operation_assignments WHERE session_id=? AND railroad_id=? ORDER BY sequence_number');
    $stmt->execute([$sessionId, $session['railroad_id']]);
    $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt = $pdo->prepare('SELECT id,invite_type,email,expires_at,revoked_at,claimed_at FROM operation_session_invites WHERE session_id=? ORDER BY id DESC');
    $stmt->execute([$sessionId]); $invites = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt = $pdo->prepare('SELECT p.id,p.display_name,p.status,p.assignment_id,i.email,i.expires_at,i.revoked_at
        FROM operation_session_participants p JOIN operation_session_invites i ON i.id=p.invite_id WHERE i.session_id=? ORDER BY p.id DESC');
    $stmt->execute([$sessionId]); $participants = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { $error = ttInvitePageError($e); }
?>
<?php include '../includes/header.php'; ?><title>Session Invitations - TrainTote</title></head><body>
<?php include '../includes/navbar.php'; ?>
<main class="container my-4">
<a href="session_edit.php?id=<?=$sessionId?>">Back to session</a>
<h1 class="h3 mt-3">Invite Crew — <?=ttHtml($session['session_number'])?></h1>
<p>Crew join free and can see only this session. Only the assignment you select allows progress updates; you retain final work-order closeout.</p>
<?php if ($error): ?><div class="alert alert-danger" role="alert"><?=ttHtml($error)?></div><?php endif; ?>
<?php if ($flash): ?><div class="alert alert-info"><?=ttHtml($flash['notice'])?></div>
<label class="form-label" for="inviteLink">Invitation link</label><input id="inviteLink" class="form-control mb-2" readonly value="<?=ttHtml($flash['url'])?>">
<button type="button" id="copyInvite" class="btn btn-outline-primary mb-3">Copy link</button>
<?php if ($flash['type'] === 'qr'): ?><div id="inviteQr" class="bg-white p-4 d-inline-block" aria-label="Session invitation QR code" data-url="<?=ttHtml($flash['url'])?>"></div><p>Scan to request access. Approve each crew member below.</p><?php endif; ?>
<?php endif; ?>
<?php if (ttInviteCurrentSession($session)): ?>
<div class="card card-body mb-4"><h2 class="h5">Create invitation</h2>
<form method="post" class="row g-3">
<input type="hidden" name="csrf_token" value="<?=ttHtml(ttOperationsCsrfToken())?>"><input type="hidden" name="session_id" value="<?=$sessionId?>"><input type="hidden" name="action" value="create">
<div class="col-md-3"><label class="form-label" for="inviteType">Invitation type</label><select class="form-select" id="inviteType" name="invite_type"><option value="email">Personal email</option><option value="qr">Shared QR code</option></select></div>
<div class="col-md-4"><label class="form-label" for="inviteEmail">Email (personal invitation only)</label><input type="email" class="form-control" id="inviteEmail" name="email" maxlength="255"></div>
<div class="col-md-2"><label class="form-label" for="inviteDays">Expires in</label><select class="form-select" id="inviteDays" name="days"><option value="1">1 day</option><option value="7" selected>7 days</option><option value="30">30 days</option></select></div>
<div class="col-md-3"><label class="form-label" for="inviteAssignment">Personal invite assignment</label><select class="form-select" id="inviteAssignment" name="assignment_id"><option value="0">View only</option><?php foreach ($assignments as $a): ?><option value="<?=(int)$a['id']?>"><?=ttHtml($a['assignment_number'].' '.$a['title_snapshot'])?></option><?php endforeach; ?></select><div class="form-text">QR crew receive assignments after owner approval.</div></div>
<div><button class="btn btn-primary">Create invitation</button></div>
</form><p class="text-muted mt-3 mb-0">Personal links admit one person. QR joins require approval. Revoking an invitation removes access for everyone who joined through it. All crew access closes when the session ends or the invitation expires.</p></div>
<?php endif; ?>
<h2 class="h4">Participants</h2><p><a href="invitations.php?id=<?=$sessionId?>">Refresh participants</a></p>
<?php foreach ($participants as $p): ?><div class="card card-body mb-2">
<h3 class="h6"><?=ttHtml($p['display_name'])?> — <?=ttHtml($p['status'])?></h3>
<?php if ($p['revoked_at'] || $p['expires_at'] <= gmdate('Y-m-d H:i:s')): ?><p>Invitation revoked or expired.</p>
<?php elseif (ttInviteCurrentSession($session)): ?><form method="post" class="d-flex flex-wrap gap-2">
<input type="hidden" name="csrf_token" value="<?=ttHtml(ttOperationsCsrfToken())?>"><input type="hidden" name="session_id" value="<?=$sessionId?>"><input type="hidden" name="action" value="participant"><input type="hidden" name="participant_id" value="<?=(int)$p['id']?>">
<select class="form-select w-auto" name="assignment_id" aria-label="Assignment for <?=ttHtml($p['display_name'])?>"><option value="0">View only</option><?php foreach ($assignments as $a): ?><option value="<?=(int)$a['id']?>" <?=(int)$a['id']===(int)$p['assignment_id']?'selected':''?>><?=ttHtml($a['assignment_number'].' '.$a['title_snapshot'])?></option><?php endforeach; ?></select>
<button class="btn btn-success" name="status" value="approved">Approve / Save access</button><button class="btn btn-outline-danger" name="status" value="revoked">Remove access</button></form><?php endif; ?></div><?php endforeach; ?>
<?php if (!$participants): ?><p>No crew have joined yet.</p><?php endif; ?>
<h2 class="h4 mt-4">Invitations</h2>
<?php foreach ($invites as $i): ?><div class="card card-body mb-2"><p><?=ttHtml($i['invite_type']==='email'?$i['email']:'Shared QR invitation')?> · Expires <?=ttHtml($i['expires_at'])?> UTC <?=$i['claimed_at']?'· Claimed':''?> <?=$i['revoked_at']?'· Revoked':''?></p>
<?php if (!$i['revoked_at']): ?><form method="post"><input type="hidden" name="csrf_token" value="<?=ttHtml(ttOperationsCsrfToken())?>"><input type="hidden" name="session_id" value="<?=$sessionId?>"><input type="hidden" name="action" value="revoke"><input type="hidden" name="invite_id" value="<?=(int)$i['id']?>"><button class="btn btn-sm btn-outline-danger">Revoke invitation and its crew access</button></form><?php endif; ?></div><?php endforeach; ?>
</main>
<script src="../assets/vendor/qrcode.min.js"></script>
<script src="../assets/js/session-invitations.js"></script>
<?php include '../includes/footer.php'; ?>
