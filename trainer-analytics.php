<?php
session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/security.php';

require_login();
$user = current_user($db);

if (!$user || !in_array($user['Role'], ['Trainer', 'Admin'])) {
    redirect_to('index.php');
}

$trainerId = (int)$user['UserID'];

// Summary stats
$summaryStmt = $db->prepare('
    SELECT
        COUNT(DISTINCT fc.ClassID)                                          AS TotalClasses,
        COUNT(CASE WHEN ce.Status = "Enrolled"  THEN 1 END)                AS TotalEnrolled,
        COUNT(CASE WHEN ce.Status = "Cancelled" THEN 1 END)                AS TotalCancelled,
        ROUND(AVG(cr.Rating), 1)                                           AS AvgRating,
        COUNT(cr.ReviewID)                                                  AS TotalReviews,
        SUM(fc.Capacity)                                                    AS TotalCapacity
    FROM FITNESS_CLASS fc
    LEFT JOIN CLASS_ENROLLMENT ce ON ce.ClassID = fc.ClassID
    LEFT JOIN CLASS_REVIEW cr     ON cr.ClassID = fc.ClassID
    WHERE fc.TrainerID = :id AND fc.IsActive = 1
');
$summaryStmt->execute([':id' => $trainerId]);
$summary = $summaryStmt->fetch();

$fillRate = ($summary['TotalCapacity'] > 0)
    ? round(($summary['TotalEnrolled'] / $summary['TotalCapacity']) * 100)
    : 0;

$totalActions = (int)$summary['TotalEnrolled'] + (int)$summary['TotalCancelled'];
$retentionRate = $totalActions > 0
    ? round(((int)$summary['TotalEnrolled'] / $totalActions) * 100)
    : 0;

// Unique members coached
$membersStmt = $db->prepare('
    SELECT COUNT(DISTINCT ce.MemberID) AS UniqueMembers
    FROM CLASS_ENROLLMENT ce
    JOIN FITNESS_CLASS fc ON ce.ClassID = fc.ClassID
    WHERE fc.TrainerID = :id AND ce.Status = "Enrolled"
');
$membersStmt->execute([':id' => $trainerId]);
$uniqueMembers = (int)$membersStmt->fetchColumn();

// Nutrition plans assigned by this trainer
$nutritionStmt = $db->prepare('
    SELECT COUNT(*) AS PlanCount FROM NUTRITION_PLAN WHERE TrainerID = :id AND IsActive = 1
');
$nutritionStmt->execute([':id' => $trainerId]);
$nutritionPlans = (int)$nutritionStmt->fetchColumn();

// Per-class breakdown
$classStmt = $db->prepare('
    SELECT
        fc.ClassID,
        fc.Name,
        fc.Type,
        fc.Day,
        fc.StartTime,
        fc.EndTime,
        fc.Capacity,
        COUNT(CASE WHEN ce.Status = "Enrolled"  THEN 1 END) AS EnrolledCount,
        COUNT(CASE WHEN ce.Status = "Cancelled" THEN 1 END) AS CancelledCount,
        ROUND(AVG(cr.Rating), 1)                             AS AvgRating,
        COUNT(cr.ReviewID)                                   AS ReviewCount
    FROM FITNESS_CLASS fc
    LEFT JOIN CLASS_ENROLLMENT ce ON ce.ClassID = fc.ClassID
    LEFT JOIN CLASS_REVIEW cr     ON cr.ClassID = fc.ClassID
    WHERE fc.TrainerID = :id AND fc.IsActive = 1
    GROUP BY fc.ClassID
    ORDER BY EnrolledCount DESC, fc.Day, fc.StartTime
');
$classStmt->execute([':id' => $trainerId]);
$classes = $classStmt->fetchAll();

// Rating distribution (1–5 stars)
$ratingDistStmt = $db->prepare('
    SELECT cr.Rating, COUNT(*) AS Count
    FROM CLASS_REVIEW cr
    JOIN FITNESS_CLASS fc ON cr.ClassID = fc.ClassID
    WHERE fc.TrainerID = :id
    GROUP BY cr.Rating
    ORDER BY cr.Rating
');
$ratingDistStmt->execute([':id' => $trainerId]);
$ratingRows = $ratingDistStmt->fetchAll();
$ratingDist = array_fill(1, 5, 0);
foreach ($ratingRows as $r) {
    $ratingDist[(int)$r['Rating']] = (int)$r['Count'];
}
$maxRatingCount = max($ratingDist) ?: 1;

// Enrollment by class type
$typeStmt = $db->prepare('
    SELECT fc.Type,
           COUNT(CASE WHEN ce.Status = "Enrolled" THEN 1 END) AS Enrolled
    FROM FITNESS_CLASS fc
    LEFT JOIN CLASS_ENROLLMENT ce ON ce.ClassID = fc.ClassID
    WHERE fc.TrainerID = :id AND fc.IsActive = 1
    GROUP BY fc.Type
    ORDER BY Enrolled DESC
');
$typeStmt->execute([':id' => $trainerId]);
$typeData = $typeStmt->fetchAll();

// Enrollment by day of week
$dayOrder = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
$dayStmt = $db->prepare('
    SELECT fc.Day,
           COUNT(CASE WHEN ce.Status = "Enrolled" THEN 1 END) AS Enrolled,
           COUNT(CASE WHEN ce.Status = "Cancelled" THEN 1 END) AS Cancelled
    FROM FITNESS_CLASS fc
    LEFT JOIN CLASS_ENROLLMENT ce ON ce.ClassID = fc.ClassID
    WHERE fc.TrainerID = :id AND fc.IsActive = 1
    GROUP BY fc.Day
');
$dayStmt->execute([':id' => $trainerId]);
$dayRows = $dayStmt->fetchAll(PDO::FETCH_ASSOC);
$dayMap = [];
foreach ($dayRows as $d) { $dayMap[$d['Day']] = $d; }
$dayEnrolled   = [];
$dayCancelled  = [];
$dayLabels     = [];
foreach ($dayOrder as $day) {
    if (isset($dayMap[$day])) {
        $dayLabels[]    = substr($day, 0, 3);
        $dayEnrolled[]  = (int)$dayMap[$day]['Enrolled'];
        $dayCancelled[] = (int)$dayMap[$day]['Cancelled'];
    }
}

// Recent reviews (last 8)
$reviewStmt = $db->prepare('
    SELECT cr.Rating, cr.Comment, cr.CreatedAt, fc.Name AS ClassName, u.Name AS MemberName
    FROM CLASS_REVIEW cr
    JOIN FITNESS_CLASS fc ON cr.ClassID = fc.ClassID
    JOIN USER u           ON cr.MemberID = u.UserID
    WHERE fc.TrainerID = :id
    ORDER BY cr.CreatedAt DESC
    LIMIT 8
');
$reviewStmt->execute([':id' => $trainerId]);
$recentReviews = $reviewStmt->fetchAll();

// JSON for charts
$typeLabels  = json_encode(array_column($typeData, 'Type'));
$typeValues  = json_encode(array_map(fn($r) => (int)$r['Enrolled'], $typeData));
$dayLabelsJ  = json_encode($dayLabels);
$dayEnrolledJ  = json_encode($dayEnrolled);
$dayCancelledJ = json_encode($dayCancelled);
$ratingValues  = json_encode(array_values($ratingDist));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Analytics – FitHub</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Crimson+Pro:wght@600;700&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/reset.css">
  <link rel="stylesheet" href="css/base.css">
  <link rel="stylesheet" href="css/components.css">
  <link rel="stylesheet" href="css/navbar.css">
  <link rel="stylesheet" href="css/pages/analytics.css">
</head>
<body>
  <?php include __DIR__ . '/includes/navbar.php'; ?>

  <main class="analytics-container">

    <header class="analytics-header">
      <div>
        <p class="analytics-eyebrow">Trainer Dashboard</p>
        <h1><?= e($user['Name']) ?></h1>
        <p class="analytics-subtitle">Here's how your classes are performing.</p>
      </div>
    </header>

    <!-- Summary cards -->
    <section class="summary-grid">
      <div class="stat-card">
        <span class="stat-icon">📋</span>
        <p class="stat-value"><?= (int)$summary['TotalClasses'] ?></p>
        <p class="stat-label">Active Classes</p>
      </div>
      <div class="stat-card">
        <span class="stat-icon">👥</span>
        <p class="stat-value"><?= $uniqueMembers ?></p>
        <p class="stat-label">Members Coached</p>
      </div>
      <div class="stat-card">
        <span class="stat-icon">📊</span>
        <p class="stat-value"><?= $fillRate ?>%</p>
        <p class="stat-label">Avg Fill Rate</p>
      </div>
      <div class="stat-card">
        <span class="stat-icon">⭐</span>
        <p class="stat-value"><?= $summary['AvgRating'] ?? '—' ?></p>
        <p class="stat-label">Overall Rating</p>
        <p class="stat-meta"><?= (int)$summary['TotalReviews'] ?> review<?= $summary['TotalReviews'] != 1 ? 's' : '' ?></p>
      </div>
    </section>

    <!-- Engagement metrics row -->
    <section class="engagement-row section-block">
      <h2 class="section-title">Engagement Overview</h2>
      <div class="engagement-grid">

        <div class="engagement-card">
          <p class="engagement-label">Retention Rate</p>
          <div class="engagement-ring-wrap">
            <svg class="ring-svg" viewBox="0 0 80 80">
              <circle class="ring-bg" cx="40" cy="40" r="32"/>
              <circle class="ring-fill" cx="40" cy="40" r="32"
                stroke-dasharray="<?= round($retentionRate * 2.011) ?> 201.1"
                stroke-dashoffset="50.3"/>
            </svg>
            <span class="ring-value"><?= $retentionRate ?>%</span>
          </div>
          <p class="engagement-meta"><?= (int)$summary['TotalEnrolled'] ?> enrolled · <?= (int)$summary['TotalCancelled'] ?> cancelled</p>
        </div>

        <div class="engagement-card">
          <p class="engagement-label">Capacity Used</p>
          <div class="engagement-ring-wrap">
            <svg class="ring-svg" viewBox="0 0 80 80">
              <circle class="ring-bg" cx="40" cy="40" r="32"/>
              <circle class="ring-fill ring-fill--blue" cx="40" cy="40" r="32"
                stroke-dasharray="<?= round($fillRate * 2.011) ?> 201.1"
                stroke-dashoffset="50.3"/>
            </svg>
            <span class="ring-value"><?= $fillRate ?>%</span>
          </div>
          <p class="engagement-meta"><?= (int)$summary['TotalEnrolled'] ?> / <?= (int)$summary['TotalCapacity'] ?> spots</p>
        </div>

        <div class="engagement-card">
          <p class="engagement-label">Total Enrollments</p>
          <p class="engagement-big"><?= (int)$summary['TotalEnrolled'] ?></p>
          <p class="engagement-meta"><?= (int)$summary['TotalCancelled'] ?> cancellations</p>
        </div>

        <div class="engagement-card">
          <p class="engagement-label">Nutrition Plans</p>
          <p class="engagement-big"><?= $nutritionPlans ?></p>
          <p class="engagement-meta">active plans assigned</p>
        </div>

      </div>
    </section>

    <!-- Charts row -->
    <?php if ($classes): ?>
    <section class="section-block">
      <h2 class="section-title">Attendance Statistics</h2>
      <div class="charts-grid">

        <div class="chart-card">
          <p class="chart-title">Enrollments by Day</p>
          <canvas id="dayChart" height="220"></canvas>
        </div>

        <div class="chart-card">
          <p class="chart-title">Enrollments by Class Type</p>
          <canvas id="typeChart" height="220"></canvas>
        </div>

      </div>
    </section>
    <?php endif; ?>

    <!-- Rating distribution -->
    <?php if ((int)$summary['TotalReviews'] > 0): ?>
    <section class="section-block">
      <h2 class="section-title">Ratings Summary</h2>
      <div class="ratings-layout">

        <div class="rating-score-block">
          <p class="rating-big"><?= e($summary['AvgRating']) ?></p>
          <div class="stars stars--lg">
            <?php
              $avg   = (float)$summary['AvgRating'];
              $full  = floor($avg);
              $half  = ($avg - $full) >= 0.5;
            ?>
            <?php for ($i = 1; $i <= 5; $i++): ?>
              <span class="star <?= $i <= $full ? 'star--filled' : ($i == $full + 1 && $half ? 'star--half' : '') ?>">★</span>
            <?php endfor; ?>
          </div>
          <p class="rating-total"><?= (int)$summary['TotalReviews'] ?> review<?= $summary['TotalReviews'] != 1 ? 's' : '' ?></p>
        </div>

        <div class="rating-bars">
          <?php for ($star = 5; $star >= 1; $star--): ?>
            <?php
              $count = $ratingDist[$star];
              $pct   = $maxRatingCount > 0 ? round(($count / $maxRatingCount) * 100) : 0;
            ?>
            <div class="rating-bar-row">
              <span class="rating-bar-label"><?= $star ?> ★</span>
              <div class="rating-bar-track">
                <div class="rating-bar-fill" style="width:<?= $pct ?>%"></div>
              </div>
              <span class="rating-bar-count"><?= $count ?></span>
            </div>
          <?php endfor; ?>
        </div>

      </div>
    </section>
    <?php endif; ?>

    <!-- Per-class breakdown -->
    <section class="section-block">
      <h2 class="section-title">Class Breakdown</h2>

      <?php if (!$classes): ?>
        <p class="empty-state">You have no active classes yet.</p>
      <?php else: ?>
        <div class="class-cards">
          <?php foreach ($classes as $cls):
            $enrolled   = (int)$cls['EnrolledCount'];
            $capacity   = (int)$cls['Capacity'];
            $pct        = $capacity > 0 ? round(($enrolled / $capacity) * 100) : 0;
            $rating     = $cls['AvgRating'];
            $stars      = $rating ? round($rating) : 0;
            $cancelRate = ($enrolled + (int)$cls['CancelledCount']) > 0
                ? round(((int)$cls['CancelledCount'] / ($enrolled + (int)$cls['CancelledCount'])) * 100)
                : 0;
          ?>
          <article class="class-card">
            <div class="class-card-head">
              <span class="class-type-badge"><?= e($cls['Type']) ?></span>
              <span class="class-day"><?= e($cls['Day']) ?> · <?= e($cls['StartTime']) ?>–<?= e($cls['EndTime']) ?></span>
            </div>
            <h3 class="class-name"><?= e($cls['Name']) ?></h3>

            <div class="class-stat-row">
              <span class="class-stat-label">Attendance</span>
              <span class="class-stat-value"><?= $enrolled ?>/<?= $capacity ?></span>
            </div>
            <div class="fill-bar-track">
              <div class="fill-bar-fill" style="width:<?= $pct ?>%"></div>
            </div>
            <p class="fill-pct"><?= $pct ?>% full</p>

            <div class="class-stat-row">
              <span class="class-stat-label">Cancellations</span>
              <span class="class-stat-value"><?= (int)$cls['CancelledCount'] ?><?= $cancelRate > 0 ? " <span class=\"cancel-rate\">({$cancelRate}%)</span>" : '' ?></span>
            </div>

            <div class="class-rating-row">
              <div class="stars">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                  <span class="star <?= $i <= $stars ? 'star--filled' : '' ?>">★</span>
                <?php endfor; ?>
              </div>
              <span class="rating-text">
                <?= $rating ? e($rating) : 'No ratings' ?>
                <?php if ($cls['ReviewCount']): ?>
                  <span class="rating-count">(<?= (int)$cls['ReviewCount'] ?>)</span>
                <?php endif; ?>
              </span>
            </div>
          </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>

    <!-- Recent reviews -->
    <?php if ($recentReviews): ?>
    <section class="section-block">
      <h2 class="section-title">Recent Reviews</h2>
      <div class="reviews-list">
        <?php foreach ($recentReviews as $review):
          $reviewStars = (int)$review['Rating'];
        ?>
        <article class="review-card">
          <div class="review-header">
            <div>
              <p class="review-class"><?= e($review['ClassName']) ?></p>
              <p class="review-member"><?= e($review['MemberName']) ?></p>
            </div>
            <div class="review-rating-wrap">
              <div class="stars">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                  <span class="star <?= $i <= $reviewStars ? 'star--filled' : '' ?>">★</span>
                <?php endfor; ?>
              </div>
              <span class="review-rating-num"><?= $reviewStars ?>/5</span>
            </div>
          </div>
          <?php if ($review['Comment']): ?>
            <p class="review-comment">"<?= e($review['Comment']) ?>"</p>
          <?php endif; ?>
          <p class="review-date"><?= e(date('d M Y', strtotime($review['CreatedAt']))) ?></p>
        </article>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>

  </main>

  <script src="js/navbar.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
  <script>
    const orange = '#f18c4f';
    const orangeDark = '#d87236';
    const peach = '#faebe0';
    const textDark = '#2a1b15';
    const textSoft = '#7d675b';

    Chart.defaults.font.family = 'Inter, sans-serif';
    Chart.defaults.color = textSoft;

    // Day chart
    const dayCtx = document.getElementById('dayChart');
    if (dayCtx) {
      new Chart(dayCtx, {
        type: 'bar',
        data: {
          labels: <?= $dayLabelsJ ?>,
          datasets: [
            {
              label: 'Enrolled',
              data: <?= $dayEnrolledJ ?>,
              backgroundColor: orange,
              borderRadius: 6,
              borderSkipped: false,
            },
            {
              label: 'Cancelled',
              data: <?= $dayCancelledJ ?>,
              backgroundColor: peach,
              borderRadius: 6,
              borderSkipped: false,
            }
          ]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: { legend: { position: 'bottom', labels: { boxWidth: 12 } } },
          scales: {
            x: { grid: { display: false } },
            y: { beginAtZero: true, ticks: { stepSize: 1 }, grid: { color: 'rgba(216,114,54,0.08)' } }
          }
        }
      });
    }

    // Class type doughnut
    const typeCtx = document.getElementById('typeChart');
    if (typeCtx) {
      const palette = ['#f18c4f','#d87236','#faebe0','#c0603a','#e8a87c','#b54e28','#f7d4be'];
      new Chart(typeCtx, {
        type: 'doughnut',
        data: {
          labels: <?= $typeLabels ?>,
          datasets: [{
            data: <?= $typeValues ?>,
            backgroundColor: palette,
            borderWidth: 0,
            hoverOffset: 8
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          cutout: '60%',
          plugins: {
            legend: { position: 'bottom', labels: { boxWidth: 12, padding: 14 } }
          }
        }
      });
    }
  </script>
</body>
</html>
