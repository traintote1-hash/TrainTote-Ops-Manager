<?php

// A PHP session cookie only identifies server-side session data. This separate,
// rotating token can rebuild the small login session if shared-hosting cleanup
// removes that data before the advertised seven-day lifetime.

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
