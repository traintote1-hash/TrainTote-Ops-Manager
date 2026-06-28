<?php
$pageTitle='Operations Center';
include '../assets/components/header.php';
include '../assets/components/sidebar.php';
?>
<link rel="stylesheet" href="../assets/css/dashboard.css">

<div class="tt-dashboard-page">
    <div class="tt-hero">
        <div class="tt-hero-main">
            <div class="tt-hero-icon" aria-hidden="true">🚂</div>
            <div>
                <span class="tt-hero-kicker">Operations Dashboard</span>
                <h1>Operations Center</h1>
                <p>Arkansas &amp; Missouri Railroad</p>
            </div>
        </div>

        <div class="tt-hero-summary" aria-label="Operations areas">
            <span>Session planning</span>
            <span>Switch lists</span>
            <span>Crew readiness</span>
        </div>
    </div>

    <div class="tt-status">
        <div class="tt-panel tt-session-panel">
            <div class="tt-panel-heading">
                <div>
                    <span class="tt-panel-kicker">Current Session</span>
                    <h2>No Active Session</h2>
                </div>
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
                <div>
                    <span class="tt-panel-kicker">Status Check</span>
                    <h3>Needs Attention</h3>
                </div>
            </div>

            <ul class="tt-list tt-check-list">
                <li>No equipment issues.</li>
                <li>No crew warnings.</li>
                <li>No dispatcher alerts.</li>
            </ul>
        </div>
    </div>

    <div class="tt-section-header">
        <div>
            <span class="tt-panel-kicker">Common Workflows</span>
            <h2>Quick Actions</h2>
        </div>
    </div>

    <div class="tt-actions">
        <a class="tt-action" href="/operations/generate.php">
            <span class="tt-action-icon" aria-hidden="true">📋</span>
            <span class="tt-action-copy">
                <span>Generate Jobs</span>
                <small>Build operating work</small>
            </span>
        </a>

        <a class="tt-action" href="/equipment/list.php">
            <span class="tt-action-icon" aria-hidden="true">🚃</span>
            <span class="tt-action-copy">
                <span>Equipment</span>
                <small>Review roster status</small>
            </span>
        </a>

        <a class="tt-action" href="#">
            <span class="tt-action-icon" aria-hidden="true">👷</span>
            <span class="tt-action-copy">
                <span>Crew</span>
                <small>Plan assignments</small>
            </span>
        </a>

        <a class="tt-action" href="/operations/print.php">
            <span class="tt-action-icon" aria-hidden="true">🖨️</span>
            <span class="tt-action-copy">
                <span>Print Switch Lists</span>
                <small>Prepare paper copies</small>
            </span>
        </a>
    </div>

    <div class="tt-dashboard-lower">
        <div class="tt-panel">
            <div class="tt-panel-heading">
                <div>
                    <span class="tt-panel-kicker">Workflow</span>
                    <h3>Session Controls</h3>
                </div>
            </div>

            <div class="tt-control-list">
                <a href="/operations/generate.php">Generate operating work</a>
                <a href="/operations/switch_list.php">View switch lists</a>
                <a href="/operations/print.php">Print switch lists</a>
            </div>
        </div>

        <div class="tt-panel">
            <div class="tt-panel-heading">
                <div>
                    <span class="tt-panel-kicker">Maintenance</span>
                    <h3>Repair Queue</h3>
                </div>
                <span class="tt-muted-count">0</span>
            </div>

            <p class="tt-muted-text">No bad orders or repair items waiting.</p>
        </div>

        <div class="tt-panel">
            <div class="tt-panel-heading">
                <div>
                    <span class="tt-panel-kicker">People</span>
                    <h3>Crew &amp; Dispatcher</h3>
                </div>
            </div>

            <ul class="tt-list">
                <li>No crews assigned.</li>
                <li>No dispatcher messages.</li>
                <li>No active track warrants.</li>
            </ul>
        </div>

        <div class="tt-panel">
            <div class="tt-panel-heading">
                <div>
                    <span class="tt-panel-kicker">Timeline</span>
                    <h3>Recent Activity</h3>
                </div>
            </div>

            <p class="tt-muted-text">Session activity will appear here once operations begin.</p>
        </div>
    </div>
</div>

<?php include '../assets/components/footer.php'; ?>
