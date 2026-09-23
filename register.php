<?php
session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/registration.php';

$message = '';
$values = [];
foreach (['first_name', 'last_name', 'email', 'railroad_name'] as $field) {
    $values[$field] = trim(ttRegistrationValue($_POST, $field));
}
$csrfToken = ttRegistrationCsrfToken();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $message = ttRegisterOwner($pdo, $_POST, $csrfToken);
    if ($message === '') {
        unset($_SESSION['registration_csrf']);
        header('Location: login.php?registered=1');
        exit;
    }
}
?>
<?php include 'includes/header.php'; ?>
<title>TrainTote Ops Manager - Register</title>
</head>
<body>
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <h1 class="mb-4 text-center">TrainTote Ops Manager</h1>
            <div class="card"><div class="card-body">
                <h3 class="mb-4">Create Account</h3>
                <p>Create your railroad owner account. Invited operators and session crew should use their session invitation.</p>
                <?php if ($message): ?><div class="alert alert-danger"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <div class="mb-3"><label for="first_name" class="form-label">First Name</label><input id="first_name" type="text" name="first_name" class="form-control" autocomplete="given-name" required value="<?= htmlspecialchars($values['first_name'], ENT_QUOTES, 'UTF-8') ?>"></div>
                    <div class="mb-3"><label for="last_name" class="form-label">Last Name</label><input id="last_name" type="text" name="last_name" class="form-control" autocomplete="family-name" required value="<?= htmlspecialchars($values['last_name'], ENT_QUOTES, 'UTF-8') ?>"></div>
                    <div class="mb-3"><label for="email" class="form-label">Email</label><input id="email" type="email" name="email" class="form-control" autocomplete="email" required value="<?= htmlspecialchars($values['email'], ENT_QUOTES, 'UTF-8') ?>"></div>
                    <div class="mb-3"><label for="password" class="form-label">Password</label><input id="password" type="password" name="password" class="form-control" autocomplete="new-password" minlength="8" aria-describedby="password_help" required><div id="password_help" class="form-text">Use at least 8 characters.</div></div>
                    <div class="mb-3"><label for="confirm_password" class="form-label">Confirm Password</label><input id="confirm_password" type="password" name="confirm_password" class="form-control" autocomplete="new-password" required></div>
                    <div class="mb-3"><label for="railroad_name" class="form-label">Railroad Name</label><input id="railroad_name" type="text" name="railroad_name" class="form-control" required value="<?= htmlspecialchars($values['railroad_name'], ENT_QUOTES, 'UTF-8') ?>"></div>
                    <button type="submit" class="btn btn-primary w-100">Create Account</button>
                </form>
                <p class="mt-3 mb-0 text-center">Already have an account? <a href="login.php">Log in</a></p>
            </div></div>
        </div>
    </div>
</div>
<?php include 'includes/footer.php'; ?>
