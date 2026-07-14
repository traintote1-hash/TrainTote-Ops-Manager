<?php
session_start();
require_once '../config/database.php';
require_once '../operations/lib.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$railroad = ttOperationsRailroad($pdo, (int)$_SESSION['user_id']);
$railroadId = (int)$railroad['id'];
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$stmt = $pdo->prepare('SELECT id,job_name FROM jobs WHERE id=? AND railroad_id=?');
$stmt->execute([$id, $railroadId]);
$job = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$job) {
    http_response_code(404);
    die('Job Title not found.');
}

function deleteJobTableExists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?');
    $stmt->execute([$table]);
    return (int)$stmt->fetchColumn() > 0;
}

function jobReferenceCount(PDO $pdo, string $table, string $column, int $jobId): int
{
    $allowed = ['operation_assignments.job_template_id','prepared_cuts.intended_job_template_id','job_route_stops.job_id','job_industries.job_id','job_cars.job_id','job_locomotives.job_id'];
    if (!in_array($table . '.' . $column, $allowed, true) || !deleteJobTableExists($pdo, $table)) {
        return 0;
    }
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM $table WHERE $column=?");
    $stmt->execute([$jobId]);
    return (int)$stmt->fetchColumn();
}

$references = [
    'saved operating assignments or history' => jobReferenceCount($pdo, 'operation_assignments', 'job_template_id', $id),
    'prepared cuts' => jobReferenceCount($pdo, 'prepared_cuts', 'intended_job_template_id', $id),
    'configured Operating Areas' => jobReferenceCount($pdo, 'job_route_stops', 'job_id', $id),
    'legacy associated locations' => jobReferenceCount($pdo, 'job_industries', 'job_id', $id),
    'legacy associated cars' => jobReferenceCount($pdo, 'job_cars', 'job_id', $id),
    'legacy associated locomotives' => jobReferenceCount($pdo, 'job_locomotives', 'job_id', $id)
];
$blocking = array_filter($references, static fn($count) => $count > 0);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        ttOperationsRequireCsrf();
        if ($blocking) {
            throw new RuntimeException('This Job Title is still in use and cannot be deleted.');
        }
        $pdo->beginTransaction();
        if (deleteJobTableExists($pdo, 'job_operation_profiles')) {
            $pdo->prepare('DELETE FROM job_operation_profiles WHERE job_id=? AND railroad_id=?')->execute([$id, $railroadId]);
        }
        $deleteStmt = $pdo->prepare('DELETE FROM jobs WHERE id=? AND railroad_id=?');
        $deleteStmt->execute([$id, $railroadId]);
        if (!$deleteStmt->rowCount()) {
            throw new RuntimeException('Job Title not found.');
        }
        $pdo->commit();
        $_SESSION['job_title_message'] = 'Job Title “' . $job['job_name'] . '” deleted.';
        header('Location: list.php');
        exit;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = $e->getMessage();
    }
}
?>
<?php include '../includes/header.php'; ?>
<title>Delete Job Title</title>
</head><body>
<?php include '../includes/navbar.php'; ?>
<div class="tt-operations-shell">
<?php include '../assets/components/sidebar.php'; ?>
<section class="tt-ops-page">
    <p class="tt-eyebrow">Operations · Job Titles</p><h1>Delete “<?= ttHtml($job['job_name']) ?>”?</h1>
    <?php if ($error): ?><div class="alert alert-danger"><?= ttHtml($error) ?></div><?php endif; ?>
    <?php if ($blocking): ?><div class="alert alert-warning"><strong>This Job Title cannot be deleted because it is currently used by:</strong><ul class="mb-0 mt-2"><?php foreach ($blocking as $label => $count): ?><li><?= (int)$count ?> <?= ttHtml($label) ?></li><?php endforeach; ?></ul><p class="mb-0 mt-2">No assignments, switch lists, prepared cuts, legacy associations, or completed history will be removed.</p></div><?php else: ?><div class="alert alert-danger">This permanently deletes only the unused Job Title template. It will not delete operating records or equipment data.</div><?php endif; ?>
    <form method="post"><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="csrf_token" value="<?= ttHtml(ttOperationsCsrfToken()) ?>"><?php if (!$blocking): ?><button class="btn btn-danger">Delete Job Title</button><?php endif; ?> <a class="btn btn-secondary" href="list.php">Cancel</a></form>
</section></div>
<?php include '../includes/footer.php'; ?>
