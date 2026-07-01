<?php

session_start();

require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$stmt = $pdo->prepare("
    SELECT id
    FROM railroads
    WHERE user_id = :user_id
    LIMIT 1
");

$stmt->execute([
    'user_id' => $_SESSION['user_id']
]);

$railroad = $stmt->fetch(PDO::FETCH_ASSOC);

$jobs = [];

if ($railroad) {

    $stmt = $pdo->prepare("
        SELECT
            j.*,
            i.industry_name AS home_location
        FROM jobs j
        LEFT JOIN industries i
            ON j.home_industry_id = i.id
        WHERE j.railroad_id = :railroad_id
        AND j.active = 1
        ORDER BY j.job_name
    ");

    $stmt->execute([
        'railroad_id' => $railroad['id']
    ]);

    $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

?>

<?php
$pageTitle = 'Select Operating Job';
include '../assets/components/header.php';
include '../assets/components/sidebar.php';
?>
<link rel="stylesheet" href="../assets/css/dashboard.css">

<div class="tt-job-workflow-page">
    <div class="tt-session-hero tt-job-hero">
        <div>
            <span class="tt-session-kicker">Operations Job Workflow</span>
            <h1>Select Operating Job</h1>
            <p>Use saved job templates to generate switch lists for assigned industries, locomotives, and cars.</p>
        </div>

        <div class="tt-session-hero-actions">
            <a class="tt-session-link" href="/operations/generate.php">Start Session</a>
            <a class="tt-session-link" href="/jobs/list.php">Job Templates</a>
        </div>
    </div>

    <div class="tt-session-workflow" aria-label="Switch list workflow">
        <div class="tt-session-step">
            <span>1</span>
            <strong>Session Options</strong>
        </div>
        <div class="tt-session-step is-current">
            <span>2</span>
            <strong>Select Job</strong>
        </div>
        <div class="tt-session-step">
            <span>3</span>
            <strong>Review Work</strong>
        </div>
        <div class="tt-session-step">
            <span>4</span>
            <strong>Generate Switch List</strong>
        </div>
    </div>

    <section class="tt-panel tt-job-picker-panel">
        <div class="tt-panel-heading">
            <div>
                <span class="tt-panel-kicker">Available Jobs</span>
                <h2>Choose a Job Template</h2>
            </div>
            <span class="tt-status-pill tt-status-ready"><?php echo count($jobs); ?> Active</span>
        </div>

        <?php if (count($jobs) == 0): ?>
        <div class="alert alert-warning tt-session-alert">
            <strong>No active jobs found.</strong>
            <span>Create a job template before generating a job-based switch list.</span>
            <a href="/jobs/add.php" class="btn btn-warning btn-sm mt-2">Create Your First Job</a>
        </div>
        <?php else: ?>
        <div class="tt-job-card-grid">
            <?php foreach ($jobs as $job): ?>
            <article class="tt-job-card">
                <div class="tt-job-card-header">
                    <div>
                        <span class="tt-panel-kicker">Job Template</span>
                        <h3><?php echo htmlspecialchars($job['job_name']); ?></h3>
                    </div>
                    <span class="tt-job-status <?php echo (int)($job['active'] ?? 0) === 1 ? 'is-active' : ''; ?>">
                        <?php echo (int)($job['active'] ?? 0) === 1 ? 'Active' : 'Inactive'; ?>
                    </span>
                </div>

                <dl class="tt-job-meta">
                    <div>
                        <dt>Type</dt>
                        <dd><?php echo htmlspecialchars($job['job_type'] ?: '-'); ?></dd>
                    </div>
                    <div>
                        <dt>Home Location</dt>
                        <dd><?php echo htmlspecialchars($job['home_location'] ?: '-'); ?></dd>
                    </div>
                </dl>

                <form method="get" action="switch_list.php" class="tt-job-card-action">
                    <input type="hidden" name="job_id" value="<?php echo (int)$job['id']; ?>">
                    <button type="submit" class="btn btn-success">Generate Switch List</button>
                </form>
            </article>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </section>
</div>

<?php include '../assets/components/footer.php'; ?>
