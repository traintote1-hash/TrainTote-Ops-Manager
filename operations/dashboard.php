<?php
$pageTitle='Operations Center';
include '../assets/components/header.php';
include '../assets/components/sidebar.php';
?>
<link rel="stylesheet" href="../assets/css/dashboard.css">

<div class="tt-hero">
<h1>🚂 Operations Center</h1>
<p>Arkansas & Missouri Railroad</p>
</div>

<div class="tt-status">
<div class="tt-panel">
<h2>No Active Session</h2>
<p>You're ready to begin a new operating session.</p>
<p><strong>Next Step:</strong> Generate jobs and click <em>Start Session</em>.</p>
<p><a class="tt-action" href="#">Start Session</a></p>
</div>

<div class="tt-panel">
<h3>Needs Attention</h3>
<ul class="tt-list">
<li>No equipment issues.</li>
<li>No crew warnings.</li>
<li>No dispatcher alerts.</li>
</ul>
</div>
</div>

<h2>Quick Actions</h2>
<div class="tt-actions">
<a class="tt-action" href="#">Generate Jobs</a>
<a class="tt-action" href="#">Equipment</a>
<a class="tt-action" href="#">Crew</a>
<a class="tt-action" href="#">Print Switch Lists</a>
</div>

<?php include '../assets/components/footer.php'; ?>
