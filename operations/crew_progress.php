<?php
require_once __DIR__ . '/invite_bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
try {
    ttInviteRequireCsrf();
    $value = ttInviteInput($_POST, 'complete');
    if (!in_array($value, ['0', '1'], true)) throw new TtInvitationError('Choose a valid progress value.');
    ttCrewSetProgress($pdo, (array)($_SESSION['ops_crew_access'] ?? []), (int)($_POST['move_id'] ?? 0), $value === '1');
    header('Location: crew.php');
} catch (Throwable $e) {
    http_response_code(403);
    $error = ttInvitePageError($e);
    include '../includes/header.php';
    echo '<title>Session Access - TrainTote</title></head><body><main class="container my-5"><div class="alert alert-danger">'
        . ttHtml($error) . '</div><a href="crew.php">Back to session</a></main>';
    include '../includes/footer.php';
}
