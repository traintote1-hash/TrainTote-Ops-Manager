<?php
require_once __DIR__ . '/invite_bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
try {
    ttInviteRequireCsrf();
    unset($_SESSION['ops_crew_access'], $_SESSION['pending_ops_invite']);
    session_regenerate_id(true);
    header('Location: join.php');
} catch (Throwable $e) {
    http_response_code(403);
    echo ttHtml(ttInvitePageError($e));
}
