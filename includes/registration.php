<?php

function ttRegistrationValue(array $input, string $key): string
{
    return isset($input[$key]) && is_string($input[$key]) ? $input[$key] : '';
}

// Match Operations' random session token/hash_equals convention without loading
// the Operations module into the public signup page.
function ttRegistrationCsrfToken(): string
{
    if (empty($_SESSION['registration_csrf']) || !is_string($_SESSION['registration_csrf'])) {
        $_SESSION['registration_csrf'] = bin2hex(random_bytes(24));
    }
    return $_SESSION['registration_csrf'];
}

function ttRegisterOwner(PDO $pdo, array $input, string $csrfToken): string
{
    $submitted = ttRegistrationValue($input, 'csrf_token');
    if ($csrfToken === '' || $submitted === '' || !hash_equals($csrfToken, $submitted)) {
        return 'The form expired. Please try again.';
    }

    $values = [];
    foreach (['first_name', 'last_name', 'email', 'railroad_name'] as $field) {
        $values[$field] = trim(ttRegistrationValue($input, $field));
        if ($values[$field] === '') {
            return 'Please complete all required fields.';
        }
    }
    if (!filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
        return 'Please enter a valid email address.';
    }
    $password = ttRegistrationValue($input, 'password');
    if (strlen($password) < 8) {
        return 'Please use a password with at least 8 characters.';
    }
    // PASSWORD_DEFAULT currently uses bcrypt, which truncates after 72 bytes.
    if (strlen($password) > 72 || strpos($password, "\0") !== false) {
        return 'This password is too long or contains an unsupported character. Please choose another password.';
    }
    if ($password !== ttRegistrationValue($input, 'confirm_password')) {
        return 'The passwords do not match.';
    }

    $duplicateMessage = 'An account with this email already exists. Please log in.';
    $previousMode = $pdo->getAttribute(PDO::ATTR_ERRMODE);
    $insertingUser = false;
    try {
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $values['email']]);
        if ($stmt->fetchColumn() !== false) {
            return $duplicateMessage;
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $pdo->beginTransaction();
        $insertingUser = true;
        $stmt = $pdo->prepare('INSERT INTO users (first_name, last_name, email, password_hash)
            VALUES (:first_name, :last_name, :email, :password_hash)');
        $stmt->execute([
            'first_name' => $values['first_name'], 'last_name' => $values['last_name'],
            'email' => $values['email'], 'password_hash' => $hash,
        ]);
        $userId = $pdo->lastInsertId();
        $insertingUser = false;
        // Use the same columns and empty optional details as railroad/create.php.
        $stmt = $pdo->prepare('INSERT INTO railroads (user_id, name, era, region, operating_style, description)
            VALUES (:user_id, :name, :era, :region, :operating_style, :description)');
        $stmt->execute([
            'user_id' => $userId, 'name' => $values['railroad_name'],
            'era' => '', 'region' => '', 'operating_style' => '', 'description' => '',
        ]);
        $pdo->commit();
        return '';
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        // MySQL duplicate key during the user insert also covers a concurrent
        // registration that passed the email lookup before this request.
        if ($insertingUser && $exception instanceof PDOException
            && (int)($exception->errorInfo[1] ?? 0) === 1062) {
            return $duplicateMessage;
        }
        error_log('TrainTote registration failed: ' . $exception->getMessage());
        return 'Unable to create your account right now. Please try again later.';
    } finally {
        $pdo->setAttribute(PDO::ATTR_ERRMODE, $previousMode);
    }
}
