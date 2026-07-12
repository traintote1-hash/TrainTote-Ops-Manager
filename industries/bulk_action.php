<?php

session_start();

require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

function industryBulkListUrl(string $message = '', int $count = 0): string
{
    $returnUrl = trim($_POST['return_url'] ?? '');

    if ($returnUrl === '') {
        $returnUrl = 'list.php';
    }

    $parts = parse_url($returnUrl);

    if ($parts === false || isset($parts['scheme']) || isset($parts['host'])) {
        $parts = ['path' => 'list.php'];
    }

    $path = $parts['path'] ?? 'list.php';

    if ($path === '' || basename($path) !== 'list.php') {
        $parts = ['path' => 'list.php'];
    }

    $query = [];

    if (!empty($parts['query'])) {
        parse_str($parts['query'], $query);
    }

    unset($query['bulk_message'], $query['bulk_count']);

    if ($message !== '') {
        $query['bulk_message'] = $message;
    }

    if ($count > 0) {
        $query['bulk_count'] = $count;
    }

    return 'list.php' . (!empty($query) ? '?' . http_build_query($query) : '');
}

function industryBulkRedirect(string $message = '', int $count = 0): void
{
    header('Location: ' . industryBulkListUrl($message, $count));
    exit;
}

$action = $_POST['bulk_action'] ?? '';
$validActions = ['bulk_edit', 'set_active', 'set_inactive', 'delete_selected'];

if (!in_array($action, $validActions, true)) {
    industryBulkRedirect('invalid_action');
}

$selectedIds = array_values(array_unique(array_filter(
    array_map('intval', (array)($_POST['industry_ids'] ?? [])),
    static fn(int $industryId): bool => $industryId > 0
)));

if (empty($selectedIds)) {
    industryBulkRedirect('none_selected');
}

$railroadStmt = $pdo->prepare("
    SELECT id
    FROM railroads
    WHERE user_id = ?
    LIMIT 1
");
$railroadStmt->execute([$_SESSION['user_id']]);
$railroad = $railroadStmt->fetch(PDO::FETCH_ASSOC);

if (!$railroad) {
    die('No railroad found.');
}

$placeholders = implode(',', array_fill(0, count($selectedIds), '?'));
$authorizedStmt = $pdo->prepare("
    SELECT id
    FROM industries
    WHERE railroad_id = ?
    AND id IN ($placeholders)
");
$authorizedStmt->execute(array_merge([$railroad['id']], $selectedIds));
$authorizedSet = array_fill_keys(
    array_map('intval', $authorizedStmt->fetchAll(PDO::FETCH_COLUMN)),
    true
);
$authorizedIds = array_values(array_filter(
    $selectedIds,
    static fn(int $industryId): bool => isset($authorizedSet[$industryId])
));

if (empty($authorizedIds)) {
    industryBulkRedirect('none_authorized');
}

if ($action === 'bulk_edit') {
    $_SESSION['industry_edit_queue'] = $authorizedIds;
    $_SESSION['industry_edit_queue_return_url'] = industryBulkListUrl();

    header('Location: edit.php?id=' . (int)$authorizedIds[0]);
    exit;
}

if ($action === 'set_active' || $action === 'set_inactive') {
    $active = $action === 'set_active' ? 1 : 0;
    $authorizedPlaceholders = implode(',', array_fill(0, count($authorizedIds), '?'));
    $updateStmt = $pdo->prepare("
        UPDATE industries
        SET active = ?
        WHERE railroad_id = ?
        AND id IN ($authorizedPlaceholders)
    ");
    $updateStmt->execute(array_merge([$active, $railroad['id']], $authorizedIds));

    industryBulkRedirect($action === 'set_active' ? 'active_set' : 'inactive_set', count($authorizedIds));
}

$_SESSION['industry_bulk_delete_ids'] = $authorizedIds;
$_SESSION['industry_bulk_delete_return_url'] = industryBulkListUrl();

header('Location: bulk_delete.php');
exit;
