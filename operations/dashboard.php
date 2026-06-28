<?php
$pageTitle='Operations Center';
include '../assets/components/header.php';
include '../assets/components/sidebar.php';
?>
<link rel="stylesheet" href="../assets/css/dashboard.css">

<div class="tt-hero">
    <div class="tt-hero-main">
        <div class="tt-hero-icon">🚂</div>
        <div>
            <h1>Operations Center</h1>
            <p>Arkansas &amp; Missouri Railroad</p>
        </div>
    </div>
</div>

<div class="tt-status">
    <div class="tt-panel tt-session-panel">
        <div class="tt-panel-heading">
            <h2>No Active Session</h2>
            <span class="tt-status-pill tt-status-ready">Ready</span>
        </div>

        <p>You're ready to begin a new operating session.</p>

        <p>
            <strong>Next Step:</strong>
            Generate jobs and click <em>Start Session</em>.
        </p>

        <p>
            <a class="tt-action tt-action-start" href="/operations/generate.php">
                Start Session
            </a>
        </p>
    </div>

    <div class="tt-panel tt-attention-panel">
        <div class="tt-panel-heading">
            <h3>Needs Attention</h3>
        </div>

        <ul class="tt-list">
            <li>No equipment issues.</li>
            <li>No crew warnings.</li>
            <li>No dispatcher alerts.</li>
        </ul>
    </div>
</div>

<div class="tt-section-header">
    <h2>Quick Actions</h2>
</div>

<div class="tt-actions">
    <a class="tt-action" href="/operations/generate.php">
        <span class="tt-action-icon">📋</span>
        <span>Generate Jobs</span>
    </a>

    <a class="tt-action" href="/equipment/list.php">
        <span class="tt-action-icon">🚃</span>
        <span>Equipment</span>
    </a>

    <a class="tt-action" href="#">
        <span class="tt-action-icon">👷</span>
        <span>Crew</span>
    </a>

    <a class="tt-action" href="/operations/print.php">
        <span class="tt-action-icon">🖨️</span>
        <span>Print Switch Lists</span>
    </a>
</div>

<div class="tt-dashboard-lower">
    <div class="tt-panel">
        <div class="tt-panel-heading">
            <h3>Session Controls</h3>
        </div>

        <div class="tt-control-list">
            <a href="/operations/generate.php">Generate operating work</a>
            <a href="/operations/switch_list.php">View switch lists</a>
            <a href="/operations/print.php">Print switch lists</a>
        </div>
    </div>

    <div class="tt-panel">
        <div class="tt-panel-heading">
            <h3>Repair Queue</h3>
            <span class="tt-muted-count">0</span>
        </div>

        <p class="tt-muted-text">No bad orders or repair items waiting.</p>
    </div>

    <div class="tt-panel">
        <div class="tt-panel-heading">
            <h3>Crew &amp; Dispatcher</h3>
        </div>

        <ul class="tt-list">
            <li>No crews assigned.</li>
            <li>No dispatcher messages.</li>
            <li>No active track warrants.</li>
        </ul>
    </div>

    <div class="tt-panel">
        <div class="tt-panel-heading">
            <h3>Recent Activity</h3>
        </div>

        <p class="tt-muted-text">Session activity will appear here once operations begin.</p>
    </div>
</div>

<?php include '../assets/components/footer.php'; ?>
