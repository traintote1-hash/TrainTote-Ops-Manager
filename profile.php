<?php
session_start();
require_once __DIR__ . '/config/database.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

$userId = (int) $_SESSION['user_id'];
$notice = ''; $error = '';
if (empty($_SESSION['profile_csrf'])) { $_SESSION['profile_csrf'] = bin2hex(random_bytes(24)); }
function profileValue($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!hash_equals($_SESSION['profile_csrf'], $_POST['csrf_token'] ?? '')) { $error = 'Your form expired. Please try again.'; }
  else {
    $action = $_POST['action'] ?? '';
    try {
      if ($action === 'details') {
        $first = trim($_POST['first_name'] ?? ''); $last = trim($_POST['last_name'] ?? ''); $email = trim($_POST['email'] ?? '');
        if ($first === '' || $last === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Enter a first name, last name, and valid email address.');
        $duplicate = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1'); $duplicate->execute([$email, $userId]);
        if ($duplicate->fetchColumn()) throw new RuntimeException('That email address is already in use.');
        $save = $pdo->prepare('UPDATE users SET first_name = ?, last_name = ?, email = ? WHERE id = ?'); $save->execute([$first, $last, $email, $userId]);
        $notice = 'Personal details saved.';
      } elseif ($action === 'password') {
        $current = $_POST['current_password'] ?? ''; $new = $_POST['new_password'] ?? ''; $confirm = $_POST['confirm_password'] ?? '';
        $row = $pdo->prepare('SELECT password_hash FROM users WHERE id = ?'); $row->execute([$userId]); $hash = (string) $row->fetchColumn();
        if (!password_verify($current, $hash)) throw new RuntimeException('Your current password is incorrect.');
        if (strlen($new) < 8 || strlen($new) > 72 || strpos($new, "\0") !== false) throw new RuntimeException('Use a password between 8 and 72 characters.');
        if ($new !== $confirm) throw new RuntimeException('The new passwords do not match.');
        $save = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?'); $save->execute([password_hash($new, PASSWORD_DEFAULT), $userId]);
        $notice = 'Password updated.';
      } elseif ($action === 'railroad') {
        $name = trim($_POST['railroad_name'] ?? ''); if ($name === '') throw new RuntimeException('Enter a railroad name.');
        $save = $pdo->prepare('UPDATE railroads SET name = ? WHERE user_id = ?'); $save->execute([$name, $userId]); $notice = 'Railroad name updated.';
      }
    } catch (Throwable $exception) { $error = $exception->getMessage(); }
  }
}
$user = $pdo->prepare('SELECT first_name, last_name, email FROM users WHERE id = ?'); $user->execute([$userId]); $user = $user->fetch(PDO::FETCH_ASSOC);
$railroad = $pdo->prepare('SELECT name FROM railroads WHERE user_id = ? LIMIT 1'); $railroad->execute([$userId]); $railroad = $railroad->fetch(PDO::FETCH_ASSOC);
$plan = 'Free';
try { $subscription = $pdo->prepare('SELECT plan_code FROM user_subscriptions WHERE user_id = ? LIMIT 1'); $subscription->execute([$userId]); $plan = ucfirst((string) ($subscription->fetchColumn() ?: 'free')); } catch (Throwable $ignored) {}
?>
<?php include 'includes/header.php'; ?>
<title>Your Profile · TrainTote Ops Manager</title>
</head><body><?php include 'includes/navbar.php'; ?>
<main class="container py-4"><div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4"><div><h1 class="mb-1">Your profile</h1><p class="text-muted mb-0">Manage your account, railroad, plan, and security.</p></div><a href="dashboard.php" class="btn btn-outline-secondary">Back to dashboard</a></div>
<?php if ($notice): ?><div class="alert alert-success"><?=profileValue($notice)?></div><?php endif; ?><?php if ($error): ?><div class="alert alert-danger"><?=profileValue($error)?></div><?php endif; ?>
<div class="row g-4"><div class="col-lg-8"><section class="card mb-4"><div class="card-body"><h2 class="h4">Personal details</h2><form method="post" class="row g-3"><input type="hidden" name="csrf_token" value="<?=profileValue($_SESSION['profile_csrf'])?>"><input type="hidden" name="action" value="details"><div class="col-md-6"><label class="form-label">First name</label><input class="form-control" name="first_name" required value="<?=profileValue($user['first_name'] ?? '')?>"></div><div class="col-md-6"><label class="form-label">Last name</label><input class="form-control" name="last_name" required value="<?=profileValue($user['last_name'] ?? '')?>"></div><div class="col-12"><label class="form-label">Email address</label><input class="form-control" type="email" name="email" required value="<?=profileValue($user['email'] ?? '')?>"></div><div class="col-12"><button class="btn btn-primary">Save details</button></div></form></div></section>
<section class="card"><div class="card-body"><h2 class="h4">Password</h2><p class="text-muted">Use at least 8 characters.</p><form method="post" class="row g-3"><input type="hidden" name="csrf_token" value="<?=profileValue($_SESSION['profile_csrf'])?>"><input type="hidden" name="action" value="password"><div class="col-12"><label class="form-label">Current password</label><input class="form-control" type="password" name="current_password" autocomplete="current-password" required></div><div class="col-md-6"><label class="form-label">New password</label><input class="form-control" type="password" name="new_password" autocomplete="new-password" required></div><div class="col-md-6"><label class="form-label">Confirm new password</label><input class="form-control" type="password" name="confirm_password" autocomplete="new-password" required></div><div class="col-12"><button class="btn btn-primary">Update password</button></div></form></div></section></div>
<div class="col-lg-4"><section class="card mb-4"><div class="card-body"><h2 class="h4">Your railroad</h2><form method="post"><input type="hidden" name="csrf_token" value="<?=profileValue($_SESSION['profile_csrf'])?>"><input type="hidden" name="action" value="railroad"><label class="form-label">Railroad name</label><input class="form-control mb-3" name="railroad_name" required value="<?=profileValue($railroad['name'] ?? '')?>"><button class="btn btn-outline-primary">Save railroad</button></form></div></section><section class="card"><div class="card-body"><h2 class="h4">Plan & billing</h2><p class="mb-1"><strong><?=profileValue($plan)?></strong> plan</p><p class="text-muted small">Review plan options or manage your subscription.</p><a class="btn btn-outline-primary" href="pricing.php">Manage plan</a></div></section></div></div></main><?php include 'includes/footer.php'; ?>
