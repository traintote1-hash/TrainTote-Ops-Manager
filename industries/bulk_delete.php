<?php

session_start();

require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

function industryDeleteListUrl(string $returnUrl, string $message = '', int $count = 0): string
{
    $parts = parse_url(trim($returnUrl));

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

$returnUrl = industryDeleteListUrl(
    (string)($_SESSION['industry_bulk_delete_return_url'] ?? 'list.php')
);

if (isset($_GET['cancel'])) {
    unset($_SESSION['industry_bulk_delete_ids'], $_SESSION['industry_bulk_delete_return_url']);
    header('Location: ' . $returnUrl);
    exit;
}

$selectedIds = array_values(array_unique(array_filter(
    array_map('intval', (array)($_SESSION['industry_bulk_delete_ids'] ?? [])),
    static fn(int $industryId): bool => $industryId > 0
)));

if (empty($selectedIds)) {
    header('Location: ' . industryDeleteListUrl($returnUrl, 'none_selected'));
    exit;
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
$industryStmt = $pdo->prepare("
    SELECT id, industry_name, industry_type, location, active, photo_filename
    FROM industries
    WHERE railroad_id = ?
    AND id IN ($placeholders)
");
$industryStmt->execute(array_merge([$railroad['id']], $selectedIds));
$industryRowsById = [];

foreach ($industryStmt->fetchAll(PDO::FETCH_ASSOC) as $industryRow) {
    $industryRowsById[(int)$industryRow['id']] = $industryRow;
}

$industries = [];

foreach ($selectedIds as $selectedId) {
    if (isset($industryRowsById[$selectedId])) {
        $industries[] = $industryRowsById[$selectedId];
    }
}

if (empty($industries)) {
    unset($_SESSION['industry_bulk_delete_ids'], $_SESSION['industry_bulk_delete_return_url']);
    header('Location: ' . industryDeleteListUrl($returnUrl, 'none_authorized'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $authorizedIds = array_map(
        static fn(array $industryRow): int => (int)$industryRow['id'],
        $industries
    );
    $photoFilenames = array_values(array_filter(array_map(
        static fn(array $industryRow): string => trim((string)($industryRow['photo_filename'] ?? '')),
        $industries
    )));
    $deletePlaceholders = implode(',', array_fill(0, count($authorizedIds), '?'));

    try {
        $pdo->beginTransaction();

        $deleteStmt = $pdo->prepare("
            DELETE FROM industries
            WHERE railroad_id = ?
            AND id IN ($deletePlaceholders)
        ");
        $deleteStmt->execute(array_merge([$railroad['id']], $authorizedIds));
        $deletedCount = $deleteStmt->rowCount();

        if ($deletedCount !== count($authorizedIds)) {
            throw new RuntimeException('Not all selected industries could be deleted.');
        }

        $pdo->commit();

        $uploadDirectory = dirname(__DIR__) . '/uploads';

        foreach ($photoFilenames as $photoFilename) {
            if (basename($photoFilename) !== $photoFilename) {
                continue;
            }

            $photoPath = $uploadDirectory . '/' . $photoFilename;

            if (is_file($photoPath)) {
                unlink($photoPath);
            }
        }

        unset($_SESSION['industry_bulk_delete_ids'], $_SESSION['industry_bulk_delete_return_url']);
        header('Location: ' . industryDeleteListUrl($returnUrl, 'industries_deleted', $deletedCount));
        exit;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        unset($_SESSION['industry_bulk_delete_ids'], $_SESSION['industry_bulk_delete_return_url']);
        header('Location: ' . industryDeleteListUrl($returnUrl, 'delete_failed'));
        exit;
    }
}

?>

<?php include '../includes/header.php'; ?>

<title>Delete Selected Industries</title>

</head>

<body>

<?php include '../includes/navbar.php'; ?>

<div class="container mt-5">

<div class="alert alert-danger">

<h3>Delete Selected Industries</h3>

<p>This action cannot be undone. Only the selected industries listed below will be deleted.</p>

<div class="table-responsive bg-white text-dark rounded mb-3">
<table class="table table-striped mb-0">
<thead>
<tr>
<th>Industry</th>
<th>Type</th>
<th>Location</th>
<th>Status</th>
</tr>
</thead>
<tbody>
<?php foreach ($industries as $industry): ?>
<tr>
<td><?= htmlspecialchars($industry['industry_name']) ?></td>
<td><?= htmlspecialchars($industry['industry_type']) ?></td>
<td><?= htmlspecialchars($industry['location']) ?></td>
<td>
<?php if ((int)($industry['active'] ?? 1) === 1): ?>
<span class="badge bg-success">Active</span>
<?php else: ?>
<span class="badge bg-secondary">Inactive</span>
<?php endif; ?>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>

<form method="post">
<button type="submit" class="btn btn-danger me-2">Delete Selected Industries</button>
<a href="bulk_delete.php?cancel=1" class="btn btn-secondary">Cancel</a>
</form>

</div>

</div>

<?php include '../includes/footer.php'; ?>
