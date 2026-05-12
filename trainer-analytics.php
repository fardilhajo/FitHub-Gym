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
        COUNT(CASE WHEN ce.Status = "Enrolled" THEN 1 END)                 AS TotalEnrolled,
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

// Recent reviews (last 5)
$reviewStmt = $db->prepare('
    SELECT cr.Rating, cr.Comment, cr.CreatedAt, fc.Name AS ClassName, u.Name AS MemberName
    FROM CLASS_REVIEW cr
    JOIN FITNESS_CLASS fc ON cr.ClassID = fc.ClassID
    JOIN USER u           ON cr.MemberID = u.UserID
    WHERE fc.TrainerID = :id
    ORDER BY cr.CreatedAt DESC
    LIMIT 5
');
$reviewStmt->execute([':id' => $trainerId]);
$recentReviews = $reviewStmt->fetchAll();
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
        <p class="stat-value"><?= (int)$summary['TotalEnrolled'] ?></p>
        <p class="stat-label">Total Enrolled</p>
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
              <span class="class-stat-value"><?= (int)$cls['CancelledCount'] ?></span>
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
            <div class="stars">
              <?php for ($i = 1; $i <= 5; $i++): ?>
                <span class="star <?= $i <= $reviewStars ? 'star--filled' : '' ?>">★</span>
              <?php endfor; ?>
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
</body>
</html>
