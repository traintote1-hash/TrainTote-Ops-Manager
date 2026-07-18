<?php
require_once __DIR__.'/fast_clock_service.php';
$fastClock = ttLoadFastClock($pdo, (int)$fastClockSessionId, (int)$railroadId);
if ($fastClock && !empty($fastClock['fast_clock_enabled'])):
    $fastClockPayload = ttFastClockPayload($fastClock);
    $fastClockCanControl = ttOperationsIsRailroadOwner($pdo, (int)$railroadId, (int)$_SESSION['user_id']);
?>
<aside class="tt-fast-clock" data-fast-clock data-session-id="<?=(int)$fastClockSessionId?>" data-state="<?=ttHtml(json_encode($fastClockPayload))?>" data-csrf="<?=ttHtml(ttOperationsCsrfToken())?>" aria-label="Fast Clock">
    <div><span class="tt-fast-clock-label">Fast Clock</span><strong data-fast-clock-time>--:-- --</strong></div>
    <div class="tt-fast-clock-meta"><span data-fast-clock-ratio><?=(int)$fastClock['fast_clock_ratio']?>:1</span><span data-fast-clock-paused <?=$fastClockPayload['running']?'hidden':''?>>PAUSED</span></div>
    <?php if ($fastClock['status'] === 'in_progress' && $fastClockCanControl): ?>
    <div class="tt-fast-clock-controls">
        <button type="button" class="btn btn-sm btn-light" data-fast-clock-action="<?=$fastClockPayload['running']?'pause':($fastClockPayload['started']?'resume':'start')?>"><?=$fastClockPayload['running']?'Pause':($fastClockPayload['started']?'Resume':'Start')?></button>
        <button type="button" class="btn btn-sm btn-outline-light" data-fast-clock-action="reset">Reset</button>
    </div>
    <?php endif; ?>
    <span class="visually-hidden" role="status" data-fast-clock-status></span>
</aside>
<script defer src="../assets/js/fast-clock.js"></script>
<?php endif; ?>
