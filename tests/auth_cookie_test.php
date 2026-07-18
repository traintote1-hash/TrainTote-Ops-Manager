<?php

require_once dirname(__DIR__) . '/includes/tt_auth_cookie.php';

function authCookieExpect($condition, $message)
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$valid = str_repeat('a', 24) . '.' . str_repeat('b', 64);
$parsed = ttAuthParseCookie($valid);
authCookieExpect($parsed !== false, 'A correctly shaped remember cookie must parse.');
authCookieExpect($parsed['selector'] === str_repeat('a', 24), 'The selector must be preserved.');
authCookieExpect($parsed['validator'] === str_repeat('b', 64), 'The validator must be preserved.');

foreach (array(
    null,
    '',
    'not-a-token',
    str_repeat('a', 23) . '.' . str_repeat('b', 64),
    str_repeat('a', 24) . '.' . str_repeat('b', 63),
    str_repeat('A', 24) . '.' . str_repeat('b', 64),
    $valid . '.extra'
) as $invalid) {
    authCookieExpect(ttAuthParseCookie($invalid) === false, 'Malformed remember cookies must be rejected.');
}

class AuthCookieFakeStatement
{
    private $database;
    private $sql;

    public function __construct($database, $sql)
    {
        $this->database = $database;
        $this->sql = $sql;
    }

    public function execute($params = array())
    {
        if (strpos($this->sql, 'INSERT INTO auth_remember_tokens') !== false) {
            $this->database->inserted[] = $params;
        } elseif (strpos($this->sql, 'DELETE FROM auth_remember_tokens') !== false) {
            $this->database->deleted[] = $params;
        }
        return true;
    }

    public function fetch($mode = null)
    {
        return $this->database->row;
    }
}

class AuthCookieFakeDatabase
{
    public $row;
    public $inserted = array();
    public $deleted = array();

    public function prepare($sql)
    {
        return new AuthCookieFakeStatement($this, $sql);
    }
}

class AuthCookieSilentFailureDatabase
{
    public function prepare($sql)
    {
        return false;
    }
}

session_start();
$_SESSION = array();
$_COOKIE[ttAuthCookieName()] = $valid;

$database = new AuthCookieFakeDatabase();
$database->row = array(
    'id' => 42,
    'first_name' => 'Casey',
    'token_hash' => hash('sha256', str_repeat('b', 64))
);

authCookieExpect(ttAuthRestoreLogin($database), 'A valid remember token must restore the login.');
authCookieExpect($_SESSION['user_id'] === 42, 'Restore must repopulate the user id.');
authCookieExpect($_SESSION['first_name'] === 'Casey', 'Restore must repopulate the display name.');
authCookieExpect(count($database->deleted) === 1, 'Restore must revoke the used selector.');
authCookieExpect(count($database->inserted) === 1, 'Restore must rotate to a new token.');
authCookieExpect($database->inserted[0]['selector'] !== str_repeat('a', 24), 'Rotation must use a new selector.');

$_SESSION = array();
$_COOKIE[ttAuthCookieName()] = str_repeat('a', 24) . '.' . str_repeat('c', 64);
$database->inserted = array();
$database->deleted = array();
authCookieExpect(!ttAuthRestoreLogin($database), 'A wrong validator must not restore the login.');
authCookieExpect(empty($_SESSION['user_id']), 'A rejected token must leave the session unauthenticated.');
authCookieExpect(count($database->inserted) === 0, 'A rejected token must not rotate into a valid token.');

authCookieExpect(
    !ttAuthRememberLogin(new AuthCookieSilentFailureDatabase(), 42),
    'A missing token table in PDO silent mode must not break ordinary login.'
);

$project = dirname(__DIR__);
$login = file_get_contents($project . '/login.php');
$logout = file_get_contents($project . '/logout.php');
$migration = file_get_contents($project . '/database/migrations/20260718_add_auth_remember_tokens.sql');

authCookieExpect(strpos($login, 'ttAuthRestoreLogin') !== false, 'Login must restore a missing PHP session.');
authCookieExpect(strpos($login, 'ttAuthRememberLogin') !== false, 'Password login must issue a remember token.');
authCookieExpect(strpos($login, 'password_verify') !== false, 'Existing password verification must remain intact.');
authCookieExpect(strpos($logout, 'ttAuthForgetLogin') !== false, 'Logout must revoke the remember token.');
authCookieExpect(strpos($migration, 'UNIQUE KEY uq_auth_remember_selector') !== false, 'Selectors must be unique.');
authCookieExpect(strpos($migration, 'ON DELETE CASCADE') !== false, 'Deleting a user must delete its tokens.');

echo "auth_cookie_test: OK\n";
