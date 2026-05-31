<?php
session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/security.php';

require_login();
$user = current_user($db);
if (!$user || !in_array($user['Role'], ['Trainer', 'Admin'])) {
    redirect_to('index.php');
}

$trainerID = (int)$user['UserID'];
$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

// ── POST actions ───────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create_plan') {
        $memberID  = (int)($_POST['member_id'] ?? 0);
        $title     = trim($_POST['title'] ?? '');
        $desc      = trim($_POST['description'] ?? '');
        $calories  = $_POST['calorie_target'] !== '' ? (int)$_POST['calorie_target'] : null;

        if ($memberID && $title) {
            $stmt = $db->prepare('INSERT INTO NUTRITION_PLAN (TrainerID, MemberID, Title, Description, DailyCalorieTarget) VALUES (:t, :m, :ti, :d, :c)');
            $stmt->execute([':t' => $trainerID, ':m' => $memberID, ':ti' => $title, ':d' => $desc ?: null, ':c' => $calories]);
            $_SESSION['flash'] = 'Plan created successfully.';
        } else {
            $_SESSION['flash'] = 'Please fill in all required fields.';
        }
        redirect_to('trainer-nutrition.php');

    } elseif ($action === 'add_meal') {
        $planID   = (int)($_POST['plan_id'] ?? 0);
        $name     = trim($_POST['meal_name'] ?? '');
        $type     = $_POST['meal_type'] ?? '';
        $calories = (int)($_POST['calories'] ?? 0);
        $protein  = $_POST['protein'] !== '' ? (float)$_POST['protein'] : null;
        $carbs    = $_POST['carbs'] !== '' ? (float)$_POST['carbs'] : null;
        $fat      = $_POST['fat'] !== '' ? (float)$_POST['fat'] : null;
        $notes    = trim($_POST['notes'] ?? '');

        // Verify this plan belongs to this trainer
        $check = $db->prepare('SELECT PlanID FROM NUTRITION_PLAN WHERE PlanID = :id AND TrainerID = :t');
        $check->execute([':id' => $planID, ':t' => $trainerID]);

        if ($name && $type && $check->fetch()) {
            $stmt = $db->prepare('INSERT INTO MEAL (PlanID, Name, MealType, Calories, ProteinG, CarbsG, FatG, Notes) VALUES (:p, :n, :mt, :c, :pr, :ca, :f, :no)');
            $stmt->execute([':p' => $planID, ':n' => $name, ':mt' => $type, ':c' => $calories, ':pr' => $protein, ':ca' => $carbs, ':f' => $fat, ':no' => $notes ?: null]);
            $_SESSION['flash'] = 'Meal added.';
        } else {
            $_SESSION['flash'] = 'Could not add meal.';
        }
        redirect_to('trainer-nutrition.php?plan_id=' . $planID);

    } elseif ($action === 'delete_meal') {
        $mealID = (int)($_POST['meal_id'] ?? 0);
        $planID = (int)($_POST['plan_id'] ?? 0);
        $stmt = $db->prepare('DELETE FROM MEAL WHERE MealID = :id AND PlanID IN (SELECT PlanID FROM NUTRITION_PLAN WHERE TrainerID = :t)');
        $stmt->execute([':id' => $mealID, ':t' => $trainerID]);
        $_SESSION['flash'] = 'Meal removed.';
        redirect_to('trainer-nutrition.php?plan_id=' . $planID);

    } elseif ($action === 'delete_plan') {
        $planID = (int)($_POST['plan_id'] ?? 0);
        $stmt = $db->prepare('DELETE FROM NUTRITION_PLAN WHERE PlanID = :id AND TrainerID = :t');
        $stmt->execute([':id' => $planID, ':t' => $trainerID]);
        $_SESSION['flash'] = 'Plan deleted.';
        redirect_to('trainer-nutrition.php');

    } elseif ($action === 'toggle_plan') {
        $planID = (int)($_POST['plan_id'] ?? 0);
        $stmt = $db->prepare('UPDATE NUTRITION_PLAN SET IsActive = 1 - IsActive WHERE PlanID = :id AND TrainerID = :t');
        $stmt->execute([':id' => $planID, ':t' => $trainerID]);
        redirect_to('trainer-nutrition.php');
    }
}

// ── Active plan detail ─────────────────────────────────────────────────────────
$activePlanID = (int)($_GET['plan_id'] ?? 0);
$activePlan   = null;
$meals        = [];

if ($activePlanID) {
    $planStmt = $db->prepare('
        SELECT np.*, u.Name AS MemberName
        FROM NUTRITION_PLAN np
        JOIN USER u ON np.MemberID = u.UserID
        WHERE np.PlanID = :id AND np.TrainerID = :t
    ');
    $planStmt->execute([':id' => $activePlanID, ':t' => $trainerID]);
    $activePlan = $planStmt->fetch();

    if ($activePlan) {
        $mealStmt = $db->prepare('SELECT * FROM MEAL WHERE PlanID = :id ORDER BY CASE MealType WHEN "Breakfast" THEN 1 WHEN "Lunch" THEN 2 WHEN "Dinner" THEN 3 ELSE 4 END, MealID');
        $mealStmt->execute([':id' => $activePlanID]);
        $meals = $mealStmt->fetchAll();
    }
}

// ── All plans by this trainer ──────────────────────────────────────────────────
$plansStmt = $db->prepare('
    SELECT np.*, u.Name AS MemberName,
           COUNT(m.MealID) AS MealCount,
           COALESCE(SUM(m.Calories), 0) AS TotalCalories
    FROM NUTRITION_PLAN np
    JOIN USER u ON np.MemberID = u.UserID
    LEFT JOIN MEAL m ON m.PlanID = np.PlanID
    WHERE np.TrainerID = :t
    GROUP BY np.PlanID
    ORDER BY np.IsActive DESC, np.CreatedAt DESC
');
$plansStmt->execute([':t' => $trainerID]);
$plans = $plansStmt->fetchAll();

// ── All active members ────────────────────────────────────────────────────────
$membersStmt = $db->prepare('SELECT UserID, Name FROM USER WHERE Role = "Member" AND IsActive = 1 ORDER BY Name');
$membersStmt->execute();
$members = $membersStmt->fetchAll();

$mealTypes = ['Breakfast', 'Lunch', 'Dinner', 'Snack'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Nutrition Plans – FitHub</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Crimson+Pro:wght@600;700&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/reset.css">
  <link rel="stylesheet" href="css/base.css">
  <link rel="stylesheet" href="css/components.css">
  <link rel="stylesheet" href="css/navbar.css">
  <link rel="stylesheet" href="css/pages/nutrition.css">
</head>
<body>
  <?php include __DIR__ . '/includes/navbar.php'; ?>

  <main class="nutrition-container">

    <header class="nutrition-header">
      <p class="eyebrow">Trainer Dashboard</p>
      <h1>Nutrition Plans</h1>
      <p class="subtitle">Create and manage personalised nutrition plans for your members.</p>
    </header>

    <?php if ($flash): ?>
      <p class="page-message"><?= e($flash) ?></p>
    <?php endif; ?>

    <?php if ($activePlan): ?>
    <!-- ── Plan detail view ──────────────────────────────────────────────────── -->
    <a href="trainer-nutrition.php" class="back-link">← All plans</a>

    <section class="plan-detail panel">
      <div class="plan-detail-header">
        <div>
          <h2><?= e($activePlan['Title']) ?></h2>
          <p class="plan-member">Assigned to <strong><?= e($activePlan['MemberName']) ?></strong></p>
          <?php if ($activePlan['Description']): ?>
            <p class="plan-desc"><?= e($activePlan['Description']) ?></p>
          <?php endif; ?>
        </div>
        <div class="plan-meta">
          <?php if ($activePlan['DailyCalorieTarget']): ?>
            <div class="meta-chip">Target: <?= number_format((int)$activePlan['DailyCalorieTarget']) ?> kcal/day</div>
          <?php endif; ?>
          <span class="badge badge--<?= $activePlan['IsActive'] ? 'active' : 'inactive' ?>"><?= $activePlan['IsActive'] ? 'Active' : 'Inactive' ?></span>
        </div>
      </div>

      <!-- Meals grouped by type -->
      <?php
        $grouped = [];
        foreach ($meals as $meal) $grouped[$meal['MealType']][] = $meal;
        $dayTotal = array_sum(array_column($meals, 'Calories'));
      ?>

      <?php if ($meals): ?>
        <div class="day-total">
          Total plan calories: <strong><?= number_format($dayTotal) ?> kcal</strong>
          <?php if ($activePlan['DailyCalorieTarget']): ?>
            <span class="day-diff <?= $dayTotal <= $activePlan['DailyCalorieTarget'] ? 'ok' : 'over' ?>">
              (<?= $dayTotal <= $activePlan['DailyCalorieTarget'] ? '-' : '+' ?><?= number_format(abs($dayTotal - $activePlan['DailyCalorieTarget'])) ?> vs target)
            </span>
          <?php endif; ?>
        </div>

        <?php foreach ($mealTypes as $mt):
          if (empty($grouped[$mt])) continue; ?>
          <div class="meal-group">
            <h3 class="meal-type-title"><?= $mt ?></h3>
            <div class="meal-cards">
              <?php foreach ($grouped[$mt] as $meal): ?>
              <article class="meal-card">
                <div class="meal-card-top">
                  <span class="meal-name"><?= e($meal['Name']) ?></span>
                  <div class="meal-card-actions">
                    <span class="meal-cal"><?= number_format((int)$meal['Calories']) ?> kcal</span>
                    <form method="post" action="trainer-nutrition.php?plan_id=<?= $activePlanID ?>" class="inline-form">
                      <?= csrf_input() ?>
                      <input type="hidden" name="action" value="delete_meal">
                      <input type="hidden" name="meal_id" value="<?= (int)$meal['MealID'] ?>">
                      <input type="hidden" name="plan_id" value="<?= $activePlanID ?>">
                      <button type="submit" class="btn-icon" onclick="return confirm('Remove this meal?')">✕</button>
                    </form>
                  </div>
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
        <p class="empty-state">No meals added yet. Use the form below to add the first one.</p>
      <?php endif; ?>

      <!-- Add meal form -->
      <div class="add-meal-section">
        <h3 class="panel-title">Add Meal</h3>
        <form method="post" action="trainer-nutrition.php?plan_id=<?= $activePlanID ?>" class="meal-form">
          <?= csrf_input() ?>
          <input type="hidden" name="action" value="add_meal">
          <input type="hidden" name="plan_id" value="<?= $activePlanID ?>">

          <div class="form-grid">
            <div class="form-row span-2">
              <label>Meal name *</label>
              <input type="text" name="meal_name" placeholder="e.g. Grilled chicken & rice" maxlength="120" required>
            </div>
            <div class="form-row">
              <label>Type *</label>
              <select name="meal_type" required>
                <option value="">Select</option>
                <?php foreach ($mealTypes as $mt): ?>
                  <option value="<?= $mt ?>"><?= $mt ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-row">
              <label>Calories (kcal) *</label>
              <input type="number" name="calories" min="0" max="9999" placeholder="e.g. 450" required>
            </div>
            <div class="form-row">
              <label>Protein (g)</label>
              <input type="number" name="protein" min="0" step="0.1" placeholder="optional">
            </div>
            <div class="form-row">
              <label>Carbs (g)</label>
              <input type="number" name="carbs" min="0" step="0.1" placeholder="optional">
            </div>
            <div class="form-row">
              <label>Fat (g)</label>
              <input type="number" name="fat" min="0" step="0.1" placeholder="optional">
            </div>
            <div class="form-row span-2">
              <label>Notes</label>
              <input type="text" name="notes" placeholder="optional preparation notes" maxlength="255">
            </div>
          </div>
          <button type="submit" class="btn-primary">Add Meal</button>
        </form>
      </div>

      <form method="post" action="trainer-nutrition.php" class="danger-zone">
        <?= csrf_input() ?>
        <input type="hidden" name="action" value="delete_plan">
        <input type="hidden" name="plan_id" value="<?= $activePlanID ?>">
        <button type="submit" class="btn-danger" onclick="return confirm('Delete this entire plan and all its meals?')">Delete Plan</button>
      </form>
    </section>

    <?php else: ?>
    <!-- ── Plans list + create form ─────────────────────────────────────────── -->
    <div class="two-col">

      <!-- Create new plan -->
      <section class="panel">
        <h2 class="panel-title">Create New Plan</h2>
        <form method="post" action="trainer-nutrition.php" class="plan-form">
          <?= csrf_input() ?>
          <input type="hidden" name="action" value="create_plan">

          <div class="form-row">
            <label>Assign to member *</label>
            <select name="member_id" required>
              <option value="">Select member</option>
              <?php foreach ($members as $m): ?>
                <option value="<?= (int)$m['UserID'] ?>" <?= (isset($_GET['member_id']) && (int)$_GET['member_id'] === (int)$m['UserID']) ? 'selected' : '' ?>><?= e($m['Name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-row">
            <label>Plan title *</label>
            <input type="text" name="title" placeholder="e.g. Clean Bulk Plan" maxlength="120" required>
          </div>
          <div class="form-row">
            <label>Description</label>
            <textarea name="description" rows="3" placeholder="Goals and general guidance..." maxlength="500"></textarea>
          </div>
          <div class="form-row">
            <label>Daily calorie target (kcal)</label>
            <input type="number" name="calorie_target" min="500" max="9999" placeholder="e.g. 2000">
          </div>
          <button type="submit" class="btn-primary">Create Plan</button>
        </form>
      </section>

      <!-- Plans list -->
      <section class="panel">
        <h2 class="panel-title">My Plans (<?= count($plans) ?>)</h2>
        <?php if (!$plans): ?>
          <p class="empty-state">No plans created yet.</p>
        <?php else: ?>
        <div class="plans-list">
          <?php foreach ($plans as $plan): ?>
          <article class="plan-card <?= !$plan['IsActive'] ? 'plan-card--inactive' : '' ?>">
            <div class="plan-card-head">
              <div>
                <h3><?= e($plan['Title']) ?></h3>
                <p class="plan-card-member"><?= e($plan['MemberName']) ?></p>
              </div>
              <span class="badge badge--<?= $plan['IsActive'] ? 'active' : 'inactive' ?>"><?= $plan['IsActive'] ? 'Active' : 'Inactive' ?></span>
            </div>
            <div class="plan-card-stats">
              <span><?= (int)$plan['MealCount'] ?> meal<?= $plan['MealCount'] != 1 ? 's' : '' ?></span>
              <span><?= number_format((int)$plan['TotalCalories']) ?> kcal total</span>
              <?php if ($plan['DailyCalorieTarget']): ?>
                <span>Target: <?= number_format((int)$plan['DailyCalorieTarget']) ?> kcal/day</span>
              <?php endif; ?>
            </div>
            <div class="plan-card-actions">
              <a href="trainer-nutrition.php?plan_id=<?= (int)$plan['PlanID'] ?>" class="btn-secondary">Manage</a>
              <form method="post" action="trainer-nutrition.php" class="inline-form">
                <?= csrf_input() ?>
                <input type="hidden" name="action" value="toggle_plan">
                <input type="hidden" name="plan_id" value="<?= (int)$plan['PlanID'] ?>">
                <button type="submit" class="btn-ghost"><?= $plan['IsActive'] ? 'Deactivate' : 'Activate' ?></button>
              </form>
            </div>
          </article>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </section>

    </div>
    <?php endif; ?>

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
