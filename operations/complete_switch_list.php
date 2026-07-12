<?php
// Compatibility route. Permanent schema creation and session-only completion were retired.
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: ../login.php'); exit; }
$id = (int)($_GET['id'] ?? $_POST['switch_list_id'] ?? 0);
header('Location: ' . ($id > 0 ? 'work_order.php?id=' . $id : 'switch_lists.php'));
exit;
