<?php
require_once dirname(__DIR__) . '/includes/registration.php';

function registrationExpect($condition, $message)
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

class RegistrationDatabase extends PDO
{
    public $users = [];
    public $railroads = [];
    public $failure = '';
    public $transaction = false;
    public $rolledBack = false;
    public $mode = PDO::ERRMODE_SILENT;
    public function __construct() {}
    public function getAttribute(int $attribute): mixed { return $this->mode; }
    public function setAttribute(int $attribute, mixed $value): bool { $this->mode = $value; return true; }
    public function beginTransaction(): bool { $this->transaction = true; return true; }
    public function inTransaction(): bool { return $this->transaction; }
    public function commit(): bool { $this->transaction = false; return true; }
    public function rollBack(): bool
    {
        $this->users = $this->railroads = [];
        $this->transaction = false;
        $this->rolledBack = true;
        return true;
    }
    public function lastInsertId(?string $name = null): string|false { return '42'; }
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        return new RegistrationStatement($this, $query);
    }
}

class RegistrationStatement extends PDOStatement
{
    private $db;
    private $query;
    public function __construct($db, $query) { $this->db = $db; $this->query = $query; }
    public function execute(?array $params = null): bool
    {
        if (strpos($this->query, 'INSERT INTO') === false) { return true; }
        registrationExpect($this->db->transaction, 'Every insert must be inside the transaction.');
        $userInsert = strpos($this->query, 'INSERT INTO users') !== false;
        if (($userInsert && in_array($this->db->failure, ['user', 'duplicate'], true))
            || (!$userInsert && $this->db->failure === 'railroad')) {
            $exception = new PDOException('Private database details');
            $exception->errorInfo = ['23000', $this->db->failure === 'duplicate' ? 1062 : 1048];
            throw $exception;
        }
        if ($userInsert) { $this->db->users[] = $params; }
        else { $this->db->railroads[] = $params; }
        return true;
    }
    public function fetchColumn(int $column = 0): mixed { return $this->db->users ? 42 : false; }
}

$_SESSION = [];
$token = ttRegistrationCsrfToken();
registrationExpect(strlen($token) === 48 && $token === ttRegistrationCsrfToken(), 'CSRF token must be random and stable within the session.');
$input = [
    'first_name' => ' Casey ', 'last_name' => ' Jones ', 'email' => ' casey@example.com ',
    'railroad_name' => ' Test Railroad ', 'password' => 'train password',
    'confirm_password' => 'train password', 'csrf_token' => $token,
];
foreach ([
    ['csrf_token', ''], ['csrf_token', 'wrong'], ['csrf_token', []],
    ['first_name', ' '], ['last_name', []], ['railroad_name', ''],
    ['email', 'invalid'], ['email', ''], ['password', 'short'],
    ['password', str_repeat('a', 73)], ['password', "password\0"],
    ['confirm_password', 'mismatch'], ['confirm_password', []],
] as [$field, $value]) {
    $db = new RegistrationDatabase();
    $invalid = array_replace($input, [$field => $value]);
    registrationExpect(ttRegisterOwner($db, $invalid, $token) !== '', 'Invalid ' . $field . ' must be rejected.');
    registrationExpect(!$db->users && !$db->railroads && !$db->transaction, 'Validation must not write data.');
}
$db = new RegistrationDatabase();
registrationExpect(ttRegisterOwner($db, $input, $token) === '', 'Valid signup must succeed.');
registrationExpect(count($db->users) === 1 && count($db->railroads) === 1 && !$db->transaction, 'Both records must commit.');
registrationExpect($db->railroads[0]['user_id'] === '42', 'The new user must own the railroad.');
registrationExpect($db->railroads[0]['name'] === 'Test Railroad' && $db->users[0]['email'] === 'casey@example.com', 'Fields must be trimmed.');
registrationExpect(password_verify($input['password'], $db->users[0]['password_hash']), 'Password must work with existing login verification.');
registrationExpect($db->mode === PDO::ERRMODE_SILENT, 'Restore the configured PDO error mode.');
registrationExpect(strpos(ttRegisterOwner($db, $input, $token), 'already exists') !== false, 'Existing email must get a friendly message.');
registrationExpect(count($db->users) === 1 && count($db->railroads) === 1, 'Duplicate email must not insert records.');
foreach (['user', 'railroad', 'duplicate'] as $failure) {
    $db = new RegistrationDatabase();
    $db->failure = $failure;
    $message = ttRegisterOwner($db, $input, $token);
    registrationExpect($message !== '' && strpos($message, 'Private database') === false, 'Failure must not expose database details.');
    registrationExpect($db->rolledBack && !$db->users && !$db->railroads, 'Either insert failure must roll back everything.');
    registrationExpect(($failure === 'duplicate') === (strpos($message, 'already exists') !== false), 'Only a duplicate user should be reported as duplicate email.');
}
echo "Registration tests passed.\n";
