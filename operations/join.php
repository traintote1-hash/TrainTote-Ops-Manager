<?php
require_once __DIR__ . '/invite_bootstrap.php';
$error = '';
$invite = null;
try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['token'])) {
        $token = ttInviteInput($_GET, 'token');
        ttSessionInvite($pdo, $token);
        $_SESSION['pending_ops_invite'] = $token;
        // Remove the bearer token from the URL before rendering any assets.
        header('Location: join.php');
        exit;
    }
    $token = ttInviteInput($_SESSION, 'pending_ops_invite');
    $invite = ttSessionInvite($pdo, $token);
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        ttInviteRequireCsrf();
        $access = ttJoinSessionInvite($pdo, $token, ttInviteInput($_POST, 'display_name'));
        session_regenerate_id(true);
        // This is deliberately not user_id and never creates an owner/SSO cookie.
        $_SESSION['ops_crew_access'] = $access;
        unset($_SESSION['pending_ops_invite']);
        header('Location: crew.php');
        exit;
    }
} catch (Throwable $e) {
    $error = ttInvitePageError($e);
}
?>
<?php include '../includes/header.php'; ?>
<title>Join Operating Session - TrainTote</title></head><body>
<main class="container my-5" style="max-width:36rem">
<h1 class="h3">Join an Operating Session</h1>
<?php if ($error): ?><div class="alert alert-danger" role="alert"><?=ttHtml($error)?></div><?php endif; ?>
<?php if ($invite): ?>
<div class="card card-body">
<h2 class="h5"><?=ttHtml($invite['railroad_name'])?></h2>
<p><?=ttHtml($invite['session_number'].' '.($invite['session_name'] ?? ''))?></p>
<p>Joining is free. No password or railroad account is needed.</p>
<?php if ($invite['invite_type'] === 'qr'): ?><p>The owner will approve your access after you join.</p><?php endif; ?>
<form method="post">
<input type="hidden" name="csrf_token" value="<?=ttHtml(ttOperationsCsrfToken())?>">
<label class="form-label" for="crewName">Your name</label>
<input class="form-control mb-3" id="crewName" name="display_name" autocomplete="name" maxlength="120" required value="<?=ttHtml(ttInviteInput($_POST, 'display_name'))?>">
<button class="btn btn-primary" type="submit">Join Session</button>
</form></div>
<?php else: ?><p>Ask the railroad owner for an email invitation or scan their session QR code.</p><?php endif; ?>
</main><?php include '../includes/footer.php'; ?>
