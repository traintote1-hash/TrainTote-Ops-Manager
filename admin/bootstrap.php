<?php
session_start();
require_once __DIR__ . '/../config/database.php';
if (empty($_SESSION['user_id'])) { header('Location: ../login.php?redirect=admin/index.php'); exit; }
$check=$pdo->prepare('SELECT id, first_name, email, is_admin FROM users WHERE id=? LIMIT 1');
$check->execute([$_SESSION['user_id']]); $admin=$check->fetch(PDO::FETCH_ASSOC);
if (!$admin || empty($admin['is_admin'])) { http_response_code(403); exit('Administrator access is required.'); }
function admin_csrf(): string { if (empty($_SESSION['admin_csrf'])) $_SESSION['admin_csrf']=bin2hex(random_bytes(24)); return $_SESSION['admin_csrf']; }
function admin_audit(PDO $pdo,int $actor,string $event,?int $target=null,array $details=[]):void { $pdo->prepare('INSERT INTO admin_audit_log (actor_user_id,target_user_id,event_type,details) VALUES (?,?,?,?)')->execute([$actor,$target,$event,json_encode($details)]); }
