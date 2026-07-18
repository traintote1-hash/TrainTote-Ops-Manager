<?php
session_start();
require_once '../config/database.php';
require_once 'lib.php';
if (!isset($_SESSION['user_id'])) { header('Location: ../login.php'); exit; }

$userId = (int)$_SESSION['user_id'];
$railroad = ttOperationsRailroad($pdo, $userId);
$railroadId = (int)$railroad['id'];
ttOperationsRequireRailroadOwner($pdo, $railroadId, $userId);
$definitions = ttOperationsModuleDefinitions();
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        ttOperationsRequireCsrf();
        ttOperationsRequireRailroadOwner($pdo, $railroadId, $userId);
        $pdo->beginTransaction();
        $save = $pdo->prepare('INSERT INTO operation_module_settings(railroad_id,module_key,enabled,updated_by_user_id) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE enabled=VALUES(enabled),updated_by_user_id=VALUES(updated_by_user_id)');
        foreach ($definitions as $key => $definition) {
            if (!$definition['available']) { continue; }
            $save->execute([$railroadId, $key, isset($_POST['modules'][$key]) ? 1 : 0, $userId]);
        }
        $pdo->commit();
        $message = 'Operations modules updated.';
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        $error = $e->getMessage();
    }
}
$states = ttOperationsModuleStates($pdo, $railroadId);
?>
<?php include '../includes/header.php'; ?><title>Operations Settings</title><link rel="stylesheet" href="../assets/css/operations.css"></head><body><?php include '../includes/navbar.php'; ?>
<div class="tt-operations-shell"><?php include '../assets/components/sidebar.php'; ?><section class="tt-ops-page">
<div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4"><div><p class="tt-eyebrow">Railroad Owner</p><h1>Operations Settings</h1><p class="text-muted mb-0">Start with the core workflow and enable advanced tools only when your railroad uses them.</p></div><a class="btn btn-outline-secondary" href="dashboard.php">Back to Operations</a></div>
<?php if ($message): ?><div class="alert alert-success"><?=ttHtml($message)?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?=ttHtml($error)?></div><?php endif; ?>
<div class="alert alert-light border"><strong>Always available:</strong> Sessions, Switch Lists / Work Orders, and Session History.</div>
<form method="post"><input type="hidden" name="csrf_token" value="<?=ttHtml(ttOperationsCsrfToken())?>">
<div class="card"><div class="list-group list-group-flush">
<?php foreach ($definitions as $key => $definition): ?>
<label class="list-group-item d-flex justify-content-between align-items-start gap-3 py-3 tt-module-setting">
<span class="tt-module-setting-copy"><strong><?=ttHtml($definition['label'])?></strong><?php if (!$definition['available']): ?> <span class="badge text-bg-secondary">Coming later</span><?php endif; ?><span class="d-block text-muted small mt-1"><?=ttHtml($definition['description'])?></span></span>
<input class="form-check-input flex-shrink-0" type="checkbox" name="modules[<?=ttHtml($key)?>]" value="1" <?=!empty($states[$key])?'checked':''?> <?=$definition['available']?'':'disabled'?> aria-label="Enable <?=ttHtml($definition['label'])?>">
</label>
<?php endforeach; ?>
</div></div>
<p class="small text-muted mt-3">Disabling a module hides its tools and stops its requests. Existing configuration and historical records are retained.</p>
<button class="btn btn-primary tt-mobile-full">Save Operations Settings</button>
</form>
</section></div><?php include '../includes/footer.php'; ?>
