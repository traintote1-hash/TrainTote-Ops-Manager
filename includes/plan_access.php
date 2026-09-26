<?php

function ttPlanDefinitions(): array
{
    return [
        'free' => [
            'label' => 'Free',
            'equipment_limit' => 25,
            'industry_limit' => 10,
            'car_cards' => false,
            'switch_lists' => false,
            'operations' => false,
            'crew_access' => false,
        ],
        'plus' => [
            'label' => 'Plus',
            'equipment_limit' => 200,
            'industry_limit' => 25,
            'car_cards' => true,
            'switch_lists' => true,
            'operations' => true,
            'crew_access' => false,
        ],
        'pro' => [
            'label' => 'Pro',
            'equipment_limit' => null,
            'industry_limit' => null,
            'car_cards' => true,
            'switch_lists' => true,
            'operations' => true,
            'crew_access' => true,
        ],
    ];
}

function ttNormalizePlanCode(?string $planCode): string
{
    $planCode = strtolower(trim((string)$planCode));

    if ($planCode === 'business') {
        return 'pro';
    }

    return array_key_exists($planCode, ttPlanDefinitions()) ? $planCode : 'free';
}

function ttUserPlan(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare("SELECT plan_code,status FROM user_subscriptions WHERE user_id=? LIMIT 1");
    $stmt->execute([$userId]);
    $subscription = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $active = ($subscription['status'] ?? 'active') === 'active';
    $planCode = $active ? ttNormalizePlanCode($subscription['plan_code'] ?? 'free') : 'free';
    $definition = ttPlanDefinitions()[$planCode];
    $definition['code'] = $planCode;
    $definition['status'] = $subscription['status'] ?? 'active';

    return $definition;
}

function ttPlanCan(PDO $pdo, int $userId, string $feature): bool
{
    $plan = ttUserPlan($pdo, $userId);
    return !empty($plan[$feature]);
}

function ttPlanLimitLabel(?int $limit): string
{
    return $limit === null ? 'unlimited' : (string)$limit;
}

function ttPlanCountActive(PDO $pdo, int $railroadId, string $table): int
{
    if (!in_array($table, ['equipment', 'industries'], true)) {
        throw new InvalidArgumentException('Unsupported plan limit table.');
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE railroad_id=? AND active=1");
    $stmt->execute([$railroadId]);
    return (int)$stmt->fetchColumn();
}

function ttPlanRequireRoom(PDO $pdo, int $userId, int $railroadId, string $kind): void
{
    $plan = ttUserPlan($pdo, $userId);
    $limitKey = $kind === 'equipment' ? 'equipment_limit' : 'industry_limit';
    $label = $kind === 'equipment' ? 'equipment' : 'industries';
    $limit = $plan[$limitKey];

    if ($limit === null) {
        return;
    }

    $count = ttPlanCountActive($pdo, $railroadId, $kind === 'equipment' ? 'equipment' : 'industries');

    if ($count >= $limit) {
        throw new RuntimeException($plan['label'] . ' allows ' . ttPlanLimitLabel($limit) . ' active ' . $label . '. Upgrade or deactivate an existing item before adding more.');
    }
}

function ttPlanRequireFeature(PDO $pdo, int $userId, string $feature, string $featureLabel): void
{
    if (!ttPlanCan($pdo, $userId, $feature)) {
        $plan = ttUserPlan($pdo, $userId);
        throw new RuntimeException($featureLabel . ' is not included with ' . $plan['label'] . '. Upgrade your plan to use it.');
    }
}
