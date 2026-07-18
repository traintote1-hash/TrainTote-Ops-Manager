<?php

// The shared signed cookie keeps the Forum/Wiki bridge compatible. The separate
// rotating database token rebuilds an Ops PHP session if shared-hosting cleanup
// removes it before the advertised lifetime.

if (!defined('TT_AUTH_COOKIE_NAME')) {
    define('TT_AUTH_COOKIE_NAME', 'TT_OPS_AUTH');
}

if (!defined('TT_AUTH_COOKIE_DOMAIN')) {
    define('TT_AUTH_COOKIE_DOMAIN', '.traintote.com');
}

if (!defined('TT_AUTH_COOKIE_LIFETIME')) {
    define('TT_AUTH_COOKIE_LIFETIME', 60 * 60 * 24 * 14);
}

if (!defined('TT_AUTH_SECRET')) {
    $ttAuthSecretFile = __DIR__ . '/../config/tt_auth_secret.php';
    if (is_file($ttAuthSecretFile)) {
        require_once $ttAuthSecretFile;
    }
}

function tt_auth_base64url_encode($value)
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function tt_auth_base64url_decode($value)
{
    $padding = strlen($value) % 4;
    if ($padding) {
        $value .= str_repeat('=', 4 - $padding);
    }
    return base64_decode(strtr($value, '-_', '+/'), true);
}

function tt_auth_sign($payload)
{
    if (!defined('TT_AUTH_SECRET') || TT_AUTH_SECRET === '') {
        return false;
    }
    return hash_hmac('sha256', $payload, TT_AUTH_SECRET);
}

function tt_auth_cookie_options($expires)
{
    return array(
        'expires' => $expires,
        'path' => '/',
        'domain' => TT_AUTH_COOKIE_DOMAIN,
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax'
    );
}

function tt_auth_current_url()
{
    $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'ops.traintote.com';
    $uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';
    return 'https://' . $host . $uri;
}

function tt_auth_login_url($redirect = null)
{
    if ($redirect === null || trim((string)$redirect) === '') {
        $redirect = tt_auth_current_url();
    }
    return 'https://ops.traintote.com/login.php?redirect=' . rawurlencode($redirect);
}

function tt_auth_set_cookie($user)
{
    if (!is_array($user) || !defined('TT_AUTH_SECRET') || TT_AUTH_SECRET === '') {
        return false;
    }

    $expires = time() + TT_AUTH_COOKIE_LIFETIME;
    $userId = isset($user['id']) ? $user['id'] : (isset($user['user_id']) ? $user['user_id'] : null);
    $payloadData = array(
        'user_id' => $userId,
        'id' => $userId,
        'email' => isset($user['email']) ? $user['email'] : '',
        'first_name' => isset($user['first_name']) ? $user['first_name'] : '',
        'expires' => $expires
    );
    $payload = tt_auth_base64url_encode(json_encode($payloadData));
    $signature = tt_auth_sign($payload);
    if ($signature === false) {
        return false;
    }

    $cookieValue = $payload . '.' . $signature;
    setcookie(TT_AUTH_COOKIE_NAME, $cookieValue, tt_auth_cookie_options($expires));
    $_COOKIE[TT_AUTH_COOKIE_NAME] = $cookieValue;
    return true;
}

function tt_auth_current_user()
{
    $cookieValue = isset($_COOKIE[TT_AUTH_COOKIE_NAME]) ? $_COOKIE[TT_AUTH_COOKIE_NAME] : '';
    if (!is_string($cookieValue) || $cookieValue === '') {
        return null;
    }

    $parts = explode('.', $cookieValue, 2);
    if (count($parts) !== 2) {
        return null;
    }

    $expectedSignature = tt_auth_sign($parts[0]);
    if ($expectedSignature === false || !hash_equals($expectedSignature, $parts[1])) {
        return null;
    }

    $json = tt_auth_base64url_decode($parts[0]);
    $data = $json === false ? null : json_decode($json, true);
    if (!is_array($data) || empty($data['expires']) || (int)$data['expires'] < time()) {
        return null;
    }
    return $data;
}

function tt_auth_get_user()
{
    return tt_auth_current_user();
}

function tt_auth_user_is_logged_in()
{
    return tt_auth_current_user() !== null;
}

function tt_auth_is_logged_in()
{
    return tt_auth_user_is_logged_in();
}

function tt_auth_require_login($redirectTo = null)
{
    if (tt_auth_user_is_logged_in()) {
        return true;
    }
    if ($redirectTo === null) {
        $redirectTo = tt_auth_login_url();
    }
    header('Location: ' . $redirectTo);
    exit;
}

function tt_auth_clear_cookie()
{
    setcookie(TT_AUTH_COOKIE_NAME, '', tt_auth_cookie_options(time() - 3600));
    unset($_COOKIE[TT_AUTH_COOKIE_NAME]);
    return true;
}

function ttAuthCookieName()
{
    return 'tt_ops_remember';
}

function ttAuthCookieLifetime()
{
    return 604800;
}

function ttAuthParseCookie($value)
{
    if (!is_string($value) || !preg_match('/\A([a-f0-9]{24})\.([a-f0-9]{64})\z/D', $value, $matches)) {
        return false;
    }

    return array(
        'selector' => $matches[1],
        'validator' => $matches[2]
    );
}

function ttAuthSetCookieHeader($value, $expiresAt)
{
    $maxAge = max(0, (int)$expiresAt - time());
    $header = ttAuthCookieName() . '=' . rawurlencode($value)
        . '; Expires=' . gmdate('D, d M Y H:i:s', (int)$expiresAt) . ' GMT'
        . '; Max-Age=' . $maxAge
        . '; Path=/'
        . '; Secure'
        . '; HttpOnly'
        . '; SameSite=Lax';

    header('Set-Cookie: ' . $header, false);
}

function ttAuthClearCookie()
{
    ttAuthSetCookieHeader('', time() - 3600);
    unset($_COOKIE[ttAuthCookieName()]);
}

function ttAuthPrepare($pdo, $sql)
{
    $stmt = $pdo->prepare($sql);
    if ($stmt === false) {
        throw new RuntimeException('Unable to prepare remember-login query.');
    }
    return $stmt;
}

function ttAuthExecute($stmt, $params = array())
{
    if (!$stmt->execute($params)) {
        throw new RuntimeException('Unable to execute remember-login query.');
    }
}

function ttAuthCreateToken($pdo, $userId)
{
    $selector = bin2hex(random_bytes(12));
    $validator = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $validator);
    $expiresAt = time() + ttAuthCookieLifetime();

    $stmt = ttAuthPrepare($pdo,
        'INSERT INTO auth_remember_tokens (user_id, selector, token_hash, expires_at) '
        . 'VALUES (:user_id, :selector, :token_hash, :expires_at)'
    );
    ttAuthExecute($stmt, array(
        'user_id' => (int)$userId,
        'selector' => $selector,
        'token_hash' => $tokenHash,
        'expires_at' => gmdate('Y-m-d H:i:s', $expiresAt)
    ));

    ttAuthSetCookieHeader($selector . '.' . $validator, $expiresAt);
}

function ttAuthRememberLogin($pdo, $userId)
{
    try {
        $stmt = ttAuthPrepare($pdo, 'DELETE FROM auth_remember_tokens WHERE expires_at <= UTC_TIMESTAMP()');
        ttAuthExecute($stmt);
        ttAuthCreateToken($pdo, $userId);
        return true;
    } catch (Exception $exception) {
        // Keep ordinary PHP-session login working if the migration is not yet
        // installed or the host temporarily rejects the token-table query.
        error_log('TrainTote remember-login unavailable: ' . $exception->getMessage());
        ttAuthClearCookie();
        return false;
    }
}

function ttAuthRestoreLogin($pdo)
{
    if (!empty($_SESSION['user_id'])) {
        return true;
    }

    $parsed = ttAuthParseCookie(isset($_COOKIE[ttAuthCookieName()]) ? $_COOKIE[ttAuthCookieName()] : null);
    if ($parsed === false) {
        if (isset($_COOKIE[ttAuthCookieName()])) {
            ttAuthClearCookie();
        }
        return false;
    }

    try {
        $stmt = ttAuthPrepare($pdo,
            'SELECT u.*, t.token_hash '
            . 'FROM auth_remember_tokens t '
            . 'JOIN users u ON u.id = t.user_id '
            . 'WHERE t.selector = :selector AND t.expires_at > UTC_TIMESTAMP() '
            . 'LIMIT 1'
        );
        ttAuthExecute($stmt, array('selector' => $parsed['selector']));
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !hash_equals($user['token_hash'], hash('sha256', $parsed['validator']))) {
            ttAuthClearCookie();
            return false;
        }

        $stmt = ttAuthPrepare($pdo, 'DELETE FROM auth_remember_tokens WHERE selector = :selector');
        ttAuthExecute($stmt, array('selector' => $parsed['selector']));

        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['first_name'] = isset($user['first_name']) ? $user['first_name'] : '';

        // Rotate the validator after every use so a captured cookie cannot be
        // replayed after the legitimate browser restores its session.
        ttAuthCreateToken($pdo, (int)$user['id']);
        return true;
    } catch (Exception $exception) {
        error_log('TrainTote remember-login restore unavailable: ' . $exception->getMessage());
        ttAuthClearCookie();
        return false;
    }
}

function ttAuthForgetLogin($pdo)
{
    $parsed = ttAuthParseCookie(isset($_COOKIE[ttAuthCookieName()]) ? $_COOKIE[ttAuthCookieName()] : null);

    if ($parsed !== false) {
        try {
            $stmt = ttAuthPrepare($pdo, 'DELETE FROM auth_remember_tokens WHERE selector = :selector');
            ttAuthExecute($stmt, array('selector' => $parsed['selector']));
        } catch (Exception $exception) {
            error_log('TrainTote remember-login logout cleanup unavailable: ' . $exception->getMessage());
        }
    }

    ttAuthClearCookie();
}
