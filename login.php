<?php

session_start();

require_once __DIR__ . '/config/database.php';

$ttAuthCookieFile = __DIR__ . '/includes/tt_auth_cookie.php';
if (is_file($ttAuthCookieFile)) {
    require_once $ttAuthCookieFile;
}

function tt_login_starts_with($haystack, $needle)
{
    return $needle === '' || substr($haystack, 0, strlen($needle)) === $needle;
}

function tt_login_safe_redirect($redirect)
{
    if (!is_string($redirect) || trim($redirect) === '') {
        return 'dashboard.php';
    }

    $redirect = trim($redirect);
    if (preg_match('/[\x00-\x1F\x7F]/', $redirect) || strpos($redirect, '\\') !== false) {
        return 'dashboard.php';
    }

    if (tt_login_starts_with($redirect, '//')) {
        return 'dashboard.php';
    }

    $parts = parse_url($redirect);
    if ($parts === false) {
        return 'dashboard.php';
    }

    if (isset($parts['scheme']) || isset($parts['host'])) {
        $allowedHosts = array(
            'ops.traintote.com',
            'wiki.traintote.com',
            'forum.traintote.com',
            'traintote.com'
        );
        $scheme = isset($parts['scheme']) ? strtolower($parts['scheme']) : '';
        $host = isset($parts['host']) ? strtolower($parts['host']) : '';
        if (($scheme !== 'http' && $scheme !== 'https') || !in_array($host, $allowedHosts, true)) {
            return 'dashboard.php';
        }
    }

    return $redirect;
}

$message = '';
$redirect = tt_login_safe_redirect(
    isset($_GET['redirect']) ? $_GET['redirect'] : (isset($_POST['redirect']) ? $_POST['redirect'] : 'dashboard.php')
);

if (empty($_SESSION['user_id']) && function_exists('ttAuthRestoreLogin')) {
    ttAuthRestoreLogin($pdo);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && !empty($_SESSION['user_id'])) {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
    $stmt->execute(array('id' => $_SESSION['user_id']));
    $sessionUser = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($sessionUser) {
        $_SESSION['first_name'] = isset($sessionUser['first_name']) ? $sessionUser['first_name'] : '';
        if (function_exists('tt_auth_set_cookie')) {
            tt_auth_set_cookie($sessionUser);
        }
        if (
            function_exists('ttAuthRememberLogin')
            && function_exists('ttAuthCookieName')
            && empty($_COOKIE[ttAuthCookieName()])
        ) {
            ttAuthRememberLogin($pdo, $sessionUser['id']);
        }
        header('Location: ' . $redirect);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim(isset($_POST['email']) ? $_POST['email'] : '');
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
    $stmt->execute(array('email' => $email));
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['first_name'] = isset($user['first_name']) ? $user['first_name'] : '';

        if (function_exists('tt_auth_set_cookie')) {
            tt_auth_set_cookie($user);
        }
        if (function_exists('ttAuthRememberLogin')) {
            ttAuthRememberLogin($pdo, $user['id']);
        }

        header('Location: ' . $redirect);
        exit;
    }

    $message = 'Invalid email or password.';
}

?>

<?php include __DIR__ . '/includes/header.php'; ?>

<title>TrainTote Ops Manager - Login</title>

</head>

<body>

<div class="container mt-5">
<div class="row justify-content-center">
<div class="col-md-6 col-lg-5">
<h1 class="mb-4 text-center">🚂 TrainTote Ops Manager</h1>
<div class="card">
<div class="card-body">
<h3 class="mb-4">Login</h3>

<?php if ($message): ?>
<div class="alert alert-danger">
<?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
</div>
<?php endif; ?>

<form method="post">
<input type="hidden" name="redirect" value="<?php echo htmlspecialchars($redirect, ENT_QUOTES, 'UTF-8'); ?>">

<div class="mb-3">
<label>Email</label>
<input type="email" name="email" class="form-control" required>
</div>

<div class="mb-3">
<label>Password</label>
<input type="password" name="password" class="form-control" required>
</div>

<button type="submit" class="btn btn-primary w-100">Login</button>
</form>
</div>
</div>
</div>
</div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
