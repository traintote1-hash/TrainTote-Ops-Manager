<?php

session_start();

require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

if (!isset($_GET['id'])) {
    die('Job ID missing.');
}

$id = (int)$_GET['id'];

$stmt = $pdo->prepare("
    SELECT r.id
    FROM railroads r
    WHERE r.user_id = :user_id
    LIMIT 1
");

$stmt->execute([
    'user_id' => $_SESSION['user_id']
]);

$railroad = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$railroad) {
    die('Railroad not found.');
}

$stmt = $pdo->prepare("
    SELECT id
    FROM jobs
    WHERE id = :id
    AND railroad_id = :railroad_id
    LIMIT 1
");

$stmt->execute([
    'id' => $id,
    'railroad_id' => $railroad['id']
]);

$job = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$job) {
    die('Job not found.');
}

$stmt = $pdo->prepare("
    DELETE FROM job_locomotives
    WHERE job_id = :job_id
");

$stmt->execute([
    'job_id' => $id
]);

$stmt = $pdo->prepare("
    DELETE FROM jobs
    WHERE id = :id
");

$stmt->execute([
    'id' => $id
]);

header('Location: list.php');
exit;