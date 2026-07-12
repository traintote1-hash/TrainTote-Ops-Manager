<?php
require_once 'config/database.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email !== '' && $password !== '') {
        $stmt = $pdo->prepare('
            INSERT INTO users (first_name, last_name, email, password_hash)
            VALUES (:first_name, :last_name, :email, :password_hash)
        ');

        try {
            $stmt->execute([
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT)
            ]);
            header('Location: login.php?registered=1');
            exit;
        } catch (PDOException $e) {
            $message = 'Error creating account.';
        }
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
                <?php if ($message): ?><div class="alert alert-info"><?= htmlspecialchars($message) ?></div><?php endif; ?>
                <form method="post">
                    <div class="mb-3"><label class="form-label">First Name</label><input type="text" name="first_name" class="form-control"></div>
                    <div class="mb-3"><label class="form-label">Last Name</label><input type="text" name="last_name" class="form-control"></div>
                    <div class="mb-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">Password</label><input type="password" name="password" class="form-control" required></div>
                    <button type="submit" class="btn btn-primary w-100">Create Account</button>
                </form>
            </div></div>
        </div>
    </div>
</div>
<?php include 'includes/footer.php'; ?>
