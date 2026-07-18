<?php

session_start();

require_once 'config/database.php';
require_once 'includes/tt_auth_cookie.php';

ttAuthForgetLogin($pdo);

$_SESSION = array();

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

session_destroy();

header('Location: /');
exit;
