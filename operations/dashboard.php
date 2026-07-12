<?php
$pageTitle='Operations Center';
include '../assets/components/header.php';
include '../assets/components/sidebar.php';
?>
<link rel="stylesheet" href="../assets/css/dashboard.css">

<main class="tt-content">
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
                Build a persistent session, add assignments, then start when the work is Ready.
            </p>

            <p>
                <a class="tt-action tt-action-start" href="/operations/sessions.php">
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
        <a class="tt-action" href="/operations/sessions.php">
            <span class="tt-action-icon" aria-hidden="true">📋</span>
            <span class="tt-action-copy">
                <span>Build Session</span>
                <small>Add multiple saved assignments</small>
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

        <a class="tt-action" href="/operations/switch_lists.php">
            <span class="tt-action-icon" aria-hidden="true">🖨️</span>
            <span class="tt-action-copy">
                <span>Switch Lists</span>
                <small>Resume, print, or close out saved work</small>
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
                <a href="/operations/sessions.php">Build or resume a session</a>
                <a href="/operations/switch_lists.php">View persistent switch lists</a>
                <a href="/operations/prepared_cuts.php">Manage prepared trains and cuts</a>
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
</main>

<?php include '../assets/components/footer.php'; ?>
