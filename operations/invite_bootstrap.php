<?php
session_start();
header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/invitation_service.php';
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function ttInvitePageError(Throwable $error): string
{
    if ($error instanceof TtInvitationError) return $error->getMessage();
    error_log('TrainTote session access: ' . $error->getMessage());
    return 'Session access is unavailable right now. Please try again or contact the owner.';
}

function ttInviteRequireCsrf(): void
{
    $token = ttInviteInput($_POST, 'csrf_token');
    if ($token === '' || !hash_equals(ttOperationsCsrfToken(), $token)) {
        throw new TtInvitationError('The form expired. Refresh the page and try again.');
    }
}
