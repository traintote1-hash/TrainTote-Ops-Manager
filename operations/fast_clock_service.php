<?php

function ttFastClockRatios(): array
{
    return [1, 2, 4, 6, 8, 10];
}

function ttFastClockNormalizeStart(string $value): int
{
    if (!preg_match('/^(\d{2}):(\d{2})$/', $value, $matches)) {
        throw new RuntimeException('Choose a valid Fast Clock start time.');
    }
    $hours = (int)$matches[1];
    $minutes = (int)$matches[2];
    if ($hours > 23 || $minutes > 59) {
        throw new RuntimeException('Choose a valid Fast Clock start time.');
    }
    return ($hours * 60) + $minutes;
}

function ttFastClockFormatMinutes(int $minutes): string
{
    $minutes = (($minutes % 1440) + 1440) % 1440;
    return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
}

function ttFastClockModelSeconds(array $clock, int $nowEpoch): int
{
    $base = (int)($clock['fast_clock_base_model_seconds'] ?? 0);
    if (empty($clock['fast_clock_running']) || empty($clock['fast_clock_base_real_epoch'])) {
        return (($base % 86400) + 86400) % 86400;
    }
    $elapsed = max(0, $nowEpoch - (int)$clock['fast_clock_base_real_epoch']);
    return ($base + ($elapsed * (int)$clock['fast_clock_ratio'])) % 86400;
}

function ttLoadFastClock(PDO $pdo, int $sessionId, int $railroadId, bool $forUpdate = false): ?array
{
    $sql = 'SELECT s.*,UNIX_TIMESTAMP(s.fast_clock_base_real_at) fast_clock_base_real_epoch,UNIX_TIMESTAMP() server_epoch FROM operating_sessions s WHERE s.id=? AND s.railroad_id=?';
    if ($forUpdate) { $sql .= ' FOR UPDATE'; }
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$sessionId, $railroadId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function ttFastClockPayload(array $clock): array
{
    $serverEpoch = (int)($clock['server_epoch'] ?? time());
    return [
        'enabled' => (bool)$clock['fast_clock_enabled'],
        'running' => (bool)$clock['fast_clock_running'],
        'ratio' => (int)$clock['fast_clock_ratio'],
        'model_seconds' => ttFastClockModelSeconds($clock, $serverEpoch),
        'server_epoch_ms' => $serverEpoch * 1000,
        'started' => !empty($clock['fast_clock_started_at']),
        'session_status' => (string)$clock['status'],
    ];
}

function ttFreezeFastClock(PDO $pdo, int $sessionId, int $railroadId): void
{
    $pdo->prepare("UPDATE operating_sessions SET fast_clock_base_model_seconds=MOD(fast_clock_base_model_seconds+IF(fast_clock_running=1,GREATEST(TIMESTAMPDIFF(SECOND,fast_clock_base_real_at,NOW()),0)*fast_clock_ratio,0),86400),fast_clock_running=0,fast_clock_base_real_at=NOW(),fast_clock_last_sync_at=NOW() WHERE id=? AND railroad_id=? AND fast_clock_enabled=1")
        ->execute([$sessionId, $railroadId]);
}
