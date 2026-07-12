<?php
// Legacy bookmark compatibility.
$query = isset($_GET['id']) ? '?id=' . (int)$_GET['id'] : '';
header('Location: switch_lists.php' . $query);
exit;
