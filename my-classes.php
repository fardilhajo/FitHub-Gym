<?php
session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/feature-schema.php';
require_once __DIR__ . '/includes/membership-access.php';

ensure_booking_feature_schema($db);
$user = current_user($db);
if (!$user || $user['Role'] !== 'Member') {
    redirect_to('login.php');
}

$activeMembershipPlan = active_membership_plan($db, $user);
if (!membership_plan_allows($activeMembershipPlan, 'Basic')) {
    $_SESSION['flash'] = 'Class management requires an active Basic membership or higher.';
    redirect_to('membership-plans.php');
}

$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $classId = (int)($_POST['class_id'] ?? 0);
    $rating  = (int)($_POST['rating'] ?? 0);
    $comment = trim($_POST['comment'] ?? '');

    if ($rating < 1 || $rating > 5) {
        $_SESSION['flash'] = 'Please select a rating between 1 and 5.';
        redirect_to('my-classes.php');
    }

    // Ensure the member is enrolled in this class
    $enrollCheck = $db->prepare('
        SELECT 1 FROM CLASS_ENROLLMENT
        WHERE ClassID = :class_id AND MemberID = :member_id AND Status = "Enrolled"
    ');
    $enrollCheck->execute([':class_id' => $classId, ':member_id' => $user['UserID']]);
    if (!$enrollCheck->fetchColumn()) {
        $_SESSION['flash'] = 'You can only review classes you are enrolled in.';
        redirect_to('my-classes.php');
    }

    $stmt = $db->prepare('
        INSERT INTO CLASS_REVIEW (ClassID, MemberID, Rating, Comment)
        VALUES (:class_id, :member_id, :rating, :comment)
        ON CONFLICT(ClassID, MemberID) DO UPDATE SET
            Rating = excluded.Rating,
            Comment = excluded.Comment,
            CreatedAt = CURRENT_TIMESTAMP
    ');
    $stmt->execute([
        ':class_id'  => $classId,
        ':member_id' => $user['UserID'],
        ':rating'    => $rating,
        ':comment'   => $comment ?: null,
    ]);

    $_SESSION['flash'] = 'Review submitted. Thank you!';
    redirect_to('my-classes.php');
}

// Fetch enrolled classes with existing review (if any)
$stmt = $db->prepare('
    SELECT
        fc.ClassID,
        fc.Name,
        fc.Type,
        fc.Day,
        fc.StartTime,
        fc.EndTime,
        fc.Room,
        fc.ImageURL,
        u.Name  AS TrainerName,
        cr.Rating   AS MyRating,
        cr.Comment  AS MyComment,
        cr.CreatedAt AS ReviewedAt
    FROM CLASS_ENROLLMENT ce
    JOIN FITNESS_CLASS fc ON fc.ClassID = ce.ClassID
    JOIN USER u ON fc.TrainerID = u.UserID
    LEFT JOIN CLASS_REVIEW cr ON cr.ClassID = ce.ClassID AND cr.MemberID = ce.MemberID
    WHERE ce.MemberID = :member_id AND ce.Status = "Enrolled" AND fc.IsActive = 1
    ORDER BY fc.Day, fc.StartTime
');
$stmt->execute([':member_id' => $user['UserID']]);
$classes = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>FitHub Gym - My Classes</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Crimson+Pro:wght@600;700&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/reset.css">
  <link rel="stylesheet" href="css/base.css">
  <link rel="stylesheet" href="css/components.css">
  <link rel="stylesheet" href="css/navbar.css">
  <link rel="stylesheet" href="css/pages/booking.css">
  <link rel="stylesheet" href="css/pages/my-classes.css">
</head>
<body>
  <?php include __DIR__ . '/includes/navbar.php'; ?>

  <main class="content">
    <header class="page-header">
      <h1>My Classes</h1>
      <p>Your enrolled classes — leave a rating and a quick note for each one.</p>
    </header>

    <?php if ($flash): ?>
      <p class="page-message"><?= e($flash) ?></p>
    <?php endif; ?>

    <?php if (empty($classes)): ?>
      <p class="empty-state">You are not enrolled in any classes yet. <a href="book-class.php">Browse classes</a></p>
    <?php else: ?>
      <div class="myclasses-grid">
        <?php foreach ($classes as $class):
          $image = $class['ImageURL'] ?: 'https://images.unsplash.com/photo-1534438327276-14e5300c3a48?auto=format&fit=crop&w=700&q=80';
          $reviewed = $class['MyRating'] !== null;
        ?>
        <article class="myclass-card">
          <div class="myclass-image">
            <img src="<?= e($image) ?>" alt="<?= e($class['Name']) ?>">
            <span class="tag-type"><?= e($class['Type']) ?></span>
          </div>
          <div class="myclass-body">
            <h3><?= e($class['Name']) ?></h3>
            <div class="class-info">
              <span><strong>Trainer:</strong> <?= e($class['TrainerName']) ?></span>
              <span>📅 <?= e($class['Day']) ?></span>
              <span>🕒 <?= e($class['StartTime']) ?> – <?= e($class['EndTime']) ?></span>
              <span>📍 <?= e($class['Room']) ?></span>
            </div>

            <?php if ($reviewed): ?>
              <div class="existing-review">
                <div class="star-display">
                  <?php for ($i = 1; $i <= 5; $i++): ?>
                    <span class="star <?= $i <= (int)$class['MyRating'] ? 'star--filled' : '' ?>">★</span>
                  <?php endfor; ?>
                  <span class="review-date"><?= e(date('d M Y', strtotime($class['ReviewedAt']))) ?></span>
                </div>
                <?php if ($class['MyComment']): ?>
                  <p class="review-comment">"<?= e($class['MyComment']) ?>"</p>
                <?php endif; ?>
                <button class="btn-edit-review" onclick="toggleForm(<?= (int)$class['ClassID'] ?>)">Edit review</button>
              </div>
            <?php else: ?>
              <p class="no-review-hint">You haven't reviewed this class yet.</p>
              <button class="btn-rate" onclick="toggleForm(<?= (int)$class['ClassID'] ?>)">Rate this class</button>
            <?php endif; ?>

            <form class="review-form" id="form-<?= (int)$class['ClassID'] ?>" method="post" action="my-classes.php">
              <?= csrf_input() ?>
              <input type="hidden" name="class_id" value="<?= (int)$class['ClassID'] ?>">

              <div class="star-picker" id="stars-<?= (int)$class['ClassID'] ?>">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                  <label class="star-label">
                    <input type="radio" name="rating" value="<?= $i ?>"
                      <?= (int)$class['MyRating'] === $i ? 'checked' : '' ?> required>
                    <span class="star-icon">★</span>
                  </label>
                <?php endfor; ?>
              </div>

              <textarea name="comment" class="review-textarea" placeholder="Optional comment..." rows="3"><?= e($class['MyComment'] ?? '') ?></textarea>

              <div class="review-actions">
                <button type="submit" class="btn-submit-review">Submit</button>
                <button type="button" class="btn-cancel-review" onclick="toggleForm(<?= (int)$class['ClassID'] ?>)">Cancel</button>
              </div>
            </form>
          </div>
        </article>
        <?php endforeach; ?>
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
  <script>
    function toggleForm(classId) {
      const form = document.getElementById('form-' + classId);
      form.classList.toggle('review-form--open');
    }

    // Star picker hover effect
    document.querySelectorAll('.star-picker').forEach(picker => {
      const labels = picker.querySelectorAll('.star-label');
      labels.forEach((label, idx) => {
        label.addEventListener('mouseenter', () => {
          labels.forEach((l, i) => l.querySelector('.star-icon').classList.toggle('star--hover', i <= idx));
        });
        label.addEventListener('mouseleave', () => {
          labels.forEach(l => l.querySelector('.star-icon').classList.remove('star--hover'));
        });
      });
    });
  </script>
</body>
</html>
