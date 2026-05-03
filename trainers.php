<?php
session_start();
require_once __DIR__ . '/includes/db.php';

// Fetch all trainers and their specializations
$stmt = $db->prepare('
    SELECT u.UserID, u.Name, u.ProfilePhoto, tp.Specializations 
    FROM USER u 
    LEFT JOIN TRAINER_PROFILE tp ON u.UserID = tp.TrainerID 
    WHERE u.Role = "Trainer" AND u.IsActive = 1
');
$stmt->execute();
$trainers = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
  <style>
    .trainers-page-header {
      text-align: center;
      margin-top: 4rem;
      margin-bottom: 3rem;
    }
    .trainers-page-header h1 {
      font-family: 'Crimson Pro', serif;
      font-size: 3rem;
      color: var(--dark);
      margin-bottom: 1rem;
    }
    .trainers-page-header p {
      color: var(--gray);
      font-size: 1.1rem;
    }
    .team-card {
      cursor: pointer;
      transition: transform 0.2s ease, box-shadow 0.2s ease;
      text-decoration: none;
      color: inherit;
    }
    .team-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 10px 20px rgba(0,0,0,0.05);
    }
  </style>
</head>
<body>
  <?php include __DIR__ . '/includes/navbar.php'; ?>

  <main class="content">
    <div class="trainers-page-header">
      <h1>Our Coaching Team</h1>
      <p>Meet the experts who will help you achieve your goals.</p>
    </div>

    <section class="page-section">
      <div class="team-grid">
        <?php foreach ($trainers as $trainer): ?>
          <?php 
            $isHttp = strpos($trainer['ProfilePhoto'], 'http') === 0;
            $photo = ($trainer['ProfilePhoto'] && ($isHttp || file_exists(__DIR__ . '/' . $trainer['ProfilePhoto']))) 
                ? htmlspecialchars($trainer['ProfilePhoto']) 
                : 'https://ui-avatars.com/api/?name=' . urlencode($trainer['Name']) . '&background=f18c4f&color=fff';
            // Extract just the first specialization to show on the card
            $specs = explode(',', $trainer['Specializations']);
            $mainSpec = isset($specs[0]) ? trim($specs[0]) : 'Personal Trainer';
          ?>
          <a href="trainer-profile.php?id=<?= $trainer['UserID'] ?>" class="team-card">
            <img src="<?= $photo ?>" alt="<?= htmlspecialchars($trainer['Name']) ?>">
            <h3><?= htmlspecialchars($trainer['Name']) ?></h3>
            <p><?= htmlspecialchars($mainSpec) ?></p>
          </a>
        <?php endforeach; ?>
      </div>
    </section>
  </main>

  <script src="js/navbar.js"></script>
</body>
</html>
