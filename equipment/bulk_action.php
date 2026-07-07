<?php

session_start();

require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

function tt_bulk_list_url($message = '')
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
        $path = 'list.php';
        $parts = ['path' => 'list.php'];
    }

    $query = [];

    if (!empty($parts['query'])) {
        parse_str($parts['query'], $query);
    }

    unset($query['bulk_message']);

    if ($message !== '') {
        $query['bulk_message'] = $message;
    }

    return 'list.php' . (!empty($query) ? '?' . http_build_query($query) : '');
}

function tt_bulk_redirect($message = '')
{
    header('Location: ' . tt_bulk_list_url($message));
    exit;
}

function tt_bulk_selected_ids()
{
    return array_values(array_unique(array_filter(array_map(
        'intval',
        (array)($_POST['equipment_ids'] ?? [])
    ))));
}

function tt_bulk_current_railroad($pdo)
{
    $stmt = $pdo->prepare("
        SELECT id
        FROM railroads
        WHERE user_id = ?
        LIMIT 1
    ");

    $stmt->execute([$_SESSION['user_id']]);
    $railroad = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$railroad) {
        die('No railroad found.');
    }

    return $railroad;
}

function tt_bulk_authorized_ids($pdo, $railroadId, $ids)
{
    if (empty($ids)) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("
        SELECT id
        FROM equipment
        WHERE railroad_id = ?
        AND id IN ($placeholders)
        ORDER BY reporting_marks, road_number
    ");

    $stmt->execute(array_merge([$railroadId], $ids));

    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

$action = $_POST['bulk_action'] ?? '';
$validActions = [
    'print_cards',
    'edit_queue',
    'set_active',
    'set_inactive',
    'delete_selected'
];

if (!in_array($action, $validActions, true)) {
    tt_bulk_redirect('invalid_action');
}

$ids = tt_bulk_selected_ids();

if (empty($ids)) {
    tt_bulk_redirect('none_selected');
}

$railroad = tt_bulk_current_railroad($pdo);
$authorizedIds = tt_bulk_authorized_ids($pdo, $railroad['id'], $ids);

if (empty($authorizedIds)) {
    tt_bulk_redirect('none_authorized');
}

if ($action === 'print_cards') {
    ?>
    <!doctype html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <title>Forwarding...</title>
    </head>
    <body>
        <form id="bulkForwardForm" method="post" action="print_cards_svg.php">
            <?php foreach ($authorizedIds as $equipmentId): ?>
                <input type="hidden" name="equipment_ids[]" value="<?= (int)$equipmentId ?>">
            <?php endforeach; ?>
            <input type="hidden" name="return_url" value="<?= htmlspecialchars(tt_bulk_list_url(), ENT_QUOTES, 'UTF-8') ?>">
        </form>
        <script>
        document.getElementById('bulkForwardForm').submit();
        </script>
    </body>
    </html>
    <?php
    exit;
}

if ($action === 'edit_queue') {
    $_SESSION['equipment_edit_queue'] = $authorizedIds;
    $_SESSION['equipment_edit_queue_return_url'] = tt_bulk_list_url();
    $_SESSION['bulk_edit_queue'] = $authorizedIds;
    $_SESSION['bulk_edit_queue_return_url'] = tt_bulk_list_url();
    $_SESSION['edit_queue'] = $authorizedIds;
    $_SESSION['edit_queue_return_url'] = tt_bulk_list_url();

    header('Location: edit.php?id=' . (int)$authorizedIds[0]);
    exit;
}

if ($action === 'set_active' || $action === 'set_inactive') {
    $active = $action === 'set_active' ? 1 : 0;
    $placeholders = implode(',', array_fill(0, count($authorizedIds), '?'));
    $stmt = $pdo->prepare("
        UPDATE equipment
        SET active = ?
        WHERE railroad_id = ?
        AND id IN ($placeholders)
    ");

    $stmt->execute(array_merge([$active, $railroad['id']], $authorizedIds));

    tt_bulk_redirect($action === 'set_active' ? 'active_set' : 'inactive_set');
}

if ($action === 'delete_selected') {
    ?>
    <!doctype html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <title>Forwarding...</title>
    </head>
    <body>
        <form id="bulkForwardForm" method="post" action="bulk_delete.php">
            <?php foreach ($authorizedIds as $equipmentId): ?>
                <input type="hidden" name="equipment_ids[]" value="<?= (int)$equipmentId ?>">
            <?php endforeach; ?>
            <input type="hidden" name="return_url" value="<?= htmlspecialchars(tt_bulk_list_url(), ENT_QUOTES, 'UTF-8') ?>">
        </form>
        <script>
        document.getElementById('bulkForwardForm').submit();
        </script>
    </body>
    </html>
    <?php
    exit;
}

tt_bulk_redirect('invalid_action');
