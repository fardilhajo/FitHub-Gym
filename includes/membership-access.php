<?php
/** Membership helpers shared by member-facing feature pages. */

function active_membership_plan(PDO $db, ?array $user): ?array
{
    if (!$user || ($user['Role'] ?? '') !== 'Member') {
        return null;
    }

    try {
        $stmt = $db->prepare('
            SELECT mp.PlanID, mp.Name, mp.PriceMonthly, mp.AccessLevel, mp.Description, mm.SubscribedAt, mm.Status
            FROM MEMBER_MEMBERSHIP mm
            JOIN MEMBERSHIP_PLAN mp ON mp.PlanID = mm.PlanID
            WHERE mm.MemberID = :member_id
              AND mm.Status = "Active"
              AND mp.IsActive = 1
            LIMIT 1
        ');
        $stmt->execute([':member_id' => $user['UserID']]);
        $plan = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return null;
    }

    return $plan ?: null;
}

function membership_plan_tier(?array $plan): string
{
    if (!$plan || empty($plan['Name'])) {
        return 'None';
    }

    $name = strtolower(trim((string)$plan['Name']));
    if ($name === 'elite') {
        return 'Elite';
    }
    if ($name === 'premium') {
        return 'Premium';
    }
    if ($name === 'basic') {
        return 'Basic';
    }

    return 'None';
}

function membership_tier_rank(string $tier): int
{
    $ranks = [
        'basic' => 1,
        'premium' => 2,
        'elite' => 3,
    ];

    return $ranks[strtolower($tier)] ?? 0;
}

function membership_plan_allows(?array $plan, string $minimumTier): bool
{
    return membership_tier_rank(membership_plan_tier($plan)) >= membership_tier_rank($minimumTier);
}

function membership_tier_label(string $tier): string
{
    $labels = [
        'basic' => 'Basic',
        'premium' => 'Premium',
        'elite' => 'Elite',
    ];

    return $labels[strtolower($tier)] ?? 'Membership';
}

function class_required_membership(array $class): string
{
    $premiumTypes = ['HIIT', 'Spinning', 'Boxing'];
    $classType = trim((string)($class['Type'] ?? ''));

    return in_array($classType, $premiumTypes, true) ? 'Premium' : 'Basic';
}

function membership_upgrade_message(string $minimumTier, string $feature): string
{
    return membership_tier_label($minimumTier) . ' membership is required to access ' . $feature . '.';
}
