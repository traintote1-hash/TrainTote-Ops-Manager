<?php
require_once dirname(__DIR__) . '/invitation_service.php';

function inviteExpect(bool $ok, string $why): void
{
    if (!$ok) throw new RuntimeException($why);
}

function inviteReject(callable $action, string $why): void
{
    try { $action(); } catch (TtInvitationError $e) { return; }
    throw new RuntimeException($why);
}

$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec('PRAGMA foreign_keys=ON');
$pdo->exec('CREATE TABLE railroads (id INTEGER PRIMARY KEY, user_id INTEGER, name TEXT)');
$pdo->exec('CREATE TABLE operating_sessions (id INTEGER PRIMARY KEY, railroad_id INTEGER, status TEXT, session_number TEXT, session_name TEXT)');
$pdo->exec('CREATE TABLE operation_assignments (id INTEGER PRIMARY KEY, railroad_id INTEGER, session_id INTEGER, assignment_number TEXT, title_snapshot TEXT, status TEXT, started_at TEXT)');
$pdo->exec('CREATE TABLE operation_switch_lists (id INTEGER PRIMARY KEY, railroad_id INTEGER, session_id INTEGER, assignment_id INTEGER, status TEXT, started_at TEXT)');
$pdo->exec('CREATE TABLE operation_switch_list_moves (id INTEGER PRIMARY KEY, railroad_id INTEGER, switch_list_id INTEGER, equipment_id INTEGER, progress_complete INTEGER DEFAULT 0, progress_updated_at TEXT)');
$pdo->exec('CREATE TABLE operation_session_invites (id INTEGER PRIMARY KEY, session_id INTEGER, token_hash TEXT UNIQUE, invite_type TEXT, email TEXT, assignment_id INTEGER, created_by_user_id INTEGER, created_at TEXT, expires_at TEXT, revoked_at TEXT, claimed_at TEXT)');
$pdo->exec('CREATE TABLE operation_session_participants (id INTEGER PRIMARY KEY, invite_id INTEGER, display_name TEXT, access_hash TEXT UNIQUE, status TEXT, assignment_id INTEGER, joined_at TEXT)');
$pdo->exec("INSERT INTO railroads VALUES (10,1,'Owner One'),(20,2,'Owner Two')");
$pdo->exec("INSERT INTO operating_sessions VALUES (100,10,'in_progress','S100','A'),(200,20,'in_progress','S200','B')");
$pdo->exec("INSERT INTO operation_assignments VALUES (1000,10,100,'S100-A','Job A','ready',NULL),(1001,10,100,'S100-B','Job B','ready',NULL),(2000,20,200,'S200-A','Foreign','ready',NULL)");
$pdo->exec("INSERT INTO operation_switch_lists VALUES (5000,10,100,1000,'approved',NULL),(5001,10,100,1001,'approved',NULL),(6000,20,200,2000,'approved',NULL)");
$pdo->exec('INSERT INTO operation_switch_list_moves VALUES (9000,10,5000,1,0,NULL),(9001,10,5001,2,0,NULL),(9002,20,6000,3,0,NULL)');

inviteReject(fn() => ttCreateSessionInvite($pdo, 100, 2, 'email', 'crew@example.com', 1000, 7), 'Another owner must not invite.');
inviteReject(fn() => ttCreateSessionInvite($pdo, 100, 1, 'email', 'crew@example.com', 2000, 7), 'Foreign assignment must be rejected.');
inviteReject(fn() => ttCreateSessionInvite($pdo, 100, 1, 'email', "bad\n@example.com", 1000, 7), 'Email header injection must be rejected.');
$email = ttCreateSessionInvite($pdo, 100, 1, 'email', 'crew@example.com', 1000, 7);
inviteExpect(strpos(ttSessionInviteUrl($email['token']), 'https://ops.traintote.com/operations/join.php?token=') === 0, 'Invitation URL must use fixed HTTPS host.');
inviteExpect(ttSessionInvite($pdo, $email['token'])['session_id'] == 100, 'Email invite resolves only to its session.');
$first = ttJoinSessionInvite($pdo, $email['token'], 'Conductor');
$member = ttSessionParticipant($pdo, $first);
inviteExpect($member['status'] === 'approved' && (int)$member['assignment_id'] === 1000, 'Personal email joins with assigned access.');
inviteReject(fn() => ttJoinSessionInvite($pdo, $email['token'], 'Replay'), 'Personal invitation must be single use.');
inviteReject(fn() => ttSessionParticipant($pdo, ['id'=>$first['id'],'secret'=>str_repeat('0',64)]), 'Wrong access secret must fail.');
inviteReject(fn() => ttCrewSetProgress($pdo, $first, 9001, true), 'Crew must not alter another assignment in same session.');
inviteReject(fn() => ttCrewSetProgress($pdo, $first, 9002, true), 'Crew must not alter another railroad.');
ttCrewSetProgress($pdo, $first, 9000, true);
inviteExpect((int)$pdo->query('SELECT progress_complete FROM operation_switch_list_moves WHERE id=9000')->fetchColumn() === 1, 'Assigned progress update must work.');
ttCrewSetProgress($pdo, $first, 9000, false);
inviteExpect((int)$pdo->query('SELECT progress_complete FROM operation_switch_list_moves WHERE id=9000')->fetchColumn() === 0, 'Crew must be able to correct progress.');

$qr = ttCreateSessionInvite($pdo, 100, 1, 'qr', '', 0, 7);
$second = ttJoinSessionInvite($pdo, $qr['token'], 'Engineer');
inviteReject(fn() => ttSessionParticipant($pdo, $second), 'QR crew must wait for owner approval.');
inviteReject(fn() => ttManageSessionParticipant($pdo, 100, 2, $second['id'], 'approved', 1000), 'Other owner must not approve crew.');
inviteReject(fn() => ttManageSessionParticipant($pdo, 100, 1, $second['id'], 'approved', 2000), 'QR crew must not receive a foreign assignment.');
ttManageSessionParticipant($pdo, 100, 1, $second['id'], 'approved', 1001);
inviteExpect((int)ttSessionParticipant($pdo, $second)['assignment_id'] === 1001, 'Owner approval assigns crew work.');
ttCrewSetProgress($pdo, $second, 9001, true);
ttManageSessionParticipant($pdo, 100, 1, $second['id'], 'revoked', 0);
inviteReject(fn() => ttSessionParticipant($pdo, $second), 'Removed crew must immediately lose access.');
ttRevokeSessionInvite($pdo, 100, 1, $email['id']);
inviteReject(fn() => ttSessionParticipant($pdo, $first), 'Invitation revocation must remove existing crew.');
ttRevokeSessionInvite($pdo, 100, 1, $qr['id']);
inviteReject(fn() => ttJoinSessionInvite($pdo, $qr['token'], 'Late'), 'Revoked QR link must stop joins.');

$ending = ttCreateSessionInvite($pdo, 200, 2, 'email', 'worker@example.com', 2000, 1);
$third = ttJoinSessionInvite($pdo, $ending['token'], 'Worker');
$pdo->exec("UPDATE operating_sessions SET status='completed' WHERE id=200");
inviteReject(fn() => ttSessionParticipant($pdo, $third), 'Closing session must end crew access.');
inviteReject(fn() => ttCreateSessionInvite($pdo, 200, 2, 'qr', '', 0, 1), 'Ended session must not issue invites.');

echo "session_invitations_test: OK\n";
