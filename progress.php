<?php
session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/security.php';

$user = current_user($db);
if (!$user || $user['Role'] !== 'Member') {
    redirect_to('login.php');
}

$memberID = (int)$user['UserID'];
$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $action = $_POST['action'] ?? '';

    if ($action === 'log_workout') {
        $logDate     = $_POST['log_date'] ?? date('Y-m-d');
        $type        = trim($_POST['workout_type'] ?? '');
        $duration    = (int)($_POST['duration'] ?? 0);
        $calories    = $_POST['calories'] !== '' ? (int)$_POST['calories'] : null;
        $notes       = trim($_POST['notes'] ?? '');

        if ($type && $duration > 0) {
            $stmt = $db->prepare('INSERT INTO WORKOUT_LOG (MemberID, LogDate, WorkoutType, DurationMinutes, CaloriesBurned, Notes) VALUES (:m, :d, :t, :dur, :cal, :n)');
            $stmt->execute([':m' => $memberID, ':d' => $logDate, ':t' => $type, ':dur' => $duration, ':cal' => $calories, ':n' => $notes ?: null]);
            $_SESSION['flash'] = 'Workout logged successfully.';
        } else {
            $_SESSION['flash'] = 'Please fill in all required fields.';
        }

    } elseif ($action === 'add_goal') {
        $title    = trim($_POST['goal_title'] ?? '');
        $goalType = $_POST['goal_type'] ?? '';
        $target   = (float)($_POST['target_value'] ?? 0);
        $unit     = trim($_POST['unit'] ?? '');
        $deadline = $_POST['deadline'] ?? null;

        if ($title && $goalType && $target > 0 && $unit) {
            $stmt = $db->prepare('INSERT INTO FITNESS_GOAL (MemberID, Title, GoalType, TargetValue, Unit, Deadline) VALUES (:m, :ti, :gt, :tv, :u, :dl)');
            $stmt->execute([':m' => $memberID, ':ti' => $title, ':gt' => $goalType, ':tv' => $target, ':u' => $unit, ':dl' => $deadline ?: null]);
            $_SESSION['flash'] = 'Goal added successfully.';
        } else {
            $_SESSION['flash'] = 'Please fill in all required goal fields.';
        }

    } elseif ($action === 'update_goal') {
        $goalID  = (int)($_POST['goal_id'] ?? 0);
        $current = (float)($_POST['current_value'] ?? 0);
        $status  = $_POST['status'] ?? 'Active';

        $stmt = $db->prepare('UPDATE FITNESS_GOAL SET CurrentValue = :cv, Status = :s WHERE GoalID = :id AND MemberID = :m');
        $stmt->execute([':cv' => $current, ':s' => $status, ':id' => $goalID, ':m' => $memberID]);
        $_SESSION['flash'] = 'Goal updated.';

    } elseif ($action === 'delete_log') {
        $logID = (int)($_POST['log_id'] ?? 0);
        $stmt = $db->prepare('DELETE FROM WORKOUT_LOG WHERE LogID = :id AND MemberID = :m');
        $stmt->execute([':id' => $logID, ':m' => $memberID]);
        $_SESSION['flash'] = 'Workout removed.';

    } elseif ($action === 'delete_goal') {
        $goalID = (int)($_POST['goal_id'] ?? 0);
        $stmt = $db->prepare('DELETE FROM FITNESS_GOAL WHERE GoalID = :id AND MemberID = :m');
        $stmt->execute([':id' => $goalID, ':m' => $memberID]);
        $_SESSION['flash'] = 'Goal removed.';
    }

    redirect_to('progress.php');
}

// ── Stats ──────────────────────────────────────────────────────────────────────
$stats = $db->prepare('
    SELECT
        COUNT(*) AS total_workouts,
        COALESCE(SUM(DurationMinutes), 0) AS total_minutes,
        COALESCE(SUM(CaloriesBurned), 0) AS total_calories
    FROM WORKOUT_LOG WHERE MemberID = :m
');
$stats->execute([':m' => $memberID]);
$totals = $stats->fetch();

// Avg duration (only sessions with duration > 0, which is all of them by constraint)
$avgStmt = $db->prepare('SELECT ROUND(AVG(DurationMinutes)) AS avg_dur FROM WORKOUT_LOG WHERE MemberID = :m');
$avgStmt->execute([':m' => $memberID]);
$avgDur = (int)$avgStmt->fetchColumn();

// ── Chart data: workouts per week for last 8 weeks ─────────────────────────────
$weeklyStmt = $db->prepare("
    SELECT
        strftime('%Y-W%W', LogDate) AS week_label,
        COUNT(*) AS count,
        COALESCE(SUM(DurationMinutes), 0) AS total_min
    FROM WORKOUT_LOG
    WHERE MemberID = :m AND LogDate >= date('now', '-56 days')
    GROUP BY week_label
    ORDER BY week_label
");
$weeklyStmt->execute([':m' => $memberID]);
$weeklyRaw = $weeklyStmt->fetchAll();

// Build last 8 ISO weeks so gaps show as 0
$weekLabels  = [];
$weekCounts  = [];
$weekMinutes = [];
for ($i = 7; $i >= 0; $i--) {
    $ts    = strtotime("-{$i} weeks");
    $label = date('Y-W', $ts) . sprintf('%02d', (int)date('W', $ts));
    $label = date('Y', $ts) . '-W' . sprintf('%02d', (int)date('W', $ts));
    $weekLabels[]  = 'W' . sprintf('%02d', (int)date('W', $ts));
    $weekCounts[]  = 0;
    $weekMinutes[] = 0;
}
foreach ($weeklyRaw as $row) {
    foreach ($weekLabels as $idx => $wl) {
        $wNum = (int)substr($wl, 1);
        if ((int)substr($row['week_label'], -2) === $wNum) {
            $weekCounts[$idx]  = (int)$row['count'];
            $weekMinutes[$idx] = (int)$row['total_min'];
        }
    }
}

// ── Goals ─────────────────────────────────────────────────────────────────────
$goalsStmt = $db->prepare('SELECT * FROM FITNESS_GOAL WHERE MemberID = :m ORDER BY Status = "Active" DESC, CreatedAt DESC');
$goalsStmt->execute([':m' => $memberID]);
$goals = $goalsStmt->fetchAll();

// ── Recent workout log ─────────────────────────────────────────────────────────
$logsStmt = $db->prepare('SELECT * FROM WORKOUT_LOG WHERE MemberID = :m ORDER BY LogDate DESC, CreatedAt DESC LIMIT 20');
$logsStmt->execute([':m' => $memberID]);
$logs = $logsStmt->fetchAll();

$workoutTypes = ['Cardio', 'Strength', 'HIIT', 'Yoga', 'Pilates', 'Spinning', 'Boxing', 'Recovery', 'Other'];
$goalTypes    = ['Workouts' => 'Number of sessions', 'Duration' => 'Total time (min)', 'Calories' => 'Calories burned', 'Custom' => 'Custom target'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>FitHub Gym - My Progress</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Crimson+Pro:wght@600;700&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/reset.css">
  <link rel="stylesheet" href="css/navbar.css">
  <link rel="stylesheet" href="css/pages/progress.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body>
  <?php include __DIR__ . '/includes/navbar.php'; ?>

  <main class="content">

    <header class="page-header">
      <h1>My Progress</h1>
      <p>Log your workouts, set goals, and watch your consistency compound.</p>
    </header>

    <?php if ($flash): ?>
      <p class="page-message"><?= e($flash) ?></p>
    <?php endif; ?>

    <!-- ── Stats ─────────────────────────────────────────────────────────────── -->
    <section class="stats-grid">
      <div class="stat-card">
        <span class="stat-value"><?= (int)$totals['total_workouts'] ?></span>
        <span class="stat-label">Total Workouts</span>
      </div>
      <div class="stat-card">
        <span class="stat-value"><?= (int)$totals['total_minutes'] ?></span>
        <span class="stat-label">Total Minutes</span>
      </div>
      <div class="stat-card">
        <span class="stat-value"><?= number_format((int)$totals['total_calories']) ?></span>
        <span class="stat-label">Calories Burned</span>
      </div>
      <div class="stat-card">
        <span class="stat-value"><?= $avgDur ?: '—' ?></span>
        <span class="stat-label">Avg. Duration (min)</span>
      </div>
    </section>

    <!-- ── Chart ─────────────────────────────────────────────────────────────── -->
    <section class="chart-section panel">
      <h2 class="panel-title">Workouts — last 8 weeks</h2>
      <div class="chart-wrapper">
        <canvas id="weeklyChart"></canvas>
      </div>
    </section>

    <div class="two-col">

      <!-- ── Log a Workout ──────────────────────────────────────────────────── -->
      <section class="panel">
        <h2 class="panel-title">Log a Workout</h2>
        <form method="post" action="progress.php" class="progress-form">
          <?= csrf_input() ?>
          <input type="hidden" name="action" value="log_workout">

          <div class="form-row">
            <label>Date</label>
            <input type="date" name="log_date" value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>" required>
          </div>
          <div class="form-row">
            <label>Type</label>
            <select name="workout_type" required>
              <option value="">Select type</option>
              <?php foreach ($workoutTypes as $wt): ?>
                <option value="<?= e($wt) ?>"><?= e($wt) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-row">
            <label>Duration (min)</label>
            <input type="number" name="duration" min="1" max="600" placeholder="e.g. 45" required>
          </div>
          <div class="form-row">
            <label>Calories burned</label>
            <input type="number" name="calories" min="0" max="9999" placeholder="optional">
          </div>
          <div class="form-row">
            <label>Notes</label>
            <input type="text" name="notes" placeholder="optional" maxlength="255">
          </div>
          <button type="submit" class="btn-primary">Log Workout</button>
        </form>
      </section>

      <!-- ── Add a Goal ─────────────────────────────────────────────────────── -->
      <section class="panel">
        <h2 class="panel-title">Add a Goal</h2>
        <form method="post" action="progress.php" class="progress-form">
          <?= csrf_input() ?>
          <input type="hidden" name="action" value="add_goal">

          <div class="form-row">
            <label>Title</label>
            <input type="text" name="goal_title" placeholder="e.g. Work out 20 times" maxlength="120" required>
          </div>
          <div class="form-row">
            <label>Type</label>
            <select name="goal_type" required>
              <option value="">Select type</option>
              <?php foreach ($goalTypes as $key => $label): ?>
                <option value="<?= e($key) ?>"><?= e($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-row">
            <label>Target value</label>
            <input type="number" name="target_value" min="1" step="any" placeholder="e.g. 20" required>
          </div>
          <div class="form-row">
            <label>Unit</label>
            <input type="text" name="unit" placeholder="e.g. sessions, min, kcal" maxlength="30" required>
          </div>
          <div class="form-row">
            <label>Deadline</label>
            <input type="date" name="deadline" min="<?= date('Y-m-d') ?>">
          </div>
          <button type="submit" class="btn-primary">Add Goal</button>
        </form>
      </section>

    </div>

    <!-- ── Goals ─────────────────────────────────────────────────────────────── -->
    <?php if ($goals): ?>
    <section class="panel">
      <h2 class="panel-title">My Goals</h2>
      <div class="goals-list">
        <?php foreach ($goals as $goal):
            $pct = $goal['TargetValue'] > 0 ? min(100, round(($goal['CurrentValue'] / $goal['TargetValue']) * 100)) : 0;
            $isActive = $goal['Status'] === 'Active';
        ?>
        <article class="goal-card <?= !$isActive ? 'goal-card--done' : '' ?>">
          <div class="goal-header">
            <div>
              <h3><?= e($goal['Title']) ?></h3>
              <?php if ($goal['Deadline']): ?>
                <span class="goal-deadline">Due <?= e($goal['Deadline']) ?></span>
              <?php endif; ?>
            </div>
            <div class="goal-badges">
              <span class="badge badge--<?= strtolower($goal['Status']) ?>"><?= e($goal['Status']) ?></span>
              <form method="post" action="progress.php" class="inline-form">
                <?= csrf_input() ?>
                <input type="hidden" name="action" value="delete_goal">
                <input type="hidden" name="goal_id" value="<?= (int)$goal['GoalID'] ?>">
                <button type="submit" class="btn-icon" title="Remove goal" onclick="return confirm('Remove this goal?')">✕</button>
              </form>
            </div>
          </div>
          <div class="goal-progress">
            <div class="progress-bar">
              <div class="progress-fill" style="width: <?= $pct ?>%"></div>
            </div>
            <span class="progress-label"><?= e($goal['CurrentValue']) ?> / <?= e($goal['TargetValue']) ?> <?= e($goal['Unit']) ?> (<?= $pct ?>%)</span>
          </div>
          <?php if ($isActive): ?>
          <form method="post" action="progress.php" class="goal-update-form">
            <?= csrf_input() ?>
            <input type="hidden" name="action" value="update_goal">
            <input type="hidden" name="goal_id" value="<?= (int)$goal['GoalID'] ?>">
            <input type="number" name="current_value" value="<?= e($goal['CurrentValue']) ?>" step="any" min="0" class="goal-input">
            <select name="status" class="goal-select">
              <option value="Active" <?= $goal['Status'] === 'Active' ? 'selected' : '' ?>>Active</option>
              <option value="Completed" <?= $goal['Status'] === 'Completed' ? 'selected' : '' ?>>Completed</option>
              <option value="Abandoned" <?= $goal['Status'] === 'Abandoned' ? 'selected' : '' ?>>Abandoned</option>
            </select>
            <button type="submit" class="btn-secondary">Update</button>
          </form>
          <?php endif; ?>
        </article>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>

    <!-- ── Workout History ───────────────────────────────────────────────────── -->
    <section class="panel">
      <h2 class="panel-title">Workout History</h2>
      <?php if (!$logs): ?>
        <p class="empty-state">No workouts logged yet. Start by logging your first session above.</p>
      <?php else: ?>
      <div class="log-table-wrapper">
        <table class="log-table">
          <thead>
            <tr>
              <th>Date</th>
              <th>Type</th>
              <th>Duration</th>
              <th>Calories</th>
              <th>Notes</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($logs as $log): ?>
            <tr>
              <td><?= e($log['LogDate']) ?></td>
              <td><span class="type-pill type-<?= strtolower(e($log['WorkoutType'])) ?>"><?= e($log['WorkoutType']) ?></span></td>
              <td><?= (int)$log['DurationMinutes'] ?> min</td>
              <td><?= $log['CaloriesBurned'] !== null ? number_format((int)$log['CaloriesBurned']) . ' kcal' : '—' ?></td>
              <td class="notes-cell"><?= $log['Notes'] ? e($log['Notes']) : '—' ?></td>
              <td>
                <form method="post" action="progress.php" class="inline-form">
                  <?= csrf_input() ?>
                  <input type="hidden" name="action" value="delete_log">
                  <input type="hidden" name="log_id" value="<?= (int)$log['LogID'] ?>">
                  <button type="submit" class="btn-icon" title="Remove" onclick="return confirm('Remove this workout?')">✕</button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </section>

  </main>

  <footer class="main-footer">
    <div class="footer-content">
      <p>&copy; 2026 FitHub Gym. Move your body, change your life.</p>
      <div class="social-links"><a href="#">Instagram</a><a href="#">Facebook</a></div>
    </div>
  </footer>

  <script src="js/navbar.js"></script>
  <script>
    const ctx = document.getElementById('weeklyChart').getContext('2d');
    new Chart(ctx, {
      type: 'bar',
      data: {
        labels: <?= json_encode($weekLabels) ?>,
        datasets: [
          {
            label: 'Sessions',
            data: <?= json_encode($weekCounts) ?>,
            backgroundColor: 'rgba(241,140,79,0.75)',
            borderRadius: 8,
            yAxisID: 'y',
          },
          {
            label: 'Minutes',
            data: <?= json_encode($weekMinutes) ?>,
            type: 'line',
            borderColor: '#d87236',
            backgroundColor: 'rgba(216,114,54,0.1)',
            tension: 0.4,
            pointBackgroundColor: '#d87236',
            pointRadius: 4,
            fill: true,
            yAxisID: 'y1',
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: { legend: { position: 'top' } },
        scales: {
          y:  { beginAtZero: true, ticks: { stepSize: 1 }, title: { display: true, text: 'Sessions' } },
          y1: { beginAtZero: true, position: 'right', grid: { drawOnChartArea: false }, title: { display: true, text: 'Minutes' } }
        }
      }
    });
  </script>
</body>
</html>
