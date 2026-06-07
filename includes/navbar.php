	<?php

	$currentPage = $_SERVER['PHP_SELF'];

	function navActive($page)
	{
		global $currentPage;

		if (strpos($currentPage, $page) !== false) {
			return 'active';
		}

		return '';
	}

	?>

	<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">

	<div class="container-fluid">

	<a class="navbar-brand" href="/dashboard.php">

	TrainTote Ops Manager

	</a>
	
	</a>


	<button
	class="navbar-toggler"
	type="button"
	data-bs-toggle="collapse"
	data-bs-target="#navbarNav">

	<span class="navbar-toggler-icon"></span>

	</button>

	<div
	class="collapse navbar-collapse"
	id="navbarNav">

	<ul class="navbar-nav me-auto">

	<li class="nav-item">
	<a
	class="nav-link <?php echo navActive('/dashboard.php'); ?>"
	href="/dashboard.php">
	Dashboard
	</a>
	</li>

	<li class="nav-item">
	<a
	class="nav-link <?php echo navActive('/equipment/'); ?>"
	href="/equipment/list.php">
	Equipment
	</a>
	</li>

	<li class="nav-item">
	<a
	class="nav-link <?php echo navActive('/industries/'); ?>"
	href="/industries/list.php">
	Industries
	</a>
	</li>

	<li class="nav-item">
	<a
	class="nav-link <?php echo navActive('/waybills/'); ?>"
	href="/waybills/list.php">
	Waybills
	</a>
	<li class="nav-item">
<a
class="nav-link <?php echo navActive('/jobs/'); ?>"
href="/jobs/list.php">
Jobs
</a>
</li>
	<a
	class="nav-link <?php echo navActive('/operations/'); ?>"
	href="/operations/generate.php">
	Operations
	</a>
	</li>

	<li class="nav-item">
	<a
	class="nav-link <?php echo navActive('/ai/'); ?>"
	href="/ai/scan_equipment.php">
	AI Scanner
	</a>
	</li>

	</ul>

	<ul class="navbar-nav">

	<li class="nav-item">
	<a class="nav-link" href="/logout.php">
	Logout
	</a>
	</li>

	</ul>

	</div>

	</div>

	</nav>