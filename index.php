<?php

session_start();

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta
name="viewport"
content="width=device-width, initial-scale=1">

<title>
TrainTote Ops Manager
</title>

<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">

<style>

.hero {
    background: #f8f9fa;
    min-height: 70vh;
    display: flex;
    align-items: center;
}

.feature-card {
    transition: .2s;
}

.feature-card:hover {
    transform: translateY(-4px);
}

</style>

</head>

<body>

<section class="hero">

<div class="container">

<div class="row justify-content-center">

<div class="col-lg-10 text-center">

<h1 class="display-3 fw-bold mb-4">

🚂 TrainTote Ops Manager

</h1>

<p class="lead mb-5">

Manage equipment, industries, waybills, and operating sessions
for your model railroad from any device.

</p>

<div class="mb-5">

<a
href="register.php"
class="btn btn-primary btn-lg me-2">

Create Account

</a>

<a
href="login.php"
class="btn btn-outline-secondary btn-lg">

Login

</a>

</div>
<p class="lead mb-4">     100% web-based • No installation required	</p>	<p class="fw-semibold mb-3"> Try the live demo today!</p><div class="mb-5">    <a    href="https://demo.traintote.com/enter_demo.php"    class="btn btn-success btn-lg">        🚂 Launch Interactive Demo    </a></div>
</div>

</div>

</div>

</section>

<section class="py-5">

<div class="container">

<h2 class="text-center mb-5">

Features

</h2>

<div class="row g-4">

<div class="col-md-4">

<div class="card h-100 shadow-sm feature-card">

<div class="card-body text-center">

<h3>🚂</h3>

<h5>Equipment Roster</h5>

<p>

Track locomotives, freight cars,
passenger cars, cabooses, and MOW equipment.

</p>

</div>

</div>

</div>

<div class="col-md-4">

<div class="card h-100 shadow-sm feature-card">

<div class="card-body text-center">

<h3>🏭</h3>

<h5>Industry Management</h5>

<p>

Create industries, assign capacities,
and organize railroad customers.

</p>

</div>

</div>

</div>

<div class="col-md-4">

<div class="card h-100 shadow-sm feature-card">

<div class="card-body text-center">

<h3>📄</h3>

<h5>Waybill System</h5>

<p>

Generate realistic traffic and routing
for railroad operations.

</p>

</div>

</div>

</div>

<div class="col-md-4">

<div class="card h-100 shadow-sm feature-card">

<div class="card-body text-center">

<h3>🎯</h3>

<h5>Operating Sessions</h5>

<p>

Automatically generate switch lists
for realistic operating sessions.

</p>

</div>

</div>

</div>

<div class="col-md-4">

<div class="card h-100 shadow-sm feature-card">

<div class="card-body text-center">

<h3>🤖</h3>

<h5>AI Equipment Scanner</h5>

<p>

Upload a photo and let AI identify
equipment details automatically.

</p>

</div>

</div>

</div>

<div class="col-md-4">

<div class="card h-100 shadow-sm feature-card">

<div class="card-body text-center">

<h3>📱</h3>

<h5>Mobile Friendly</h5>

<p>

Designed for phones, tablets,
and desktop computers.

</p>

</div>

</div>

</div>

</div>

<div class="text-center mt-5">

<a
href="register.php"
class="btn btn-primary btn-lg me-2">

Get Started

</a>

<a
href="login.php"
class="btn btn-secondary btn-lg">

Login

</a>

</div>

</div>

</section>

<script
src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js">
</script>

</body>

</html>