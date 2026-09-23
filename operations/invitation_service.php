<?php
require_once __DIR__ . '/lib.php';

class TtInvitationError extends RuntimeException {}

function ttInviteInput(array $input, string $key): string
{
    return isset($input[$key]) && is_string($input[$key]) ? $input[$key] : '';
}

function ttInviteLock(PDO $pdo): string
{
    return $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
}

function ttInviteCurrentSession(array $session): bool
{
    return in_array($session['status'] ?? '', ['draft', 'ready', 'in_progress'], true);
}

function ttInviteOwnerSession(PDO $pdo, int $sessionId, int $userId, bool $lock = false): array
{
    $stmt = $pdo->prepare('SELECT s.*, r.name railroad_name FROM operating_sessions s
        JOIN railroads r ON r.id=s.railroad_id WHERE s.id=? AND r.user_id=?'
        . ($lock ? ttInviteLock($pdo) : ''));
    $stmt->execute([$sessionId, $userId]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$session) throw new TtInvitationError('Session not found or access denied.');
    return $session;
}

function ttInviteAssignment(PDO $pdo, int $sessionId, int $railroadId, int $assignmentId): void
{
    if ($assignmentId === 0) return;
    $stmt = $pdo->prepare('SELECT id FROM operation_assignments WHERE id=? AND session_id=? AND railroad_id=?');
    $stmt->execute([$assignmentId, $sessionId, $railroadId]);
    if (!$stmt->fetchColumn()) throw new TtInvitationError('Choose an assignment from this session.');
}

function ttCreateSessionInvite(PDO $pdo, int $sessionId, int $ownerId, string $type, string $email, int $assignmentId, int $days): array
{
    if (!in_array($type, ['email', 'qr'], true) || !in_array($days, [1, 7, 30], true)) {
        throw new TtInvitationError('Choose a valid invitation type and expiry.');
    }
    $email = trim($email);
    if ($type === 'email' && (strlen($email) > 255 || !filter_var($email, FILTER_VALIDATE_EMAIL)
        || preg_match('/[\r\n]/', $email))) throw new TtInvitationError('Enter a valid email address.');
    $pdo->beginTransaction();
    try {
        $session = ttInviteOwnerSession($pdo, $sessionId, $ownerId, true);
        if (!ttInviteCurrentSession($session)) throw new TtInvitationError('This session has ended.');
        if ($type === 'email') ttInviteAssignment($pdo, $sessionId, (int)$session['railroad_id'], $assignmentId);
        $count = $pdo->prepare('SELECT COUNT(*) FROM operation_session_invites WHERE session_id=? AND created_at>=?');
        $count->execute([$sessionId, gmdate('Y-m-d H:i:s', time() - 3600)]);
        if ((int)$count->fetchColumn() >= 50) throw new TtInvitationError('Please wait before creating more invitations.');
        $token = bin2hex(random_bytes(32));
        $expires = gmdate('Y-m-d H:i:s', time() + $days * 86400);
        $stmt = $pdo->prepare('INSERT INTO operation_session_invites
            (session_id,token_hash,invite_type,email,assignment_id,created_by_user_id,created_at,expires_at)
            VALUES(?,?,?,?,?,?,?,?)');
        $stmt->execute([$sessionId, hash('sha256', $token), $type, $type === 'email' ? $email : null,
            $type === 'email' && $assignmentId ? $assignmentId : null, $ownerId, gmdate('Y-m-d H:i:s'), $expires]);
        $id = (int)$pdo->lastInsertId();
        $pdo->commit();
        return ['id' => $id, 'token' => $token, 'expires_at' => $expires, 'session' => $session];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function ttSessionInvite(PDO $pdo, string $token): array
{
    if (!preg_match('/\A[a-f0-9]{64}\z/', $token)) throw new TtInvitationError('This invitation is unavailable. Ask the owner for a new invitation.');
    $stmt = $pdo->prepare('SELECT i.*,s.railroad_id,s.status session_status,s.session_number,s.session_name,r.name railroad_name
        FROM operation_session_invites i JOIN operating_sessions s ON s.id=i.session_id
        JOIN railroads r ON r.id=s.railroad_id WHERE i.token_hash=?');
    $stmt->execute([hash('sha256', $token)]);
    $invite = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$invite || $invite['revoked_at'] || $invite['expires_at'] <= gmdate('Y-m-d H:i:s')
        || !ttInviteCurrentSession(['status' => $invite['session_status']])
        || ($invite['invite_type'] === 'email' && $invite['claimed_at'])) {
        throw new TtInvitationError('This invitation is unavailable. Ask the owner for a new invitation.');
    }
    return $invite;
}

function ttJoinSessionInvite(PDO $pdo, string $token, string $name): array
{
    $name = trim($name);
    if ($name === '' || strlen($name) > 120 || preg_match('/[\x00-\x1f\x7f]/', $name)) {
        throw new TtInvitationError('Enter your name (up to 120 characters).');
    }
    $invite = ttSessionInvite($pdo, $token);
    $pdo->beginTransaction();
    try {
        // Serialize joins and owner changes on the session before rechecking the invite.
        $stmt = $pdo->prepare('SELECT id FROM operating_sessions WHERE id=?' . ttInviteLock($pdo));
        $stmt->execute([$invite['session_id']]);
        $stmt->fetchColumn();
        $stmt = $pdo->prepare('SELECT id FROM operation_session_invites WHERE id=?' . ttInviteLock($pdo));
        $stmt->execute([$invite['id']]);
        $stmt->fetchColumn();
        $invite = ttSessionInvite($pdo, $token);
        $count = $pdo->prepare('SELECT COUNT(*) FROM operation_session_participants WHERE invite_id=?');
        $count->execute([$invite['id']]);
        if ((int)$count->fetchColumn() >= 100) throw new TtInvitationError('This invitation is full. Ask the owner for a new invitation.');
        $secret = bin2hex(random_bytes(32));
        $status = $invite['invite_type'] === 'email' ? 'approved' : 'pending';
        $stmt = $pdo->prepare('INSERT INTO operation_session_participants
            (invite_id,display_name,access_hash,status,assignment_id,joined_at) VALUES(?,?,?,?,?,?)');
        $stmt->execute([$invite['id'], $name, hash('sha256', $secret), $status, $invite['assignment_id'], gmdate('Y-m-d H:i:s')]);
        $id = (int)$pdo->lastInsertId();
        if ($invite['invite_type'] === 'email') {
            $pdo->prepare('UPDATE operation_session_invites SET claimed_at=? WHERE id=?')->execute([gmdate('Y-m-d H:i:s'), $invite['id']]);
        }
        $pdo->commit();
        return ['id' => $id, 'secret' => $secret];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function ttSessionParticipant(PDO $pdo, array $access, bool $allowPending = false): array
{
    $secret = ttInviteInput($access, 'secret');
    if (!preg_match('/\A[a-f0-9]{64}\z/', $secret)) throw new TtInvitationError('Open your session invitation to join.');
    $stmt = $pdo->prepare('SELECT p.*,i.session_id,i.expires_at,i.revoked_at,s.railroad_id,
        s.status session_status,s.session_name,s.session_number,r.name railroad_name
        FROM operation_session_participants p JOIN operation_session_invites i ON i.id=p.invite_id
        JOIN operating_sessions s ON s.id=i.session_id JOIN railroads r ON r.id=s.railroad_id
        WHERE p.id=? AND p.access_hash=?');
    $stmt->execute([(int)($access['id'] ?? 0), hash('sha256', $secret)]);
    $member = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$member || $member['revoked_at'] || $member['expires_at'] <= gmdate('Y-m-d H:i:s')
        || !ttInviteCurrentSession(['status' => $member['session_status']])
        || !in_array($member['status'], $allowPending ? ['pending', 'approved'] : ['approved'], true)) {
        throw new TtInvitationError('Session access is unavailable or awaiting owner approval.');
    }
    return $member;
}

function ttManageSessionParticipant(PDO $pdo, int $sessionId, int $ownerId, int $participantId, string $status, int $assignmentId): void
{
    if (!in_array($status, ['approved', 'revoked'], true)) throw new TtInvitationError('Choose a valid access status.');
    $pdo->beginTransaction();
    try {
        $session = ttInviteOwnerSession($pdo, $sessionId, $ownerId, true);
        if (!ttInviteCurrentSession($session)) throw new TtInvitationError('This session has ended.');
        ttInviteAssignment($pdo, $sessionId, (int)$session['railroad_id'], $assignmentId);
        $stmt = $pdo->prepare('SELECT p.id FROM operation_session_participants p JOIN operation_session_invites i ON i.id=p.invite_id
            WHERE p.id=? AND i.session_id=? AND i.revoked_at IS NULL AND i.expires_at>?');
        $stmt->execute([$participantId, $sessionId, gmdate('Y-m-d H:i:s')]);
        if (!$stmt->fetchColumn()) throw new TtInvitationError('Participant not found or invitation expired.');
        $pdo->prepare('UPDATE operation_session_participants SET status=?,assignment_id=? WHERE id=?')
            ->execute([$status, $assignmentId ?: null, $participantId]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function ttRevokeSessionInvite(PDO $pdo, int $sessionId, int $ownerId, int $inviteId): void
{
    $pdo->beginTransaction();
    try {
        ttInviteOwnerSession($pdo, $sessionId, $ownerId, true);
        $pdo->prepare('UPDATE operation_session_invites SET revoked_at=? WHERE id=? AND session_id=?')
            ->execute([gmdate('Y-m-d H:i:s'), $inviteId, $sessionId]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function ttCrewSetProgress(PDO $pdo, array $access, int $moveId, bool $complete): void
{
    $member = ttSessionParticipant($pdo, $access);
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT id FROM operating_sessions WHERE id=?' . ttInviteLock($pdo));
        $stmt->execute([$member['session_id']]);
        $stmt->fetchColumn();
        $member = ttSessionParticipant($pdo, $access);
        $stmt = $pdo->prepare('SELECT m.id,sl.id list_id,sl.status list_status,a.id assignment_id,a.status assignment_status
            FROM operation_switch_list_moves m JOIN operation_switch_lists sl ON sl.id=m.switch_list_id AND sl.railroad_id=m.railroad_id
            JOIN operation_assignments a ON a.id=sl.assignment_id AND a.session_id=sl.session_id AND a.railroad_id=sl.railroad_id
            WHERE m.id=? AND sl.session_id=? AND sl.railroad_id=? AND a.id=? AND m.equipment_id IS NOT NULL' . ttInviteLock($pdo));
        $stmt->execute([$moveId, $member['session_id'], $member['railroad_id'], (int)$member['assignment_id']]);
        $move = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$move || $member['session_status'] !== 'in_progress'
            || !in_array($move['list_status'], ['approved', 'in_progress'], true)
            || !in_array($move['assignment_status'], ['ready', 'in_progress', 'needs_review'], true)) {
            throw new TtInvitationError('You can update only your assigned work in an active session.');
        }
        $pdo->prepare('UPDATE operation_switch_list_moves SET progress_complete=?,progress_updated_at=? WHERE id=?')
            ->execute([(int)$complete, gmdate('Y-m-d H:i:s'), $moveId]);
        $pdo->prepare("UPDATE operation_switch_lists SET status='in_progress',started_at=COALESCE(started_at,?) WHERE id=? AND status='approved'")
            ->execute([gmdate('Y-m-d H:i:s'), $move['list_id']]);
        $pdo->prepare("UPDATE operation_assignments SET status='in_progress',started_at=COALESCE(started_at,?) WHERE id=? AND status='ready'")
            ->execute([gmdate('Y-m-d H:i:s'), $move['assignment_id']]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function ttSessionInviteUrl(string $token): string
{
    // Never construct an emailed bearer link from an untrusted Host header.
    return 'https://ops.traintote.com/operations/join.php?token=' . rawurlencode($token);
}

function ttSendSessionInvite(string $email, string $url, array $session): bool
{
    $from = (string)getenv('TT_OPS_MAIL_FROM');
    if (!filter_var($from, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $from)) return false;
    $body = "You are invited to a TrainTote operating session.\n\n"
        . $session['railroad_name'] . ' - ' . $session['session_number'] . "\n\n"
        . "Join free, without a password:\n" . $url . "\n\n"
        . "This personal link can be used once. Do not forward it. Access ends when the session closes or the invitation expires.\n";
    return mail($email, 'TrainTote operating session invitation', $body,
        ['From' => $from, 'Content-Type' => 'text/plain; charset=UTF-8']);
}
