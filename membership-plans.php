<?php
session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/feature-schema.php';

ensure_booking_feature_schema($db);
$user = current_user($db);
$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    if (!$user || $user['Role'] !== 'Member') {
        $_SESSION['flash'] = 'Please log in as a member before choosing a plan.';
        redirect_to('login.php');
    }

    $planId = (int)($_POST['plan_id'] ?? 0);
    $planStmt = $db->prepare('SELECT PlanID FROM MEMBERSHIP_PLAN WHERE PlanID = :id AND IsActive = 1');
    $planStmt->execute([':id' => $planId]);

    if ($planStmt->fetch()) {
        $stmt = $db->prepare('
            INSERT INTO MEMBER_MEMBERSHIP (MemberID, PlanID, Status)
            VALUES (:member, :plan, "Active")
            ON CONFLICT(MemberID) DO UPDATE SET PlanID = excluded.PlanID, Status = "Active", SubscribedAt = CURRENT_TIMESTAMP
        ');
        $stmt->execute([':member' => $user['UserID'], ':plan' => $planId]);
        $_SESSION['flash'] = 'Membership plan updated.';
    } else {
        $_SESSION['flash'] = 'That membership plan is not available.';
    }

    redirect_to('membership-plans.php');
}

$plans = $db->query('SELECT PlanID, Name, PriceMonthly, AccessLevel, Description FROM MEMBERSHIP_PLAN WHERE IsActive = 1 ORDER BY PriceMonthly')->fetchAll();
$currentPlanId = null;
if ($user) {
    $stmt = $db->prepare('SELECT PlanID FROM MEMBER_MEMBERSHIP WHERE MemberID = :member AND Status = "Active"');
    $stmt->execute([':member' => $user['UserID']]);
    $currentPlanId = $stmt->fetchColumn() ?: null;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>FitHub Gym - Membership Plans</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Crimson+Pro:wght@600;700&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/reset.css">
  <link rel="stylesheet" href="css/navbar.css">
  <link rel="stylesheet" href="css/pages/booking.css">
</head>
<body>
  <?php include __DIR__ . '/includes/navbar.php'; ?>
  <main class="content">
    <header class="page-header">
      <h1>Membership Plans</h1>
      <p>Pick a tier, unlock access levels, and pretend subscriptions have not already conquered modern civilisation.</p>
    </header>

    <?php if ($flash): ?><p class="page-message"><?= e($flash) ?></p><?php endif; ?>

    <section class="classes-container">
      <div class="classes-grid feature-grid">
        <?php foreach ($plans as $plan): ?>
          <?php $isCurrent = (int)$currentPlanId === (int)$plan['PlanID']; ?>
          <article class="class-card plan-card <?= $isCurrent ? 'plan-card--current' : '' ?>">
            <div class="card-body">
              <span class="tag-type plan-tag"><?= $isCurrent ? 'Current Plan' : 'Membership' ?></span>
              <h3><?= e($plan['Name']) ?></h3>
              <p class="plan-price">€<?= number_format((float)$plan['PriceMonthly'], 2) ?><span>/month</span></p>
              <p class="class-description"><?= e($plan['Description']) ?></p>
              <div class="class-info">
                <span><strong>Access:</strong> <?= e($plan['AccessLevel']) ?></span>
              </div>
              <?php if (!$user): ?>
                <a class="btn-book" href="login.php">Login to subscribe</a>
              <?php elseif ($user['Role'] !== 'Member'): ?>
                <button class="btn-book" disabled>Members only</button>
              <?php elseif ($isCurrent): ?>
                <button class="btn-book" disabled>Active</button>
              <?php else: ?>
                <form method="post" action="membership-plans.php" class="inline-form">
                  <?= csrf_input() ?>
                  <input type="hidden" name="plan_id" value="<?= e($plan['PlanID']) ?>">
                  <button class="btn-book" type="submit"><?= $currentPlanId ? 'Upgrade / Switch' : 'Subscribe' ?></button>
                </form>
              <?php endif; ?>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    </section>
  </main>
  <script src="js/navbar.js"></script>
</body>
</html>
