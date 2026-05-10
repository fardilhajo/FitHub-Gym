<?php
session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/security.php';

$stmt = $db->query('
    SELECT u.UserID, u.Name, u.ProfilePhoto, tp.Specializations,
           COUNT(fc.ClassID) AS ClassCount
    FROM USER u
    LEFT JOIN TRAINER_PROFILE tp ON u.UserID = tp.TrainerID
    LEFT JOIN FITNESS_CLASS fc ON fc.TrainerID = u.UserID AND fc.IsActive = 1
    WHERE u.Role = "Trainer" AND u.IsActive = 1
    GROUP BY u.UserID
    ORDER BY u.Name
');
$trainers = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>FitHub Gym - Trainers</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Crimson+Pro:wght@600;700&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/reset.css">
  <link rel="stylesheet" href="css/navbar.css">
  <link rel="stylesheet" href="css/pages/home.css">
</head>
<body>
  <?php include __DIR__ . '/includes/navbar.php'; ?>

  <main class="content">
    <div class="trainers-page-header">
      <h1>Our Coaching Team</h1>
      <p>Meet the trainers and the classes they teach.</p>
    </div>

    <section class="page-section">
      <div class="team-grid">
        <?php foreach ($trainers as $trainer): ?>
          <?php
            $photo = $trainer['ProfilePhoto'] ?: 'https://ui-avatars.com/api/?name=' . urlencode($trainer['Name']) . '&background=f18c4f&color=fff';
            $specs = array_filter(array_map('trim', explode(',', $trainer['Specializations'] ?? '')));
            $mainSpec = $specs[0] ?? 'Personal Trainer';
          ?>
          <a href="trainer-profile.php?id=<?= e($trainer['UserID']) ?>" class="team-card">
            <img src="<?= e($photo) ?>" alt="<?= e($trainer['Name']) ?>">
            <h3><?= e($trainer['Name']) ?></h3>
            <p><?= e($mainSpec) ?> · <?= e($trainer['ClassCount']) ?> classes</p>
          </a>
        <?php endforeach; ?>
      </div>
    </section>
  </main>

  <script src="js/navbar.js"></script>
</body>
</html>
