<?php
require __DIR__ . '/bootstrap.php';

$plans = ['free' => 'Free', 'plus' => 'Plus', 'pro' => 'Pro'];
$statuses = ['active' => 'Active', 'paused' => 'Paused', 'cancelled' => 'Cancelled'];

function admin_redirect(array $params = []): void
{
    $query = array_merge($_GET, $params);
    header('Location: index.php' . ($query ? '?' . http_build_query($query) : ''));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals(admin_csrf(), (string)($_POST['csrf_token'] ?? ''))) {
        exit('Form expired.');
    }

    $action = (string)($_POST['action'] ?? 'update_user');
    $plan = (string)($_POST['plan_code'] ?? 'free');
    $status = (string)($_POST['status'] ?? 'active');

    if (!array_key_exists($plan, $plans) || !array_key_exists($status, $statuses)) {
        exit('Invalid service setting.');
    }

    if ($action === 'bulk_update') {
        $ids = array_values(array_unique(array_filter(array_map('intval', $_POST['user_ids'] ?? []))));
        if (!$ids) {
            admin_redirect(['error' => 'Choose at least one user before applying a bulk update.']);
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $valid = $pdo->prepare("SELECT id FROM users WHERE id IN ($placeholders)");
        $valid->execute($ids);
        $validIds = array_map('intval', $valid->fetchAll(PDO::FETCH_COLUMN));

        $statement = $pdo->prepare('INSERT INTO user_subscriptions (user_id,plan_code,status,updated_by) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE plan_code=VALUES(plan_code),status=VALUES(status),updated_by=VALUES(updated_by),updated_at=CURRENT_TIMESTAMP');
        foreach ($validIds as $userId) {
            $statement->execute([$userId, $plan, $status, $admin['id']]);
            admin_audit($pdo, (int)$admin['id'], 'subscription_bulk_updated', $userId, ['plan' => $plan, 'status' => $status]);
        }

        admin_redirect(['saved' => count($validIds)]);
    }

    $id = (int)($_POST['user_id'] ?? 0);
    $statement = $pdo->prepare('INSERT INTO user_subscriptions (user_id,plan_code,status,updated_by) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE plan_code=VALUES(plan_code),status=VALUES(status),updated_by=VALUES(updated_by),updated_at=CURRENT_TIMESTAMP');
    $statement->execute([$id, $plan, $status, $admin['id']]);
    admin_audit($pdo, (int)$admin['id'], 'subscription_updated', $id, ['plan' => $plan, 'status' => $status]);
    admin_redirect(['saved' => 1]);
}

$query = trim((string)($_GET['q'] ?? ''));
$planFilter = (string)($_GET['plan'] ?? '');
$statusFilter = (string)($_GET['status'] ?? '');
$limit = min(max((int)($_GET['limit'] ?? 250), 25), 1000);

$where = [];
$params = [];

if ($query !== '') {
    $where[] = '(u.email LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)';
    $like = '%' . $query . '%';
    array_push($params, $like, $like, $like);
}

if (array_key_exists($planFilter, $plans)) {
    $where[] = "COALESCE(s.plan_code,'free') = ?";
    $params[] = $planFilter;
}

if (array_key_exists($statusFilter, $statuses)) {
    $where[] = "COALESCE(s.status,'active') = ?";
    $params[] = $statusFilter;
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stats = $pdo->query("SELECT
    (SELECT COUNT(*) FROM users) users,
    (SELECT COUNT(*) FROM railroads) railroads,
    (SELECT COUNT(*) FROM user_subscriptions WHERE plan_code<>'free' AND status='active') paid,
    (SELECT COUNT(*) FROM user_subscriptions WHERE plan_code='plus' AND status='active') plus,
    (SELECT COUNT(*) FROM user_subscriptions WHERE plan_code='pro' AND status='active') pro
")->fetch(PDO::FETCH_ASSOC);

$countStatement = $pdo->prepare("SELECT COUNT(*) FROM users u LEFT JOIN user_subscriptions s ON s.user_id=u.id $whereSql");
$countStatement->execute($params);
$filteredTotal = (int)$countStatement->fetchColumn();

$usersStatement = $pdo->prepare("
    SELECT u.id,u.first_name,u.last_name,u.email,u.created_at,u.is_admin,
           COALESCE(s.plan_code,'free') plan_code,
           COALESCE(s.status,'active') status,
           r.name railroad_name,
           r.id railroad_id,
           (SELECT COUNT(*) FROM equipment e WHERE e.railroad_id=r.id) equipment_count,
           (SELECT COUNT(*) FROM industries i WHERE i.railroad_id=r.id) industry_count
    FROM users u
    LEFT JOIN user_subscriptions s ON s.user_id=u.id
    LEFT JOIN railroads r ON r.user_id=u.id
    $whereSql
    ORDER BY u.created_at DESC
    LIMIT $limit
");
$usersStatement->execute($params);
$users = $usersStatement->fetchAll(PDO::FETCH_ASSOC);
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<title>TrainTote Admin</title>
</head>
<body>
<?php include __DIR__ . '/../includes/navbar.php'; ?>
<main class="container-fluid px-3 px-lg-4 py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
        <div>
            <h1 class="mb-1">Operations Admin</h1>
            <p class="text-muted mb-0">Users, service access, railroad counts, and platform activity.</p>
        </div>
        <a class="btn btn-outline-secondary" href="../dashboard.php">Back to Ops</a>
    </div>

    <?php if (isset($_GET['saved'])): ?>
        <div class="alert alert-success">Service access updated for <?= (int)$_GET['saved'] ?> user<?= (int)$_GET['saved'] === 1 ? '' : 's' ?>.</div>
    <?php endif; ?>
    <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-warning"><?= htmlspecialchars((string)$_GET['error']) ?></div>
    <?php endif; ?>

    <div class="row g-3 mb-4">
        <?php foreach (['users' => 'Users', 'paid' => 'Paid accounts', 'plus' => 'Active Plus', 'pro' => 'Active Pro', 'railroads' => 'Railroads'] as $key => $label): ?>
            <div class="col-6 col-lg">
                <div class="card h-100">
                    <div class="card-body">
                        <small class="text-muted"><?= htmlspecialchars($label) ?></small>
                        <div class="fs-2 fw-bold"><?= (int)$stats[$key] ?></div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <section class="card mb-4">
        <div class="card-body">
            <form class="row g-3 align-items-end" method="get">
                <div class="col-md-5 col-xl-4">
                    <label class="form-label" for="adminSearch">Search users</label>
                    <input class="form-control" id="adminSearch" name="q" placeholder="Name or email" value="<?= htmlspecialchars($query) ?>">
                </div>
                <div class="col-sm-6 col-md-2">
                    <label class="form-label" for="planFilter">Plan</label>
                    <select class="form-select" id="planFilter" name="plan">
                        <option value="">All plans</option>
                        <?php foreach ($plans as $value => $label): ?>
                            <option value="<?= $value ?>" <?= $planFilter === $value ? 'selected' : '' ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-sm-6 col-md-2">
                    <label class="form-label" for="statusFilter">Status</label>
                    <select class="form-select" id="statusFilter" name="status">
                        <option value="">All statuses</option>
                        <?php foreach ($statuses as $value => $label): ?>
                            <option value="<?= $value ?>" <?= $statusFilter === $value ? 'selected' : '' ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-sm-6 col-md-2">
                    <label class="form-label" for="limitFilter">Show</label>
                    <select class="form-select" id="limitFilter" name="limit">
                        <?php foreach ([100, 250, 500, 1000] as $option): ?>
                            <option value="<?= $option ?>" <?= $limit === $option ? 'selected' : '' ?>><?= $option ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-sm-6 col-md-2 d-flex gap-2">
                    <button class="btn btn-primary flex-fill">Search</button>
                    <a class="btn btn-outline-secondary" href="index.php">Reset</a>
                </div>
            </form>
        </div>
    </section>

    <form method="post" id="bulkUserForm">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(admin_csrf()) ?>">
        <input type="hidden" name="action" value="bulk_update">
    </form>

    <section class="card">
            <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div>
                    <strong>Accounts</strong>
                    <span class="text-muted ms-2"><?= count($users) ?> shown of <?= $filteredTotal ?> matching</span>
                </div>
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <select name="plan_code" form="bulkUserForm" class="form-select form-select-sm w-auto" aria-label="Bulk plan">
                        <?php foreach ($plans as $value => $label): ?>
                            <option value="<?= $value ?>"><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="status" form="bulkUserForm" class="form-select form-select-sm w-auto" aria-label="Bulk status">
                        <?php foreach ($statuses as $value => $label): ?>
                            <option value="<?= $value ?>"><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn btn-sm btn-primary" form="bulkUserForm" onclick="return confirm('Update every selected user?')">Apply to selected</button>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th><input class="form-check-input" type="checkbox" id="selectAllUsers" aria-label="Select all users"></th>
                            <th>User</th>
                            <th>Railroad</th>
                            <th>Usage</th>
                            <th>Joined</th>
                            <th>Service</th>
                            <th>Update</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <?php $name = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')); ?>
                            <tr>
                                <td><input class="form-check-input admin-user-checkbox" form="bulkUserForm" type="checkbox" name="user_ids[]" value="<?= (int)$user['id'] ?>" aria-label="Select <?= htmlspecialchars($user['email']) ?>"></td>
                                <td>
                                    <strong><?= htmlspecialchars($name !== '' ? $name : 'No name') ?></strong>
                                    <?php if (!empty($user['is_admin'])): ?><span class="badge text-bg-dark ms-1">Admin</span><?php endif; ?>
                                    <br><a href="mailto:<?= htmlspecialchars($user['email']) ?>"><?= htmlspecialchars($user['email']) ?></a>
                                </td>
                                <td><?= htmlspecialchars($user['railroad_name'] ?: 'No railroad') ?></td>
                                <td>
                                    <span class="badge text-bg-light"><?= (int)$user['equipment_count'] ?> equipment</span>
                                    <span class="badge text-bg-light"><?= (int)$user['industry_count'] ?> industries</span>
                                </td>
                                <td><?= htmlspecialchars($user['created_at']) ?></td>
                                <td><span class="badge text-bg-primary"><?= htmlspecialchars($plans[$user['plan_code']] ?? $user['plan_code']) ?></span> <small class="text-muted"><?= htmlspecialchars($statuses[$user['status']] ?? $user['status']) ?></small></td>
                                <td>
                                    <form method="post" class="d-flex flex-wrap gap-1">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(admin_csrf()) ?>">
                                        <input type="hidden" name="action" value="update_user">
                                        <input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>">
                                        <select name="plan_code" class="form-select form-select-sm w-auto">
                                            <?php foreach ($plans as $value => $label): ?>
                                                <option value="<?= $value ?>" <?= $user['plan_code'] === $value ? 'selected' : '' ?>><?= $label ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <select name="status" class="form-select form-select-sm w-auto">
                                            <?php foreach ($statuses as $value => $label): ?>
                                                <option value="<?= $value ?>" <?= $user['status'] === $value ? 'selected' : '' ?>><?= $label ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button class="btn btn-sm btn-outline-primary">Save</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$users): ?>
                            <tr><td colspan="7" class="text-center text-muted py-5">No users match those filters.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
    </section>
</main>
<script>
(() => {
    const selectAll = document.getElementById('selectAllUsers');
    const boxes = Array.from(document.querySelectorAll('.admin-user-checkbox'));
    if (!selectAll || !boxes.length) return;
    selectAll.addEventListener('change', () => {
        boxes.forEach(box => box.checked = selectAll.checked);
    });
})();
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
