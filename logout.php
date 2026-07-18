<?php

session_start();

require_once __DIR__ . '/config/database.php';

$ttAuthCookieFile = __DIR__ . '/includes/tt_auth_cookie.php';
if (is_file($ttAuthCookieFile)) {
    require_once $ttAuthCookieFile;
}

if (function_exists('tt_auth_clear_cookie')) {
    tt_auth_clear_cookie();
}
if (function_exists('ttAuthForgetLogin')) {
    ttAuthForgetLogin($pdo);
}

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

header('Location: /login.php');
exit;
