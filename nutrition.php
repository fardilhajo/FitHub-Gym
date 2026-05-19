<?php
session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/security.php';

$user = current_user($db);
if (!$user || $user['Role'] !== 'Member') {
    redirect_to('login.php');
}

$memberID = (int)$user['UserID'];

// ── All active plans assigned to this member ───────────────────────────────────
$plansStmt = $db->prepare('
    SELECT np.*, u.Name AS TrainerName,
           COUNT(m.MealID) AS MealCount,
           COALESCE(SUM(m.Calories), 0) AS TotalCalories
    FROM NUTRITION_PLAN np
    JOIN USER u ON np.TrainerID = u.UserID
    LEFT JOIN MEAL m ON m.PlanID = np.PlanID
    WHERE np.MemberID = :mid AND np.IsActive = 1
    GROUP BY np.PlanID
    ORDER BY np.CreatedAt DESC
');
$plansStmt->execute([':mid' => $memberID]);
$plans = $plansStmt->fetchAll();

// ── Load meals for each plan ───────────────────────────────────────────────────
$planMeals = [];
foreach ($plans as $plan) {
    $mStmt = $db->prepare('SELECT * FROM MEAL WHERE PlanID = :pid ORDER BY CASE MealType WHEN "Breakfast" THEN 1 WHEN "Lunch" THEN 2 WHEN "Dinner" THEN 3 ELSE 4 END, MealID');
    $mStmt->execute([':pid' => $plan['PlanID']]);
    $planMeals[$plan['PlanID']] = $mStmt->fetchAll();
}

$mealTypes = ['Breakfast', 'Lunch', 'Dinner', 'Snack'];
$mealTypeColors = [
    'Breakfast' => 'breakfast',
    'Lunch'     => 'lunch',
    'Dinner'    => 'dinner',
    'Snack'     => 'snack',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Nutrition – FitHub</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Crimson+Pro:wght@600;700&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/reset.css">
  <link rel="stylesheet" href="css/navbar.css">
  <link rel="stylesheet" href="css/pages/nutrition.css">
</head>
<body>
  <?php include __DIR__ . '/includes/navbar.php'; ?>

  <main class="nutrition-container">

    <header class="nutrition-header">
      <p class="eyebrow">Member Area</p>
      <h1>My Nutrition Plans</h1>
      <p class="subtitle">Personalised plans created by your trainer. Follow them to reach your goals faster.</p>
    </header>

    <?php if (!$plans): ?>
      <div class="panel empty-panel">
        <p class="empty-state">No nutrition plans assigned to you yet. Ask your trainer to create one.</p>
      </div>
    <?php endif; ?>

    <?php foreach ($plans as $plan):
      $meals   = $planMeals[$plan['PlanID']] ?? [];
      $grouped = [];
      foreach ($meals as $m) $grouped[$m['MealType']][] = $m;
      $totalCals   = array_sum(array_column($meals, 'Calories'));
      $totalProtein = array_sum(array_filter(array_column($meals, 'ProteinG'), fn($v) => $v !== null));
      $totalCarbs  = array_sum(array_filter(array_column($meals, 'CarbsG'), fn($v) => $v !== null));
      $totalFat    = array_sum(array_filter(array_column($meals, 'FatG'), fn($v) => $v !== null));
      $target      = (int)$plan['DailyCalorieTarget'];
      $pct         = $target > 0 ? min(100, round(($totalCals / $target) * 100)) : null;
    ?>
    <section class="panel plan-section">

      <div class="plan-section-header">
        <div>
          <h2><?= e($plan['Title']) ?></h2>
          <p class="plan-trainer">By <strong><?= e($plan['TrainerName']) ?></strong></p>
          <?php if ($plan['Description']): ?>
            <p class="plan-desc"><?= e($plan['Description']) ?></p>
          <?php endif; ?>
        </div>
        <?php if ($target): ?>
        <div class="calorie-gauge">
          <span class="gauge-value"><?= number_format($totalCals) ?> <small>/ <?= number_format($target) ?> kcal</small></span>
          <div class="progress-bar">
            <div class="progress-fill <?= $totalCals > $target ? 'over' : '' ?>" style="width:<?= $pct ?>%"></div>
          </div>
          <span class="gauge-label"><?= $pct ?>% of daily target</span>
        </div>
        <?php endif; ?>
      </div>

      <!-- Macro summary -->
      <?php if ($meals): ?>
      <div class="macro-summary">
        <div class="macro-chip macro-chip--cal"><?= number_format($totalCals) ?> kcal</div>
        <?php if ($totalProtein > 0): ?><div class="macro-chip macro-chip--p">Protein <?= round($totalProtein) ?>g</div><?php endif; ?>
        <?php if ($totalCarbs > 0): ?><div class="macro-chip macro-chip--c">Carbs <?= round($totalCarbs) ?>g</div><?php endif; ?>
        <?php if ($totalFat > 0): ?><div class="macro-chip macro-chip--f">Fat <?= round($totalFat) ?>g</div><?php endif; ?>
      </div>

      <!-- Meals -->
      <?php foreach ($mealTypes as $mt):
        if (empty($grouped[$mt])) continue; ?>
        <div class="meal-group">
          <h3 class="meal-type-title meal-type-title--<?= $mealTypeColors[$mt] ?>"><?= $mt ?></h3>
          <div class="meal-cards">
            <?php foreach ($grouped[$mt] as $meal): ?>
            <article class="meal-card">
              <div class="meal-card-top">
                <span class="meal-name"><?= e($meal['Name']) ?></span>
                <span class="meal-cal"><?= number_format((int)$meal['Calories']) ?> kcal</span>
              </div>
              <div class="macros">
                <?php if ($meal['ProteinG'] !== null): ?><span class="macro macro--p">P <?= $meal['ProteinG'] ?>g</span><?php endif; ?>
                <?php if ($meal['CarbsG'] !== null): ?><span class="macro macro--c">C <?= $meal['CarbsG'] ?>g</span><?php endif; ?>
                <?php if ($meal['FatG'] !== null): ?><span class="macro macro--f">F <?= $meal['FatG'] ?>g</span><?php endif; ?>
              </div>
              <?php if ($meal['Notes']): ?>
                <p class="meal-notes"><?= e($meal['Notes']) ?></p>
              <?php endif; ?>
            </article>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>
      <?php else: ?>
        <p class="empty-state">Your trainer hasn't added any meals to this plan yet.</p>
      <?php endif; ?>

    </section>
    <?php endforeach; ?>

  </main>

  <footer class="main-footer">
    <div class="footer-content">
      <p>&copy; 2026 FitHub Gym. Move your body, change your life.</p>
      <div class="social-links"><a href="#">Instagram</a><a href="#">Facebook</a></div>
    </div>
  </footer>
  <script src="js/navbar.js"></script>
</body>
</html>
