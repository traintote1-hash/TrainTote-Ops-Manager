<?php
session_start();
header('Content-Type: application/json');
require_once '../config/database.php';
require_once 'lib.php';
require_once 'fast_clock_service.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required.']);
    exit;
}

try {
    $railroad = ttOperationsRailroad($pdo, (int)$_SESSION['user_id']);
    $railroadId = (int)$railroad['id'];
    $sessionId = (int)($_GET['session_id'] ?? $_POST['session_id'] ?? 0);
    $clock = ttLoadFastClock($pdo, $sessionId, $railroadId);
    if (!$clock) {
        http_response_code(404);
        throw new RuntimeException('Operating session not found.');
    }
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        echo json_encode(ttFastClockPayload($clock));
        exit;
    }

    ttOperationsRequireCsrf();
    ttOperationsRequireRailroadOwner($pdo, $railroadId, (int)$_SESSION['user_id']);
    $action = (string)($_POST['action'] ?? '');
    $pdo->beginTransaction();
    $clock = ttLoadFastClock($pdo, $sessionId, $railroadId, true);
    if (!$clock) { throw new RuntimeException('Operating session not found.'); }

    if ($action === 'configure') {
        if (!in_array($clock['status'], ['draft', 'ready'], true) || !empty($clock['fast_clock_started_at'])) {
            throw new RuntimeException('Fast Clock settings can only change before the clock starts.');
        }
        $enabled = ($_POST['enabled'] ?? '0') === '1' ? 1 : 0;
        $ratio = (int)($_POST['ratio'] ?? 0);
        if (!in_array($ratio, ttFastClockRatios(), true)) { throw new RuntimeException('Choose a supported Fast Clock ratio.'); }
        $startMinutes = ttFastClockNormalizeStart((string)($_POST['start_time'] ?? ''));
        $stmt = $pdo->prepare('UPDATE operating_sessions SET fast_clock_enabled=?,fast_clock_running=0,fast_clock_start_minutes=?,fast_clock_ratio=?,fast_clock_base_model_seconds=?,fast_clock_base_real_at=NULL,fast_clock_last_sync_at=NOW() WHERE id=? AND railroad_id=?');
        $stmt->execute([$enabled, $startMinutes, $ratio, $startMinutes * 60, $sessionId, $railroadId]);
    } elseif (in_array($action, ['start', 'resume'], true)) {
        if ($clock['status'] !== 'in_progress' || empty($clock['fast_clock_enabled']) || !empty($clock['fast_clock_running'])) {
            throw new RuntimeException('The enabled Fast Clock can only start or resume during an Active session.');
        }
        $stmt = $pdo->prepare('UPDATE operating_sessions SET fast_clock_running=1,fast_clock_base_real_at=NOW(),fast_clock_last_sync_at=NOW(),fast_clock_started_at=COALESCE(fast_clock_started_at,NOW()) WHERE id=? AND railroad_id=?');
        $stmt->execute([$sessionId, $railroadId]);
    } elseif ($action === 'pause') {
        if ($clock['status'] !== 'in_progress' || empty($clock['fast_clock_enabled']) || empty($clock['fast_clock_running'])) {
            throw new RuntimeException('Only a running Fast Clock in an Active session can be paused.');
        }
        ttFreezeFastClock($pdo, $sessionId, $railroadId);
    } elseif ($action === 'reset') {
        if ($clock['status'] !== 'in_progress' || empty($clock['fast_clock_enabled'])) {
            throw new RuntimeException('Only an enabled Fast Clock in an Active session can be reset.');
        }
        $stmt = $pdo->prepare('UPDATE operating_sessions SET fast_clock_running=0,fast_clock_base_model_seconds=fast_clock_start_minutes*60,fast_clock_base_real_at=NOW(),fast_clock_last_sync_at=NOW() WHERE id=? AND railroad_id=?');
        $stmt->execute([$sessionId, $railroadId]);
    } else {
        throw new RuntimeException('Invalid Fast Clock action.');
    }

    $pdo->commit();
    $clock = ttLoadFastClock($pdo, $sessionId, $railroadId);
    echo json_encode(ttFastClockPayload($clock));
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) { $pdo->rollBack(); }
    if (http_response_code() < 400) { http_response_code(422); }
    echo json_encode(['error' => $e->getMessage()]);
}
