<?php

session_start();

require_once 'config/database.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim($_POST['email']);
    $password = $_POST['password'];

    $stmt = $pdo->prepare("
        SELECT *
        FROM users
        WHERE email = :email
        LIMIT 1
    ");

    $stmt->execute([
        'email' => $email
    ]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (
        $user &&
        password_verify(
            $password,
            $user['password_hash']
        )
    ) {

        $_SESSION['user_id'] = $user['id'];

        $_SESSION['first_name'] =
            $user['first_name'];

        header(
            'Location: dashboard.php'
        );

        exit;

    } else {

        $message =
            'Invalid email or password.';

    }

}
?>

<?php include 'includes/header.php'; ?>

<title>TrainTote Ops Manager - Login</title>

</head>

<body>

<div class="container mt-5">

<div class="row justify-content-center">

<div class="col-md-6 col-lg-5">

<h1 class="mb-4 text-center">

🚂 TrainTote Ops Manager

</h1>

<div class="card">

<div class="card-body">

<h3 class="mb-4">

Login

</h3>

<?php if($message): ?>

<div class="alert alert-danger">

<?php echo htmlspecialchars($message); ?>

</div>

<?php endif; ?>

<form method="post">

<div class="mb-3">

<label>Email</label>

<input
type="email"
name="email"
class="form-control"
required>

</div>

<div class="mb-3">

<label>Password</label>

<input
type="password"
name="password"
class="form-control"
required>

</div>

<button
type="submit"
class="btn btn-primary w-100">

Login

</button>

</form>

</div>

</div>

</div>

</div>

</div>

<?php include 'includes/footer.php'; ?>